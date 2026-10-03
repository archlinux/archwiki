<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use BadMethodCallException;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\WebAuthnAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\HTMLField\NoJsInfoField;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\Hook\UserGetRightsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Hook\ReadPrivateUserRequirementsConditionHook;
use MediaWiki\User\Hook\UserEffectiveGroupsHook;
use MediaWiki\User\Hook\UserRequirementsConditionHook;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentity;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use Wikimedia\Message\ListParam;
use Wikimedia\Message\ListType;

class HookHandler implements
	AuthChangeFormFieldsHook,
	BeforePageDisplayHook,
	GetPreferencesHook,
	ReadPrivateUserRequirementsConditionHook,
	UserEffectiveGroupsHook,
	UserGetRightsHook,
	UserRequirementsConditionHook
{
	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly PermissionManager $permissionManager,
		private readonly Config $config,
		private readonly UserGroupManager $userGroupManager,
	) {
	}

	/** @inheritDoc */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( isset( $fieldInfo['OATHToken'] ) ) {
			$formDescriptor['OATHToken'] += [
				'cssClass' => 'loginText',
				'id' => 'wpOATHToken',
				'size' => 20,
				'dir' => 'ltr',
				'autofocus' => true,
				'persistent' => false,
				'autocomplete' => 'one-time-code',
				'spellcheck' => false,
				'help-message' => 'oathauth-auth-token-help-ui',
			];
		}

		if ( isset( $fieldInfo['RecoveryCode'] ) ) {
			$formDescriptor['RecoveryCode'] += [
				'dir' => 'ltr',
				'autofocus' => true,
				'persistent' => false,
				'autocomplete' => 'off',
				'spellcheck' => false,
				'help-message' => 'oathauth-auth-recovery-code-help',
			];
		}

		if ( isset( $fieldInfo['newModule'] ) ) {
			// HACK: Hide the newModule <select>, but keep it in form, otherwise HTMLForm won't
			// understand the button weirdness below. There's no great way for us to inject CSS, so
			// abuse a CSS class from core that has display: none; on it.
			// TODO: Make this multi-button thing a real HTMLForm field (T404664)
			$formDescriptor['newModule']['cssclass'] = 'emptyPortlet';
			if ( isset( $formDescriptor['OATHToken'] ) ) {
				// Don't make the TOTP token field required. Otherwise, the "Switch to XYZ" submit
				// buttons can't be used without filling in this field
				$formDescriptor['OATHToken']['required'] = false;
			}
			// Check the weight of the form submit button to make sure other authentication
			// options are placed below it
			$loginButtonWeight = $formDescriptor['loginattempt']['weight'] ?? 100;

			$availableModules = $fieldInfo['newModule']['options'];
			// Remove the empty option for not switching first
			unset( $availableModules[''] );

			// Reorder 2FA types according to SecondaryAuthenticationProvider Module Priority
			$orderedModules = [];
			foreach ( SecondaryAuthenticationProvider::MODULE_PRIORITY as $moduleName ) {
				if ( isset( $availableModules[$moduleName] ) ) {
					$orderedModules[$moduleName] = $availableModules[$moduleName];
					unset( $availableModules[$moduleName] );
				}
			}
			// Append any remaining modules that weren’t in the priority list
			$availableModules = $orderedModules + $availableModules;

			$extraWeight = 1;
			foreach ( $availableModules as $moduleName => $ignored ) {
				// Add a switch button for each alternative module, all with name="newModule"
				// Whichever button is clicked will submit the form, with newModule set to its value
				$buttonMessage = $this->moduleRegistry->getModuleByKey( $moduleName )->getLoginSwitchButtonMessage();
				$formDescriptor["newModule_$moduleName"] = [
					'type' => 'submit',
					'name' => 'newModule',
					// Set and explicit id, as HTMLFormField assigns autogenerates ids based on the name field.
					// As all buttons share the same name="newModule" field, all would get the same id otherwise
					// Duplicate IDs confuse VoiceOver, making it announce all buttons with the same label.
					'id' => "mw-input-newModule_$moduleName",
					'default' => $moduleName,
					'buttonlabel' => $buttonMessage->text(),
					// Make sure these buttons appear after the loginattempt button
					'weight' => $loginButtonWeight + $extraWeight,
					'flags' => [],
				];
				$extraWeight++;
			}
		}

		$webauthnReq = AuthenticationRequest::getRequestByClass( $requests, WebAuthnAuthenticationRequest::class );
		// Display a message about needing JavaScript for WebAuthn, but don't display it if we're on
		// the initial login page (the WebAuthnAuthenticationRequest there is for passwordless login)
		if ( $webauthnReq && !isset( $fieldInfo['username'] ) ) {
			$formDescriptor['webauthn-nojs'] = [
				'class' => NoJsInfoField::class,
				'weight' => -50,
			];
		}

		if ( $this->config->get( 'OATHPasswordlessLogin' ) && isset( $fieldInfo['username'] ) ) {
			$formDescriptor['username']['autocomplete'] = 'username webauthn';

			// HACK autofocus the username even when it's prepopulated
			$formDescriptor['username']['autofocus'] = true;
			if ( isset( $formDescriptor['password']['autofocus'] ) ) {
				unset( $formDescriptor['password']['autofocus'] );
			}
		}

		return true;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$oathUser = $this->userRepo->findByUser( $user );

		// If there is no existing module for the user, and the user is not allowed to enable it,
		// we have nothing to show.
		if (
			!$oathUser->isTwoFactorAuthEnabled() &&
			!$this->permissionManager->userHasRight( $user, 'oathauth-enable' )
		) {
			return true;
		}

		$modules = array_unique( array_map(
			static fn ( AuthKey $key ) => $key->getModule(),
			$oathUser->getKeys(),
		) );
		$moduleNames = array_map(
			fn ( string $moduleId ) => $this->moduleRegistry
				->getModuleByKey( $moduleId )
				->getDisplayName(),
			$modules
		);

		if ( count( $moduleNames ) > 1 ) {
			$moduleLabel = wfMessage( 'rawmessage' )
				->params( new ListParam( ListType::AND, $moduleNames ) );
		} elseif ( $moduleNames ) {
			$moduleLabel = $moduleNames[0];
		} else {
			$moduleLabel = wfMessage( 'oathauth-ui-no-module' );
		}

		$manageButton = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => wfMessage( 'oathauth-ui-manage' )->text()
		] );

		$currentModuleLabel = new LabelWidget( [
			'label' => $moduleLabel->text(),
		] );

		$control = new HorizontalLayout( [
			'items' => [
				$currentModuleLabel,
				$manageButton
			]
		] );

		$preferences['oathauth-module'] = [
			'type' => 'info',
			'raw' => true,
			'default' => (string)$control,
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/info',
		];

		$disabledGroups = $this->getDisabledGroups( $user, $this->userGroupManager->getUserGroups( $user ) );
		if ( $disabledGroups && !$oathUser->isTwoFactorAuthEnabled() ) {
			$context = RequestContext::getMain();
			$list = [];
			foreach ( $disabledGroups as $disabledGroup ) {
				$list[] = UserGroupMembership::getLinkHTML( $disabledGroup, $context );
			}
			$info = $context->getLanguage()->commaList( $list );
			$disabledInfo = [ 'oathauth-disabledgroups' => [
				'type' => 'info',
				'label-message' => [ 'oathauth-prefs-disabledgroups',
					Message::numParam( count( $disabledGroups ) ) ],
				'help-message' => [ 'oathauth-prefs-disabledgroups-help',
					Message::numParam( count( $disabledGroups ) ), $user->getName() ],
				'default' => $info,
				'raw' => true,
				'section' => 'personal/info',
			] ];
			// Insert right after "Member of groups"
			$preferences = wfArrayInsertAfter( $preferences, $disabledInfo, 'usergroups' );
		}

		return true;
	}

	/**
	 * Return the groups that this user is supposed to be in, but are disabled
	 * because 2FA isn't enabled
	 *
	 * @param User $user
	 * @param string[] $groups All groups the user is supposed to be in
	 * @return string[] Groups the user should be disabled in
	 */
	private function getDisabledGroups( User $user, array $groups ): array {
		$requiredGroups = $this->config->get( 'OATHRequiredForGroups' );
		// Bail early if:
		// * No configured restricted groups
		// * The user is not in any of the restricted groups
		$intersect = array_intersect( $groups, $requiredGroups );
		if ( !$requiredGroups || !$intersect ) {
			return [];
		}

		$oathUser = $this->userRepo->findByUser( $user );
		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			// Not enabled, strip the groups
			return $intersect;
		}

		return [];
	}

	/**
	 * Remove groups if 2FA is required for them and it's not enabled
	 *
	 * @inheritDoc
	 */
	public function onUserEffectiveGroups( $user, &$groups ) {
		// If the user has 2FA disabled, don't leak that information to other users (T412061)
		try {
			if ( !$user->equals( RequestContext::getMain()->getUser() ) ) {
				return;
			}
		} catch ( BadMethodCallException ) {
			// If we got this exception, it means we are in a session-less entry point.
			// Treat this as if the current user is not the same as $user, and don't expose
			// $user's potential lack of 2FA
			return;
		}

		$disabledGroups = $this->getDisabledGroups( $user, $groups );
		if ( $disabledGroups ) {
			$groups = array_diff( $groups, $disabledGroups );
		}
	}

	/**
	 * If a user has groups disabled for not having 2FA enabled, make sure they
	 * have "oathauth-enable" so they can turn it on
	 *
	 * @inheritDoc
	 */
	public function onUserGetRights( $user, &$rights ) {
		if ( in_array( 'oathauth-enable', $rights ) ) {
			return;
		}

		$dbGroups = $this->userGroupManager->getUserGroups( $user );
		if ( $this->getDisabledGroups( $user, $dbGroups ) ) {
			// User has some disabled groups, add oathauth-enable
			$rights[] = 'oathauth-enable';
		}
	}

	/**
	 * Callback that generates the contents of the virtual data.json file in the ext.oath.manage
	 * ResourceLoader module.
	 */
	public static function getOathManageModuleData( Context $context ): array {
		return [
			'passkeyDialogTextHtml' => $context->msg( 'oathauth-passkey-dialog-text' )->parseAsBlock()
		];
	}

	/** @inheritDoc */
	public function onUserRequirementsCondition(
		string|int $type,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest,
		?bool &$result
	): void {
		if ( $type !== APCOND_OATH_HAS2FA ) {
			return;
		}
		$result = $this->userRepo->userHas2FAEnabled( $user );
	}

	/** @inheritDoc */
	public function onReadPrivateUserRequirementsCondition(
		UserIdentity $performer,
		UserIdentity $target,
		array $conditions
	): void {
		if ( in_array( APCOND_OATH_HAS2FA, $conditions ) ) {
			$this->oathLogger->logImplicitVerification( $performer, $target );
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$this->config->get( 'OATHPasswordlessLogin' ) &&
			$out->getTitle()->isSpecial( 'Userlogin' )
		) {
			$out->addModules( 'ext.webauthn.passwordlessLogin' );
		}
	}
}
