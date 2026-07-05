<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker;
use MediaWiki\Extension\OATHAuth\HTMLForm\DisableForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCode;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupManager;
use MediaWiki\WikiMap\WikiMap;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use Wikimedia\Codex\Utility\Codex;

/**
 * Initializes a page to manage available 2FA modules
 */
class OATHManage extends SpecialPage {
	use RecoveryCodesTrait;

	public const ACTION_ENABLE = 'enable';
	public const ACTION_DISABLE = 'disable';
	public const ACTION_DELETE = 'delete';

	protected OATHUser $oathUser;

	protected string $action;

	protected ?IModule $requestedModule;

	private array $groupsRequiring2FA;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly Mandatory2FAChecker $mandatory2FAChecker,
		private readonly AuthManager $authManager,
		private readonly UserGroupManager $userGroupManager,
	) {
		// messages used: oathmanage (display "name" on Special:SpecialPages)
		parent::__construct( 'OATHManage' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		// messages used: right-oathauth-enable, action-oathauth-enable
		return 'oathauth-enable';
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'login';
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'accountsecurity' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->oathUser = $this->userRepo->findByUser( $this->getUser() );
		$this->groupsRequiring2FA = $this->get2FAGroupsData();

		$this->getOutput()->enableOOUI();
		$this->getOutput()->disallowUserJs();
		$this->setAction();
		$this->setModule();

		parent::execute( $subPage );

		if ( $this->action === self::ACTION_DELETE ) {
			$this->showDeleteWarning();
			return;
		} elseif ( $this->requestedModule instanceof IModule ) {
			// Performing an action on a requested module
			$this->clearPage();
			$this->addModuleHTML( $this->requestedModule );
			return;
		}

		$this->displayNewUI();

		// recovery codes
		if ( $this->hasSpecialModules() ) {
			$this->addSpecialModulesHTML();
		}
	}

	public function checkPermissions() {
		$this->requireNamedUser();

		if ( !$this->oathUser->getCentralId() ) {
			throw new ErrorPageError(
				'oathauth-enable',
				'oathauth-must-be-central',
				[ $this->getUser()->getName() ]
			);
		}

		$canEnable = $this->getUser()->isAllowed( 'oathauth-enable' );

		if ( $this->action === static::ACTION_ENABLE && !$canEnable ) {
			$this->displayRestrictionError();
		}

		if ( !$canEnable && !$this->oathUser->isTwoFactorAuthEnabled() ) {
			// No enabled module and cannot enable - nothing to do
			$this->displayRestrictionError();
		}
	}

	private function setAction(): void {
		$this->action = $this->getRequest()->getVal( 'action', '' );
	}

	private function setModule(): void {
		$moduleKey = $this->getRequest()->getVal( 'module', '' );
		$this->requestedModule = ( $moduleKey && $this->moduleRegistry->moduleExists( $moduleKey ) )
			? $this->moduleRegistry->getModuleByKey( $moduleKey )
			: null;
	}

	/**
	 * Get the name, description, and timestamp to display for a given key.
	 * @param AuthKey $key
	 * @return array{name:string, description?:string, timestamp:?string}
	 */
	private function getKeyNameAndDescription( AuthKey $key ): array {
		$keyName = $key->getFriendlyName();
		$moduleName = $this->moduleRegistry->getModuleByKey( $key->getModule() )->getDisplayName()->text();
		$createdTimestamp = null;
		$timestamp = $key->getCreatedTimestamp();

		if ( $timestamp !== null ) {
			$createdTimestamp = $this->msg(
				'oathauth-created-at',
				Message::dateParam( $timestamp )
			)->text();
		}

		// Use the key if it has a non-empty name and set the description to the module name
		if ( $keyName !== null && trim( $keyName ) !== '' ) {
			return [
				'name' => $keyName,
				'description' => $moduleName,
				'timestamp' => $createdTimestamp
			];
		}

		// If the key has no name, use the module name as the name and send the timestamp
		return [
			'name' => $moduleName,
			'timestamp' => $createdTimestamp
		];
	}

	private function canRemoveKeys(): bool {
		if ( !$this->groupsRequiring2FA ) {
			return true;
		}
		$numKeys = 0;
		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			if ( !$key->supportsPasswordlessLogin() ) {
				$numKeys++;
			}
		}
		// If there's exactly one proper key (non-special and non-passwordless), it cannot be removed, because
		// then whole 2FA would be disabled for the user.
		return $numKeys !== 1;
	}

	/**
	 * Prepares data about the current user's groups that require 2FA
	 */
	private function get2FAGroupsData(): array {
		global $wgConf;
		'@phan-var \MediaWiki\Config\SiteConfiguration $wgConf';

		$groupsRequiring2FA = $this->mandatory2FAChecker->getGroupsRequiring2FAAcrossWikiFarm( $this->getUser() );

		// Keyed by wiki, then by page, with the value being an array of groups
		$splitGroups = [];
		foreach ( $groupsRequiring2FA as $wikiId => $groupsOnWiki ) {
			if ( WikiMap::isCurrentWikiId( $wikiId ) ) {
				$groupRemovalPages = $this->getConfig()->get( 'OATH2FARequiredGroupRemovalPages' ) ?? [];
			} else {
				$groupRemovalPages = $wgConf->get( 'wgOATH2FARequiredGroupRemovalPages', $wikiId ) ?? [];
			}
			foreach ( $groupsOnWiki as $group ) {
				$relevantPage = $groupRemovalPages[$group] ?? $groupRemovalPages['*'] ?? '';
				$splitGroups[$wikiId][$relevantPage][] = $group;
			}
		}

		$lang = $this->getLanguage();
		$result = [];
		foreach ( $splitGroups as $wikiId => $pages ) {
			$wiki = WikiMap::getWiki( $wikiId );
			if ( $wiki === null && !WikiMap::isCurrentWikiId( $wikiId ) ) {
				// Skip remote wikis that cannot be resolved via WikiMap.
				continue;
			}

			foreach ( $pages as $page => $groups ) {
				$groupNames = array_map(
					fn ( $group ) => $lang->getGroupMemberName( $group, $this->getUser() ),
					$groups
				);

				if ( $wiki !== null ) {
					$wikiName = $wiki->getDisplayName();
					$url = $page !== '' ? $wiki->getUrl( $page ) : '';
				} else {
					// If there's no $wgConf nor sites table, the current wiki may not be resolvable using WikiMap
					// Fallback to local resolution of relevant settings
					$wikiName = $this->getConfig()->get( MainConfigNames::Sitename );
					$title = $page !== '' ? Title::newFromText( $page ) : null;
					$url = $title ? $title->getFullURL() : '';
				}

				$result[] = [
					'wiki' => $wikiName,
					'page' => $page,
					'url' => $url,
					'groupNames' => $groupNames,
				];
			}
		}
		return $result;
	}

	private function build2FARequiredNotice(): string {
		$codex = new Codex();
		$lang = $this->getLanguage();
		$message = $codex->message();
		$message->setAttributes( [ 'class' => 'mw-special-OATHManage-mandatory-2fa' ] );
		if ( $this->oathUser->isTwoFactorAuthEnabled() ) {
			$message->setInline( true )
				->setContentText( $this->msg( 'oathauth-2fa-required' )->text() );
		} else {
			$groupsPerWiki = [];
			foreach ( $this->groupsRequiring2FA as $entry ) {
				$groupsPerWiki[$entry['wiki']] = array_merge(
					$groupsPerWiki[$entry['wiki']] ?? [],
					$entry['groupNames']
				);
			}

			$content = $this->msg( 'oathauth-2fa-required' )->parse();
			$listItems = '';
			foreach ( $groupsPerWiki as $wiki => $groups ) {
				$listItems .= Html::rawElement( 'li', [], $this->msg(
					'oathauth-2fa-required-groups-on-project',
					count( $groups ),
					$lang->listToText( $groups ),
					$wiki
				)->parse() );
			}
			$content .= Html::rawElement( 'ul', [], $listItems );
			$message->setContentHtml( $codex->htmlSnippet()->setContent( $content )->build() );
		}
		return $message->build()->getHtml();
	}

	private function buildIrremovableKeyNotice(): string {
		$lang = $this->getLanguage();
		if ( count( $this->groupsRequiring2FA ) === 1 ) {
			$entry = $this->groupsRequiring2FA[0];
			if ( $entry['url'] ) {
				$pageLink = '[' . $entry['url'] . ' ' . $entry['page'] . ']';
			} else {
				$pageLink = $this->msg( 'oathauth-2fa-groups-notice-unknown-page' )->parse();
			}
			$noticeContent = $this->msg( 'oathauth-2fa-groups-notice-single' )
				->params(
					count( $entry['groupNames'] ),
					$lang->listToText( $entry['groupNames'] ),
					$entry['wiki'],
					$pageLink
				)
				->parse();
		} else {
			$totalGroups = 0;
			foreach ( $this->groupsRequiring2FA as $entry ) {
				$totalGroups += count( $entry['groupNames'] );
			}

			$noticeContent = $this->msg( 'oathauth-2fa-groups-notice-multiple' )->params( $totalGroups )->parse();
			$noticeContent .= Html::rawElement(
				'div',
				[ 'class' => 'mw-special-OATHManage-2fa-groups-list-intro' ],
				$this->msg( 'oathauth-2fa-groups-notice-multiple-links-intro' )->params( $totalGroups )->parse()
			);
			$noticeContent .= Html::openElement( 'ul' );

			foreach ( $this->groupsRequiring2FA as $entry ) {
				if ( $entry['url'] ) {
					$pageLink = '[' . $entry['url'] . ' ' . $entry['page'] . ']';
				} else {
					$pageLink = $this->msg( 'oathauth-2fa-groups-notice-unknown-page' )->parse();
				}
				$content = $this->msg( 'oathauth-2fa-groups-notice-multiple-links-entry' )
					->params(
						count( $entry['groupNames'] ),
						$lang->listToText( $entry['groupNames'] ),
						$entry['wiki'],
						$pageLink
					)
					->parse();
				$noticeContent .= Html::rawElement( 'li', [], $content );
			}
			$noticeContent .= Html::closeElement( 'ul' );
		}
		$codex = new Codex();
		return $codex->message()
			->setType( 'warning' )
			->setAttributes( [ 'class' => 'mw-special-OATHManage-2fa-groups-notice' ] )
			->setContentHtml( $codex->htmlSnippet()->setContent( $noticeContent )->build() )
			->build()
			->getHtml();
	}

	private function buildKeyAccordion( AuthKey $key, string $noticeHtml = '', bool $removable = true ): string {
		$codex = new Codex();
		$keyData = $this->getKeyNameAndDescription( $key );
		$keyAccordion = $codex->accordion()
			->setTitle( $keyData['name'] )
			// TODO support outlined Accordions in Codex-PHP (T416645)
			->setAttributes( [ 'class' => 'cdx-accordion--separation-outline' ] );

		$accordionDescription = $keyData['timestamp'] ?? $keyData['description'] ?? null;
		if ( $accordionDescription !== null ) {
			$keyAccordion->setDescription( $accordionDescription );
		}

		$keyAccordion
			->setContentHtml( $codex->htmlSnippet()->setContent(
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__method-actions'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'module', $key->getModule() ) .
					Html::hidden( 'keyId', $key->getId() ) .
					Html::hidden( 'warn', '1' ) .
					// TODO implement rename (T401775)
					$codex->button()
						->setLabel( $this->msg( 'oathauth-authenticator-delete' )->text() )
						->setAction( 'destructive' )
						->setWeight( 'primary' )
						->setDisabled( !$removable )
						->setType( 'submit' )
						->setAttributes( [ 'name' => 'action', 'value' => self::ACTION_DELETE ] )
						->build()
						->getHtml()
				) . $noticeHtml
			)->build() );
		return $keyAccordion->build()->getHtml();
	}

	private function buildVueData(): array {
		$data = [
			'modules' => [],
			'keys' => [],
			'passkeys' => [],
			'groupsRequiring2FA' => $this->groupsRequiring2FA
		];

		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			$labelMessage = $module->getAddKeyMessage();
			if ( $labelMessage ) {
				$data['modules'][] = [
					'name' => $module->getName(),
					'labelMessage' => $labelMessage->text()
				];
			}
		}

		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			$keyData = [
				'id' => $key->getId(),
				'module' => $key->getModule()
			] + $this->getKeyNameAndDescription( $key );

			if ( $key->supportsPasswordlessLogin() ) {
				$data['passkeys'][] = $keyData;
			} else {
				$data['keys'][] = $keyData;
			}
		}

		return $data;
	}

	private function displayNewUI(): void {
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.oath.manage.styles' );
		$output->addModules( 'ext.oath.manage' );
		$output->addJsConfigVars( 'wgOATHManageData', $this->buildVueData() );
		$codex = new Codex();

		// Show the delete success message, if applicable
		$deletedKeyName = $this->getRequest()->getVal( 'deletesuccess' );
		if ( $deletedKeyName !== null ) {
			$output->addHTML( Html::successBox(
				$this->msg( 'oathauth-delete-success' )->parse()
			) );
		}

		// Add the success message for newly enabled key
		$addedKeyName = $this->getRequest()->getVal( 'addsuccess' );
		if ( $addedKeyName !== null ) {
			$output->addHTML(
				Html::successBox(
					$this->msg( 'oathauth-enable-success' )->parse()
				)
			);
		}

		// Password section
		if ( $this->authManager->allowsAuthenticationDataChange(
			new PasswordAuthenticationRequest(), false )->isGood()
		) {
			$output->addHTML(
				Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-password' ],
					Html::element( 'h3', [], $this->msg( 'oathauth-password-header' )->text() ) .
					Html::rawElement( 'form', [
							'action' => wfScript(),
							'class' => 'mw-special-OATHManage-password__form'
						],
						Html::hidden( 'title', self::getTitleFor( 'ChangePassword' )->getPrefixedDBkey() ) .
						Html::hidden( 'returnto', $this->getPageTitle()->getPrefixedDBkey() ) .
						Html::element( 'p',
							[ 'class' => 'mw-special-OATHManage-password__label' ],
							$this->msg( 'oathauth-password-label' )->text()
						) .
						$codex->button()
							->setLabel( $this->msg( 'oathauth-password-action' )->text() )
							->setType( 'submit' )
							->build()
							->getHtml()
					)
				)
			);
		}

		// 2FA section
		$canRemoveKeys = $this->canRemoveKeys();
		$irremovableKeyNotice = '';
		if ( !$canRemoveKeys ) {
			$irremovableKeyNotice = $this->buildIrremovableKeyNotice();
		}

		$keyAccordions = '';
		$keyPlaceholder = '';
		$mandatory2FAMessage = '';
		$authmethodsClasses = [
			'mw-special-OATHManage-authmethods'
		];
		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			if ( $key->supportsPasswordlessLogin() ) {
				// Keys that support passwordless login are displayed in the passkeys section instead
				continue;
			}

			$keyAccordions .= $this->buildKeyAccordion( $key, $irremovableKeyNotice, $canRemoveKeys );
		}
		if ( $keyAccordions === '' ) {
			// User has no keys, display the placeholder message instead
			$keyPlaceholder = Html::element( 'p',
				[ 'class' => 'mw-special-OATHManage-authmethods__placeholder' ],
				$this->msg( 'oathauth-authenticator-placeholder' )->text()
			);
			$authmethodsClasses[] = 'mw-special-OATHManage-authmethods--no-keys';
		}

		if ( $this->groupsRequiring2FA ) {
			$mandatory2FAMessage = $this->build2FARequiredNotice();
		}

		$moduleButtons = '';
		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			$labelMessage = $module->getAddKeyMessage();
			if ( !$labelMessage ) {
				continue;
			}
			$moduleButtons .= $codex
				->button()
				->setLabel( $labelMessage->text() )
				->setType( 'submit' )
				->setAttributes( [ 'name' => 'module', 'value' => $module->getName() ] )
				->build()
				->getHtml();
		}

		$authMethodsSection = Html::rawElement( 'div', [ 'class' => $authmethodsClasses ],
			Html::element( 'h3', [], $this->msg( 'oathauth-authenticator-header' )->text() ) .
			$mandatory2FAMessage . $keyAccordions .
			Html::rawElement( 'form', [
					'action' => wfScript(),
					'class' => 'mw-special-OATHManage-authmethods__addform'
				],
				Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
				Html::hidden( 'action', 'enable' ) .
				$keyPlaceholder .
				$moduleButtons
			)
		);

		// Passkeys section
		$passkeySection = '';
		$passkeyAccordions = '';
		$passkeyPlaceholder = '';
		$passkeyClasses = [ 'mw-special-OATHManage-passkeys' ];
		foreach ( $this->oathUser->getNonSpecialKeys() as $key ) {
			if ( !$key->supportsPasswordlessLogin() ) {
				// Regular 2FA keys are displayed in the 2FA section below
				continue;
			}
			$passkeyAccordions .= $this->buildKeyAccordion( $key );
		}
		if ( $passkeyAccordions === '' ) {
			$passkeyPlaceholder = Html::element( 'p',
				[ 'class' => 'mw-special-OATHManage-passkeys__placeholder' ],
				$this->msg( 'oathauth-passkeys-placeholder' )->text()
			);
			// Display an additional message if the user can't add passkeys
			if ( $keyAccordions === '' ) {
				$passkeyPlaceholder .= Html::element( 'p',
					[ 'class' => 'mw-special-OATHManage-passkeys__placeholder' ],
					 $this->msg( 'oathauth-passkeys-no2fa' )->text()
				);
			}
			$passkeyClasses[] = 'mw-special-OATHManage-passkeys--no-keys';
		}
		// Only display the "Add passkey" button if the user can add passkeys
		$passkeyAddButton = $keyAccordions === '' ? '' : $codex->button()
			->setLabel( $this->msg( 'oathauth-passkeys-add' )->text() )
			->setAttributes( [ 'class' => 'mw-special-OATHManage-passkeys__addbutton' ] )
			->build()
			->getHtml();
		$passkeySection = Html::rawElement( 'div', [ 'class' => $passkeyClasses ],
			Html::element( 'h3', [], $this->msg( 'oathauth-passkeys-header' )->text() ) .
			$passkeyAccordions .
			Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-authmethods__addform' ],
				$passkeyPlaceholder .
				$passkeyAddButton
			)
		);

		$output->addHTML( Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-vue-container' ],
			// If 2FA is enabled then put passkeys first, otherwise put 2FA first
			$keyAccordions === '' ?
				$authMethodsSection . $passkeySection :
				$passkeySection . $authMethodsSection
		) );
	}

	private function addModuleHTML( IModule $module ): void {
		if ( $this->isModuleRequested( $module ) ) {
			$this->addCustomContent( $module );
			return;
		}

		$panel = $this->getGenericContent( $module );
		if ( $this->isModuleEnabled( $module ) ) {
			$this->addCustomContent( $module, $panel );
		}

		$this->getOutput()->addHTML( (string)$panel );
	}

	/**
	 * Get the panel with generic content for a module
	 */
	private function getGenericContent( IModule $module ): PanelLayout {
		$modulePanel = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'padded' => true
		] );
		$headerLayout = new HorizontalLayout();

		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		if ( $this->shouldShowGenericButtons() ) {
			$enabled = $this->isModuleEnabled( $module );
			$urlParams = [
				'action' => $enabled ? static::ACTION_DISABLE : static::ACTION_ENABLE,
				'module' => $module->getName(),
			];
			$button = new ButtonWidget( [
				'label' => $this
					->msg( $enabled ? 'oathauth-disable-generic' : 'oathauth-enable-generic' )
					->text(),
				'href' => $this->getOutput()->getTitle()->getLocalURL( $urlParams )
			] );
			$headerLayout->addItems( [ $button ] );
		}
		$headerLayout->addItems( [ $label ] );

		$modulePanel->appendContent( $headerLayout );
		$modulePanel->appendContent( new HtmlSnippet(
			$module->getDescriptionMessage()->parseAsBlock()
		) );
		return $modulePanel;
	}

	/**
	 * Check max keys for a user and return true if max is exceeded
	 * @return bool
	 */
	private function exceedsKeyLimit(): bool {
		return count( $this->oathUser->getNonSpecialKeys() ) >= $this->getConfig()->get( 'OATHMaxKeysPerUser' );
	}

	private function addCustomContent( IModule $module, ?PanelLayout $panel = null ): void {
		if ( $this->action === self::ACTION_ENABLE && $this->exceedsKeyLimit() ) {
			throw new ErrorPageError(
				'oathauth-max-keys-exceeded',
				'oathauth-max-keys-exceeded-message',
				[ Message::numParam( $this->getConfig()->get( 'OATHMaxKeysPerUser' ) ) ]
			);
		}

		if ( $this->action === self::ACTION_DISABLE ) {
			$form = new DisableForm(
				$this->oathUser,
				$this->userRepo,
				$module,
				$this->getContext(),
				$this->moduleRegistry
			);
		} else {
			$form = $module->getManageForm(
				$this->action,
				$this->oathUser,
				$this->userRepo,
				$this->getContext(),
				$this->moduleRegistry
			);

			if ( $form === null ) {
				return;
			}
		}

		$form->setTitle( $this->getOutput()->getTitle() );
		$this->ensureRequiredFormFields( $form, $module );
		$form->setSubmitCallback( [ $form, 'onSubmit' ] );
		if ( $form->show( $panel ) ) {
			$form->onSuccess();

			// Only redirect for enabling a new key
			if ( $this->action === self::ACTION_ENABLE ) {
				$addedKeyName = $module->getDisplayName()->text();
				$this->getOutput()->redirect(
					$this->getPageTitle()->getLocalURL( [
						'addsuccess' => $addedKeyName
					] )
				);
				// Stop further rendering
				return;
			}
		}
	}

	private function shouldShowGenericButtons(): bool {
		return !$this->requestedModule instanceof IModule || !$this->isGenericAction();
	}

	private function isModuleRequested( ?IModule $module ): bool {
		return (
			$this->requestedModule instanceof IModule
			&& $module instanceof IModule
			&& $this->requestedModule->getName() === $module->getName()
		);
	}

	private function isModuleEnabled( IModule $module ): bool {
		return (bool)$this->oathUser->getKeysForModule( $module->getName() );
	}

	/**
	 * Verifies if the module can be enabled
	 */
	private function isModuleAvailable( IModule $module ): bool {
		return $module->getManageForm(
			static::ACTION_ENABLE,
			$this->oathUser,
			$this->userRepo,
			$this->getContext(),
			$this->moduleRegistry
		) !== null;
	}

	private function ensureRequiredFormFields( OATHAuthOOUIHTMLForm $form, IModule $module ): void {
		if ( !$form->hasField( 'module' ) ) {
			$form->addHiddenField( 'module', $module->getName() );
		}
		if ( !$form->hasField( 'action' ) ) {
			$form->addHiddenField( 'action', $this->action );
		}
	}

	/**
	 * When performing an action on a module (like enable/disable),
	 * the page should contain only the form for that action.
	 */
	private function clearPage(): void {
		if ( $this->isGenericAction() ) {
			$displayName = $this->requestedModule->getDisplayName();
			$pageTitleMessage = $this->action === self::ACTION_DISABLE ?
				$this->msg( 'oathauth-disable-page-title', $displayName ) :
				$this->msg( 'oathauth-enable-page-title', $displayName );
			$this->getOutput()->setPageTitleMsg( $pageTitleMessage );
		}

		$this->getOutput()->clearHTML();
		$this->getOutput()->addBacklinkSubtitle( $this->getOutput()->getTitle() );
	}

	/**
	 * The enable and disable actions are generic, and all modules must
	 * implement them (except special modules) while all other actions are module-specific.
	 */
	private function isGenericAction(): bool {
		return in_array( $this->action, [ static::ACTION_ENABLE, static::ACTION_DISABLE ] );
	}

	/**
	 * Returns special modules, which do not follow the constraints of standard modules.
	 * @return IModule[]
	 */
	private function getSpecialModules(): array {
		$modules = [];
		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			if ( $this->isModuleAvailable( $module ) && $module->isSpecial() ) {
				$modules[] = $module;
			}
		}
		return $modules;
	}

	/**
	 * Checks local groups to see what groups a user is in
	 * If any of the local groups are required, then the user is privileged
	 */
	private function isPrivilegedUser(): bool {
		$requiredGroups = $this->getConfig()->get( 'OATHRequiredForGroups' );
		if ( count( $requiredGroups ) === 0 ) {
			return false;
		}
		$userGroups = $this->userGroupManager->getUserGroups( $this->oathUser->getUser() );
		$a = array_intersect( $userGroups, $requiredGroups );
		return count( $a ) > 0;
	}

	/**
	 * Show the delete key warning/confirmation form using HTMLForm.
	 */
	private function showDeleteWarning(): void {
		$keyId = $this->getRequest()->getInt( 'keyId' );
		$keyToDelete = $this->oathUser->getKeyById( $keyId );
		if ( !$keyToDelete ) {
			throw new ErrorPageError(
				'oathauth-disable',
				'oathauth-remove-nosuchkey'
			);
		}

		if ( !$keyToDelete->supportsPasswordlessLogin() && !$this->canRemoveKeys() ) {
			throw new ErrorPageError(
				'oathauth-disable',
				'oathauth-remove-lastkey-required'
			);
		}

		$keyName = $this->getKeyNameAndDescription( $keyToDelete )['name'];
		$remainingKeys = array_filter(
			$this->oathUser->getNonSpecialKeys(),
			static fn ( $key ) => $key->getId() !== $keyId && !$key->supportsPasswordlessLogin()
		);
		$lastKey = count( $remainingKeys ) === 0;

		$this->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-delete-warning-header', $keyName ) );
		$this->getOutput()->addModuleStyles( 'ext.oath.manage.styles' );

		$formDescriptor = [];
		$warningDescription = $this->msg( 'oathauth-delete-warning' )->parse();

		if ( $lastKey ) {
			$formDescriptor['warning'] = [
				'type' => 'info',
				'raw' => true,
				'default' => Html::warningBox( $this->msg( 'oathauth-delete-warning-final' )->parse() ),
			];
			if ( $this->isPrivilegedUser() ) {
				$warningDescription = $this->msg( 'oathauth-delete-warning-final-privileged-user' )->parse();
			}
		}

		$formDescriptor['warning-description'] = [
			'type' => 'info',
			'raw' => true,
			'default' => $warningDescription,
		];

		if ( $lastKey ) {
			$formDescriptor['remove-confirm-box'] = [
				'type' => 'text',
				'label-message' => 'oathauth-delete-confirm-box',
				'required' => true,
				'validation-callback' => function ( $value ) {
					$expectedText = $this->msg( 'oathauth-authenticator-delete-text' )->text();
					return $value !== $expectedText
						? $this->msg( 'oathauth-delete-wrong-confirm-message' )->text()
						: true;
				},
			];
		}

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setTitle( $this->getPageTitle() );

		$form->addHiddenField( 'action', self::ACTION_DELETE );
		$form->addHiddenField( 'module', $keyToDelete->getModule() );
		$form->addHiddenField( 'keyId', (string)$keyId );

		$form->setSubmitDestructive();
		$form->setSubmitTextMsg( 'oathauth-authenticator-delete' );
		$form->showCancel();
		$form->setCancelTarget( $this->getPageTitle() );
		$form->setWrapperLegend( false );

		$form->setSubmitCallback( function ( $formData ) use ( $keyToDelete, $keyName, $lastKey ) {
			$this->userRepo->removeKey(
				$this->oathUser,
				$keyToDelete,
				$this->getRequest()->getIP(),
				true
			);

			if ( $lastKey ) {
				$this->userRepo->removeAll(
				$this->oathUser,
				$this->getRequest()->getIP(),
				true
				);

				if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
					$logEntry = new ManualLogEntry( 'oath', 'disable-self' );
					$logEntry->setPerformer( $this->getUser() );
					$logEntry->setTarget( $this->getUser()->getUserPage() );
					/** @var CheckUserInsert $checkUserInsert */
					$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
					$checkUserInsert->updateCheckUserData( $logEntry->getRecentChange() );
				}
			}

			$this->getOutput()->redirect( $this->getPageTitle()->getFullURL( [
				'deletesuccess' => $keyName
			] ) );

			return true;
		} );

		$this->getOutput()->addHTML( Html::openElement( 'div',
			[ 'class' => 'mw-special-OATHManage-delete-warning' ]
		) );

		$form->show();
		$this->getOutput()->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Adds HTML for all available special modules
	 */
	private function addSpecialModulesHTML(): void {
		if ( !$this->oathUser->getKeys() ) {
			return;
		}
		foreach ( $this->getSpecialModules() as $module ) {
			$this->addSpecialModuleHTML( $module );
		}
	}

	/**
	 * Adds special module HTML content
	 *
	 * Since special modules can vary in a number of ways from standard modules,
	 * there isn't much benefit to further abstracting/genericizing display logic
	 */
	private function addSpecialModuleHTML( IModule $module ): void {
		// only one special module type is currently supported
		if ( $module instanceof RecoveryCodes ) {
			$this->getRecoveryCodesHTML( $module );
		}
	}

	private function getRecoveryCodesHTML( RecoveryCodes $module ): void {
		$key = $module->ensureExistence( $this->oathUser );

		$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
		$this->getOutput()->addModules( 'ext.oath.recovery' );
		$codex = new Codex();
		$placeholderMessage = '';

		$this->setOutputJsConfigVars(
			$this->getRecoveryCodesForDisplay( $key )
		);

		$keyAccordion = $codex->accordion()
			->setTitle( $module->getDisplayName()->text() )
			->setDescription(
				$this->msg( 'oathauth-recoverycodes' )->text()
			)
			// TODO support outlined Accordions in Codex-PHP (T416645)
			->setAttributes( [ 'class' => 'cdx-accordion--separation-outline' ] )
			->setContentHtml( $codex->htmlSnippet()->setContent(
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__method-actions'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'module', $key->getModule() ) .
					Html::hidden( 'keyId', $key->getId() ) .
					$this->createRecoveryCodesCopyButton() .
					$this->createRecoveryCodesDownloadLink(
						$this->getRecoveryCodesForDisplay( $key )
					) .
					$codex->button()
						->setLabel( $this->msg(
							'oathauth-recoverycodes-create-label',
							$this->getConfig()->get( 'OATHRecoveryCodesCount' )
						)->parse() )
						->setType( 'submit' )
						->setAttributes( [ 'name' => 'action', 'value' => 'create-' . $module->getName() ] )
						->build()
						->getHtml()
				)
			)->build() );
		$accordionsHtml = $keyAccordion->build()->getHtml();

		$expiringCodes = array_filter(
			$key->getRecoveryCodes(),
			static fn ( RecoveryCode $code ) => !$code->isPermanent()
		);
		if ( $expiringCodes ) {
			$expiringCodesCount = count( $expiringCodes );
			$maxExpiry = '';
			foreach ( $expiringCodes as $code ) {
				$codeExpiry = $code->getExpiryTimestamp();
				if ( $codeExpiry && $codeExpiry > $maxExpiry ) {
					$maxExpiry = $codeExpiry;
				}
			}
			$temporaryCodesAccordion = $codex->accordion()
				->setTitle(
					$this->msg( 'oathauth-recoverycodes-temporary' )
						->numParams( $expiringCodesCount )
						->text()
				)
				->setDescription(
					$this->msg( 'oathauth-recoverycodes-temporary-desc' )
						->numParams( $expiringCodesCount )
						->dateTimeParams( $maxExpiry )
						->dateParams( $maxExpiry )
						->timeParams( $maxExpiry )
						->text()
				)
				// TODO support outlined Accordions in Codex-PHP (T416645)
				->setAttributes( [ 'class' => 'cdx-accordion--separation-outline' ] )
				->setContentHtml( $codex->htmlSnippet()->setContent(
					$this->msg( 'oathauth-recoverycodes-temporary-intro' )
						->numParams( $expiringCodesCount )
						->parse() .
					Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__method-actions'
					],
						Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
						Html::hidden( 'module', $key->getModule() ) .
						Html::hidden( 'keyId', $key->getId() ) .
						$codex->button()
							->setLabel( $this->msg(
								'oathauth-recoverycodes-temporary-remove-label'
							)->parse() )
							->setAction( 'destructive' )
							->setWeight( 'primary' )
							->setType( 'submit' )
							->setAttributes( [
								'name' => 'action',
								'value' => RecoveryCodes::ACTION_REMOVE_TEMPORARY
							] )
							->build()
							->getHtml()
					)
				)->build() );
			$accordionsHtml .= $temporaryCodesAccordion->build()->getHtml();
		}

		$authmethodsClasses = [
			'mw-special-OATHManage-authmethods'
		];
		if ( !$this->oathUser->getKeys() ) {
			$authmethodsClasses[] = 'mw-special-OATHManage-authmethods--no-keys';
		}

		$this->getOutput()->addHTML(
			Html::rawElement( 'div', [ 'class' => $authmethodsClasses ],
				Html::element( 'h3', [], $this->msg( 'oathauth-' . $module->getName() . '-header' )->text() ) .
				$accordionsHtml .
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__addform'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'action', 'enable' ) .
					$placeholderMessage
				)
			)
		);
	}

	private function hasSpecialModules(): bool {
		return $this->getSpecialModules() !== [];
	}
}
