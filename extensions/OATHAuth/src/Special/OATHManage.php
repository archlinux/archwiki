<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\OATHAuth\Special;

use ErrorPageError;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\OATHAuth\HTMLForm\DisableForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\SpecialPage\SpecialPage;
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
	protected bool $nonSpecialEnabledKeys;

	/**
	 * @var string
	 */
	protected $action;

	protected ?IModule $requestedModule;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly AuthManager $authManager,
	) {
		// messages used: oathmanage (display "name" on Special:SpecialPages),
		// right-oathauth-enable, action-oathauth-enable
		parent::__construct( 'OATHManage', 'oathauth-enable' );
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
	public function getDescription() {
		return $this->msg( 'accountsecurity' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->oathUser = $this->userRepo->findByUser( $this->getUser() );
		$this->nonSpecialEnabledKeys = $this->oathUser->userHasNonSpecialEnabledKeys();

		$this->getOutput()->enableOOUI();
		$this->getOutput()->disallowUserJs();
		$this->setAction();
		$this->setModule();

		parent::execute( $subPage );

		if ( $this->action === self::ACTION_DELETE ) {
			if (
				$this->getRequest()->wasPosted() &&
				$this->getContext()->getCsrfTokenSet()->matchTokenField()
			) {
				if ( !$this->isValidFinalKeyDeletion() ) {
					$this->showDeleteWarning( true );
					return;
				}
				// Delete the key, then redirect to the main view with a success message
				$deletedKey = $this->deleteKey();
				$deletedKeyName = $this->getKeyNameAndDescription( $deletedKey )['name'];
				$this->maybeDeleteRecoveryCodes();
				$this->getOutput()->redirect( $this->getPageTitle()->getFullURL( [
					'deletesuccess' => $deletedKeyName
				] ) );
				return;
			} elseif ( $this->getRequest()->getBool( 'warn' ) ) {
				$this->showDeleteWarning( false );
				return;
			}
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

	/**
	 * @throws PermissionsError
	 * @throws UserNotLoggedIn
	 */
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

		if ( !$this->oathUser->isTwoFactorAuthEnabled() && !$canEnable ) {
			// No enabled module and cannot enable - nothing to do
			$this->displayRestrictionError();
		}
	}

	private function isValidFinalKeyDeletion(): bool {
		$isNormalDelete = $this->getRequest()->getBool( 'normalDelete' );
		if ( $isNormalDelete ) {
			return true;
		}

		$expectedText = $this->msg( 'oathauth-authenticator-delete-text' )->text();
		$actualText = $this->getRequest()->getText( 'remove-confirm-box' );

		return $actualText === $expectedText;
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
	 * @param IAuthKey $key
	 * @return array{name:string, description?:string, timestamp: ?string}
	 */
	private function getKeyNameAndDescription( IAuthKey $key ): array {
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

		// If the key has a non-empty name, use that, and set the description to the module name
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

	private function displayNewUI(): void {
		$this->getOutput()->addModuleStyles( 'ext.oath.manage.styles' );
		// TODO JS enhancement for rename and delete buttons
		$codex = new Codex();

		// Delete success message, if applicable
		$deletedKeyName = $this->getRequest()->getVal( 'deletesuccess' );
		if ( $deletedKeyName !== null ) {
			$this->getOutput()->addHTML( Html::successBox(
				$this->msg( 'oathauth-delete-success', $deletedKeyName )->parse()
			) );
		}

		// Add success message for newly enabled key
		$addedKeyName = $this->getRequest()->getVal( 'addsuccess' );
		if ( $addedKeyName !== null ) {
			$this->getOutput()->addHTML(
				Html::successBox(
					$this->msg( 'oathauth-enable-success', $addedKeyName )->parse()
				)
			);
		}

		// Password section
		if ( $this->authManager->allowsAuthenticationDataChange(
			new PasswordAuthenticationRequest(), false )->isGood()
		) {
			$this->getOutput()->addHTML(
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
		$keyAccordions = '';
		$placeholderMessage = '';
		foreach ( $this->oathUser->getKeys() as $key ) {
			if ( $this->moduleRegistry->getModuleByKey( $key->getModule() )->isSpecial() ) {
				continue;
			}

			// TODO use outlined Accordions once these are available in Codex
			$keyData = $this->getKeyNameAndDescription( $key );
			$keyAccordion = $codex->accordion();

			$keyAccordion->setTitle( $keyData['name'] );

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
							->setType( 'submit' )
							->setAttributes( [ 'name' => 'action', 'value' => self::ACTION_DELETE ] )
							->build()
							->getHtml()
					)
				)->build() );
			$keyAccordions .= $keyAccordion->build()->getHtml();
		}
		if ( !$this->oathUser->getKeys() ) {
			// User has no keys, display the placeholder message instead
			$placeholderMessage = Html::element( 'p',
				[ 'class' => 'mw-special-OATHManage-authmethods__placeholder' ],
				$this->msg( 'oathauth-authenticator-placeholder' )->text()
			);
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

		$authmethodsClasses = [
			'mw-special-OATHManage-authmethods'
		];
		if ( !$this->oathUser->getKeys() ) {
			$authmethodsClasses[] = 'mw-special-OATHManage-authmethods--no-keys';
		}

		$this->getOutput()->addHTML(
			Html::rawElement( 'div', [ 'class' => $authmethodsClasses ],
				Html::element( 'h3', [], $this->msg( 'oathauth-authenticator-header' )->text() ) .
				$keyAccordions .
				Html::rawElement( 'form', [
						'action' => wfScript(),
						'class' => 'mw-special-OATHManage-authmethods__addform'
					],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'action', 'enable' ) .
					$placeholderMessage .
					$moduleButtons
				)
			)
		);
	}

	private function addEnabledHTML(): void {
		$enabledModules = $this->getEnabledModules();
		$this->addHeading( $this->msg( 'oathauth-ui-enabled-module', count( $enabledModules ) ) );
		foreach ( $enabledModules as $module ) {
			$this->addModuleHTML( $module );
		}
	}

	private function addAlternativesHTML(): void {
		$this->addHeading( $this->msg( 'oathauth-ui-not-enabled-modules' ) );
		$this->addInactiveHTML();
	}

	private function nothingEnabled(): void {
		$this->addHeading( $this->msg( 'oathauth-ui-available-modules' ) );
		$this->addInactiveHTML();
	}

	private function addInactiveHTML(): void {
		foreach ( $this->getAvailableModules() as $module ) {
			$this->addModuleHTML( $module );
		}
	}

	private function addGeneralHelp(): void {
		$this->getOutput()->addHTML( $this->msg(
			'oathauth-ui-general-help'
		)->parseAsBlock() );
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
	 * Get the panel with special content for a module. This creates a very
	 * basic layout, moreso even than getGenericContent, and assumes necessary
	 * custom elements will be handled exclusively in addCustomContent() and
	 * getManageForm().
	 */
	private function getSpecialContent( IModule $module ): PanelLayout {
		$modulePanel = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'padded' => true
		] );
		$headerLayout = new HorizontalLayout();
		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		$headerLayout->addItems( [ $label ] );
		$modulePanel->appendContent( $headerLayout );
		$modulePanel->appendContent( new HtmlSnippet(
			$module->getDescriptionMessage()->parseAsBlock()
		) );
		return $modulePanel;
	}

	private function addCustomContent( IModule $module, ?PanelLayout $panel = null ): void {
		if ( $this->action === self::ACTION_DISABLE ) {
			$form = new DisableForm( $this->oathUser, $this->userRepo, $module, $this->getContext() );
		} else {
			$form = $module->getManageForm(
				$this->action,
				$this->oathUser,
				$this->userRepo,
				$this->getContext()
			);
		}
		if ( $form === null || !$this->isValidFormType( $form ) ) {
			return;
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

	private function deleteKey(): IAuthKey {
		$keyToDelete = $this->oathUser->getKeyById( $this->getRequest()->getInt( 'keyId' ) );
		if ( !$keyToDelete ) {
			throw new ErrorPageError(
				'oathauth-disable',
				'oathauth-remove-nosuchkey'
			);
		}
		$this->userRepo->removeKey( $this->oathUser, $keyToDelete, $this->getRequest()->getIP(), true );
		return $keyToDelete;
	}

	/**
	 * function to remove recovery codes as an auth factor if the user
	 * has removed their final 2fa key. This functionality also exists
	 * within the older DisableForm class)
	 */
	public function maybeDeleteRecoveryCodes(): bool {
		// delete recovery codes if this is the last 2fa method for a user
		if ( $this->oathUser->userHasNonSpecialEnabledKeys() ) {
			return false;
		}

		$this->userRepo->removeAllOfType(
			$this->oathUser,
			RecoveryCodes::MODULE_NAME,
			$this->getRequest()->getIP(),
			true
		);

		return true;
	}

	private function addHeading( Message $message ): void {
		$this->getOutput()->addHTML( Html::element( 'h2', [], $message->text() ) );
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
	 * Verifies if the module is available to be enabled
	 *
	 * @param IModule $module
	 * @return bool
	 */
	private function isModuleAvailable( IModule $module ): bool {
		$form = $module->getManageForm(
			static::ACTION_ENABLE,
			$this->oathUser,
			$this->userRepo,
			$this->getContext()
		);
		if ( $form === '' ) {
			return false;
		}
		return true;
	}

	/**
	 * Verifies if the given form instance fulfills the required conditions
	 *
	 * @param mixed $form
	 * @return bool
	 */
	private function isValidFormType( $form ): bool {
		if ( !( $form instanceof HTMLForm ) ) {
			return false;
		}
		$implements = class_implements( $form );
		if ( !isset( $implements[IManageForm::class] ) ) {
			return false;
		}

		return true;
	}

	private function ensureRequiredFormFields( IManageForm $form, IModule $module ): void {
		if ( !$form->hasField( 'module' ) ) {
			$form->addHiddenField( 'module', $module->getName() );
		}
		if ( !$form->hasField( 'action' ) ) {
			$form->addHiddenField( 'action', $this->action );
		}
	}

	/**
	 * When performing an action on a module (like enable/disable),
	 * page should contain only the form for that action.
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
	 * Returns modules currently enabled by the user.
	 * @return IModule[]
	 */
	private function getEnabledModules(): array {
		$modules = [];
		$moduleNames = array_unique(
			array_map(
				static fn ( IAuthKey $key ) => $key->getModule(),
				$this->oathUser->getKeys(),
			)
		);
		foreach ( $moduleNames as $moduleName ) {
			if ( !$this->moduleRegistry->getModuleByKey( $moduleName )->isSpecial() ) {
				$modules[] = $this->moduleRegistry->getModuleByKey( $moduleName );
			}
		}
		return $modules;
	}

	/**
	 * Returns modules which are not enabled by the user, but the user would be able to enable them.
	 * @return IModule[]
	 */
	private function getAvailableModules(): array {
		$modules = [];
		foreach ( $this->moduleRegistry->getAllModules() as $module ) {
			if (
				!$this->isModuleEnabled( $module )
				&& $this->isModuleAvailable( $module )
				&& !$module->isSpecial()
			) {
					$modules[] = $module;
			}
		}
		return $modules;
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

	private function hasAlternativeModules(): bool {
		return (bool)$this->getAvailableModules();
	}

	private function showDeleteWarning( bool $showWrongConfirmMessage ) {
		$keyId = $this->getRequest()->getInt( 'keyId' );
		$keyToDelete = $this->oathUser->getKeyById( $keyId );
		if ( !$keyToDelete ) {
			throw new ErrorPageError(
				'oathauth-disable',
				'oathauth-remove-nosuchkey'
			);
		}

		$keyName = $this->getKeyNameAndDescription( $keyToDelete )['name'];
		$remainingKeys = array_filter(
			$this->oathUser->getNonSpecialKeys(),
			static fn ( $key ) => $key->getId() !== $keyId
		);
		$lastKey = count( $remainingKeys ) === 0;

		$this->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-delete-warning-header', $keyName ) );
		$codex = new Codex();

		$warningMessage = $showWrongConfirmMessage ?
				$this->msg( 'oathauth-delete-wrong-confirm-message' )->escaped() :
				$this->msg( 'oathauth-delete-warning-final' )->escaped();

		$deleteWarningHTML =
			( $lastKey ? Html::warningBox( $warningMessage ) : '' ) .
			Html::element( 'p', [], $this->msg( 'oathauth-delete-warning' )->text() ) .
			Html::rawElement( 'form', [ 'action' => wfScript(), 'method' => 'POST' ],
				( $lastKey ? $codex->Field()
					->setLabel( $codex->Label()
						->setLabelText( $this->msg( 'oathauth-delete-confirm-box' )->escaped() )
						->setInputId( 'remove-confirm-box' )
						->build()
					)
					->setFields( [
						$codex->TextInput()
							->setName( 'remove-confirm-box' )
							->setInputId( 'remove-confirm-box' )
							->build()
							->getHtml()
					] )
					->build()
					->getHtml()
				: '' ) .
				Html::rawElement( 'div', [ 'class' => 'mw-special-OATHManage-delete-warning__actions' ],
					Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBkey() ) .
					Html::hidden( 'module', $keyToDelete->getModule() ) .
					Html::hidden( 'keyId', $keyId ) .
					( !$lastKey ? Html::hidden( 'normalDelete', true ) : '' ) .
					Html::hidden(
						CsrfTokenSet::DEFAULT_FIELD_NAME,
						$this->getContext()->getCsrfTokenSet()->getToken()
					) .
					$codex->button()
						->setLabel( $this->msg( 'oathauth-authenticator-delete' )->text() )
						->setAction( 'destructive' )
						->setWeight( 'primary' )
						->setType( 'submit' )
						->setAttributes( [ 'name' => 'action', 'value' => self::ACTION_DELETE ] )
						->build()
						->getHtml() .
					Html::linkButton( $this->msg( 'cancel' )->text(), [
						'href' => $this->getPageTitle()->getLinkURL(),
						'class' => 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled',
						'role' => 'button'
					] )
				)
			);

		$this->getOutput()->addHTML( Html::rawElement( 'div',
			[ 'class' => 'mw-special-OATHManage-delete-warning' ],
			$deleteWarningHTML
		) );
		$this->getOutput()->addModuleStyles( 'ext.oath.manage.styles' );
	}

	/**
	 * Adds html for all available special modules
	 *
	 * @return void|null
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
	 * Adds special module html content
	 *
	 * Since special modules can vary in a number of ways from standard modules,
	 * there isn't much benefit to further abstracting/genericizing display logic
	 */
	private function addSpecialModuleHTML( IModule $module ): void {
		// only one special module type is currently supported
		if ( $module->getName() === RecoveryCodes::MODULE_NAME ) {
			$this->getRecoveryCodesHTML( $module );
		}
	}

	/** @return void|null */
	private function getRecoveryCodesHTML( IModule $module ): void {
		$keys = $this->oathUser->getKeysForModule( $module->getName() );
		if ( count( $keys ) === 0 ) {
			// This path should only be possible if a user had an existing TOTP or WebAuthn
			// key, pre multi-module support. So let's create an empty Recovery Code Keys
			// for them, since they will otherwise not yet exist.
			RecoveryCodeKeys::maybeCreateOrUpdateRecoveryCodeKeys( $this->oathUser );
			$keys = $this->oathUser->getKeysForModule( $module->getName() );
		}

		$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
		$this->getOutput()->addModules( 'ext.oath.recovery' );
		$codex = new Codex();
		$keyAccordions = '';
		$placeholderMessage = '';

		foreach ( $keys as $key ) {
			/** @var RecoveryCodeKeys $key */
			'@phan-var RecoveryCodeKeys $key';
			$this->setOutputJsConfigVars(
				array_map(
					[ $this, 'tokenFormatterFunction' ],
					$key->getRecoveryCodeKeys()
				)
			);

			// TODO: use outlined Accordions once these are available in Codex
			$keyData = $this->getKeyNameAndDescription( $key );
			$keyAccordion = $codex->accordion()
				->setTitle( $keyData['name'] );
			$keyAccordion->setDescription(
				$this->msg( 'oathauth-recoverycodes' )->text()
			);
			$keyAccordion
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
							$key->getRecoveryCodeKeys()
						) .
						$codex->button()
							->setLabel( $this->msg(
								'oathauth-recoverycodes-create-label',
								$this->getConfig()->get( 'OATHRecoveryCodesCount' )
							) )
							->setType( 'submit' )
							->setAttributes( [ 'name' => 'action', 'value' => 'create-' . $module->getName() ] )
							->build()
							->getHtml()
					)
				)->build() );
			$keyAccordions .= $keyAccordion->build()->getHtml();
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
				$keyAccordions .
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
