<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\Hook\UserGetRightsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\Hook\UserEffectiveGroupsHook;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupMembership;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use Wikimedia\Message\ListParam;
use Wikimedia\Message\ListType;

class HookHandler implements
	AuthChangeFormFieldsHook,
	GetPreferencesHook,
	getUserPermissionsErrorsHook,
	UserEffectiveGroupsHook,
	UserGetRightsHook
{
	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly PermissionManager $permissionManager,
		private readonly Config $config,
		private readonly UserGroupManager $userGroupManager,
		private readonly CentralIdLookupFactory $centralIdLookupFactory,
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

			// Reorder 2FA types according to OATHPrioritizedModules
			$orderedModules = [];
			foreach ( $this->config->get( 'OATHPrioritizedModules' ) as $moduleName ) {
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
					'default' => $moduleName,
					'buttonlabel' => $buttonMessage->text(),
					// Make sure these buttons appear after the loginattempt button
					'weight' => $loginButtonWeight + $extraWeight,
					'flags' => [],
				];
				$extraWeight++;
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
			static fn ( IAuthKey $key ) => $key->getModule(),
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
		$disabledGroups = $this->getDisabledGroups( $user, $groups );
		if ( $disabledGroups ) {
			$groups = array_diff( $groups, $disabledGroups );
		}

		// Enable 2FA for users in gradual rollout if MFARollout is enabled.
		// Exclude temp users and users without email addresses; check this first
		// so that we don't try to look up central user IDs for non-named users.
		if ( $user->isNamed() && $user->getEmail() ) {
			$centralID = $this->centralIdLookupFactory->getLookup()
				->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
			$MFARollout = $this->config->get( 'OATHRolloutPercent' );
			if ( $centralID % 100 < $MFARollout ) {
				$groups[] = "oathauth-twofactorauth";
			}
		}
	}

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( !$this->config->has( 'OATHExclusiveRights' ) ) {
			return true;
		}

		// TODO: Get the session from somewhere more... sane?
		$session = $user->getRequest()->getSession();
		if (
			!$session->get( OATHAuth::AUTHENTICATED_OVER_2FA, false ) &&
			in_array( $action, $this->config->get( 'OATHExclusiveRights' ) )
		) {
			$result = 'oathauth-action-exclusive-to-2fa';
			return false;
		}
		return true;
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
}
