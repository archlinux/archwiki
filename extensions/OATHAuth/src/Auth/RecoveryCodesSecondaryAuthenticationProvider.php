<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;

/**
 * AuthManager secondary authentication provider for Recovery Codes second-factor authentication.
 *
 * After a successful primary authentication, requests a recovery code from the user.
 *
 * @see AuthManager
 */
class RecoveryCodesSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	public function __construct(
		private readonly RecoveryCodes $module,
		private readonly OATHUserRepository $userRepository,
		private readonly OATHAuthLogger $oathLogger,
	) {
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ): array {
		// don't ask for anything initially, so the second factor is on a separate screen
		return [];
	}

	/**
	 * If the user has a recovery code module enabled, request a second factor.
	 *
	 * @inheritDoc
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ): AuthenticationResponse {
		$authUser = $this->userRepository->findByUser( $user );

		if ( !$this->module->isEnabled( $authUser ) ) {
			return AuthenticationResponse::newAbstain();
		}

		return AuthenticationResponse::newUI( [ new RecoveryCodesAuthenticationRequest() ] );
	}

	/** @inheritDoc */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		/** @var RecoveryCodesAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, RecoveryCodesAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newUI( [ new RecoveryCodesAuthenticationRequest() ],
				wfMessage( 'oathauth-recovery-code-login-failed' ), 'error' );
		}

		// Check for (and increment) rate limiter before doing the auth
		if ( $user->pingLimiter( 'badoath' ) ) {
			return AuthenticationResponse::newUI(
				[ new RecoveryCodesAuthenticationRequest() ],
				new Message( 'oathauth-throttled' ),
				'error'
			);
		}

		$authUser = $this->userRepository->findByUser( $user );
		$recoveryCode = $request->RecoveryCode;

		if ( $this->module->verify( $authUser, [ 'recoverycode' => $recoveryCode ] ) ) {
			return AuthenticationResponse::newPass();
		}

		$this->logger->info( 'OATHAuth user {user} failed recovery code from {clientip}', [
			'user'     => $user->getName(),
			'clientip' => $user->getRequest()->getIP(),
		] );

		$this->oathLogger->logFailedVerification( $user );

		return AuthenticationResponse::newUI(
			[ new RecoveryCodesAuthenticationRequest() ],
			wfMessage( 'oathauth-recovery-code-login-failed' ),
			'error'
		);
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
