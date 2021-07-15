<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use Config;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use SpecialPage;
use Title;
use User;

class HookHandler implements
	AuthChangeFormFieldsHook,
	GetPreferencesHook,
	getUserPermissionsErrorsHook
{
	/**
	 * @var OATHUserRepository
	 */
	private $userRepo;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param OATHUserRepository $userRepo
	 * @param PermissionManager $permissionManager
	 * @param Config $config
	 */
	public function __construct( $userRepo, $permissionManager, $config ) {
		$this->userRepo = $userRepo;
		$this->permissionManager = $permissionManager;
		$this->config = $config;
	}

	/**
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 *
	 * @return bool
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( !isset( $fieldInfo['OATHToken'] ) ) {
			return true;
		}

		$formDescriptor['OATHToken'] += [
			'cssClass' => 'loginText',
			'id' => 'wpOATHToken',
			'size' => 20,
			'dir' => 'ltr',
			'autofocus' => true,
			'persistent' => false,
			'autocomplete' => false,
			'spellcheck' => false,
		];
		return true;
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 *
	 * @return bool
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$oathUser = $this->userRepo->findByUser( $user );

		// If there is no existing module in user, and the user is not allowed to enable it,
		// we have nothing to show.

		if (
			$oathUser->getModule() === null &&
			!$this->permissionManager->userHasRight( $user, 'oathauth-enable' )
		) {
			return true;
		}

		$module = $oathUser->getModule();

		$moduleLabel = $module === null ?
			wfMessage( 'oauthauth-ui-no-module' ) :
			$module->getDisplayName();

		$manageButton = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => wfMessage( 'oathauth-ui-manage' )->text()
		] );

		$currentModuleLabel = new LabelWidget( [
			'label' => $moduleLabel->text()
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

		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param string &$result
	 *
	 * @return bool
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( !$this->config->has( 'OATHExclusiveRights' ) ) {
			return true;
		}

		// TODO: Get the session from somewhere more... sane?
		$session = $user->getRequest()->getSession();
		if (
			!(bool)$session->get( OATHAuth::AUTHENTICATED_OVER_2FA, false ) &&
			in_array( $action, $this->config->get( 'OATHExclusiveRights' ) )
		) {
			$result = 'oathauth-action-exclusive-to-2fa';
			return false;
		}
		return true;
	}
}
