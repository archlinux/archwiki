<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn as WebAuthnModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class provides an endpoint for all WebAuthn actions.
 */
class ApiWebAuthn extends ApiBase {

	private const ACTION_GET_AUTH_INFO = 'getAuthInfo';
	private const ACTION_GET_REGISTER_INFO = 'getRegisterInfo';
	private const ACTION_REGISTER = 'register';

	/**
	 * Array of all functions that are allowed to be called.
	 * Each key must have the appropriate configuration that
	 * defines user requirements for the action.
	 */
	private const REGISTERED_FUNCTIONS = [
		self::ACTION_GET_AUTH_INFO => [
			'permissions' => [],
			'mustBeLoggedIn' => false,
		],
		self::ACTION_GET_REGISTER_INFO => [
			'permissions' => [ 'oathauth-enable' ],
			'mustBeLoggedIn' => true,
		],
		self::ACTION_REGISTER => [
			'permissions' => [ 'oathauth-enable' ],
			'mustBeLoggedIn' => true,
			'loginSecurityLevel' => 'OATHManage'
		],
	];

	public function __construct(
		ApiMain $main,
		string $moduleName,
		private readonly AuthManager $authManager,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHUserRepository $userRepo,
		private readonly WebAuthnAuthenticator $authenticator,
	) {
		parent::__construct( $main, $moduleName );
	}

	public function execute() {
		$func = $this->getParameter( 'func' );

		$this->checkPermissions( $func );
		$this->checkModule();

		$result = match ( $func ) {
			self::ACTION_GET_REGISTER_INFO => $this->getRegisterInfo(),
			self::ACTION_GET_AUTH_INFO => $this->getAuthInfo(),
			self::ACTION_REGISTER => $this->register(),
		};

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/** @inheritDoc */
	public function needsToken() {
		return $this->getRequest()->getVal( 'func' ) === self::ACTION_REGISTER ? 'csrf' : false;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return $this->getRequest()->getVal( 'func' ) === self::ACTION_REGISTER;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return $this->getRequest()->getVal( 'func' ) === self::ACTION_REGISTER;
	}

	/** @inheritDoc */
	protected function getSummaryMessage() {
		return "apihelp-oathauth-webauthn-summary";
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'func' => [
				ParamValidator::PARAM_TYPE => array_keys( self::REGISTERED_FUNCTIONS ),
				ParamValidator::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-oathauth-webauthn-param-func',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'getAuthInfo' => 'apihelp-oathauth-webauthn-paramvalue-func-getauthinfo',
					'getRegisterInfo' => 'apihelp-oathauth-webauthn-paramvalue-func-getregisterinfo',
					'register' => 'apihelp-oathauth-webauthn-paramvalue-func-register',
				],
			],
			'passkeyMode' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ApiBase::PARAM_HELP_MSG => 'apihelp-oathauth-webauthn-param-passkeymode',
			],
			'credential' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ApiBase::PARAM_HELP_MSG => 'apihelp-oathauth-webauthn-param-credential',
			],
			'friendlyname' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ApiBase::PARAM_HELP_MSG => 'apihelp-oathauth-webauthn-param-friendlyname',
			],
		];
	}

	private function checkPermissions( string $func ): void {
		$functionConfig = self::REGISTERED_FUNCTIONS[$func];

		$mustBeLoggedIn = $functionConfig['mustBeLoggedIn'];
		if ( $mustBeLoggedIn === true ) {
			$user = $this->getUser();
			if ( !$user->isNamed() ) {
				$this->dieWithError( [ 'apierror-mustbeloggedin', $this->msg( 'action-oathauth-enable' ) ] );
			}
		}

		$funcPermissions = $functionConfig['permissions'];
		if ( $funcPermissions ) {
			$this->checkUserRightsAny( $funcPermissions );
		}

		if ( isset( $functionConfig[ 'loginSecurityLevel' ] ) ) {
			$status = $this->authManager->securitySensitiveOperationStatus( $functionConfig[ 'loginSecurityLevel' ] );
			if ( $status !== AuthManager::SEC_OK ) {
				$this->dieWithError( 'apierror-oathauth-webauthn-reauthenticate' );
			}
		}
	}

	private function checkModule() {
		$module = $this->moduleRegistry->getModuleByKey( WebAuthnModule::MODULE_ID );
		if ( !( $module instanceof WebAuthnModule ) ) {
			$this->dieWithError( 'apierror-oathauth-webauthn-module-missing' );
		}
	}

	private function getAuthInfo(): array {
		$oathUser = $this->getOATHUser();
		$startAuthResult = $this->authenticator->startAuthentication( $oathUser );
		if ( $startAuthResult->isGood() ) {
			return [
				'auth_info' => $startAuthResult->getValue()['json']
			];
		}
		$this->dieWithError( $startAuthResult->getMessage() );
	}

	private function getRegisterInfo(): array {
		$oathUser = $this->getOATHUser();
		$startRegResult = $this->authenticator->startRegistration(
			$oathUser,
			(bool)$this->getParameter( 'passkeyMode' )
		);
		if ( $startRegResult->isGood() ) {
			return [
				'register_info' => $startRegResult->getValue()['json']
			];
		}
		$this->dieWithError( $startRegResult->getMessage() );
	}

	private function register(): array {
		$credentialJson = $this->getParameter( 'credential' );

		if ( !$credentialJson ) {
			$this->dieWithError( 'apierror-oathauth-webauthn-missing-credential' );
		}

		$result = $this->authenticator->continueRegistration(
			$this->getOATHUser(),
			$credentialJson,
			$this->getParameter( 'friendlyname' ) ?? '',
			(bool)$this->getParameter( 'passkeyMode' )
		);

		if ( !$result->isGood() ) {
			$this->dieWithError( $result->getMessage() );
		}

		return [ 'success' => true ];
	}

	private function getOATHUser(): OATHUser {
		return $this->userRepo->findByUser( $this->getUser() );
	}
}
