<?php

namespace MediaWiki\Extension\OATHAuth\Auth;

use BadMethodCallException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWiki\Status\Status;
use MediaWiki\User\UserFactory;

class PasskeyPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	public function __construct(
		private readonly WebAuthnAuthenticator $webAuthnAuthenticator,
		private readonly OATHAuthLogger $oathLogger,
		private readonly UserFactory $userFactory
	) {
	}

	public const SUCCESS_KEY = 'webauthn-passkey-successful';

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if (
			!$this->config->get( 'OATHPasswordlessLogin' ) ||
			$action !== AuthManager::ACTION_LOGIN
		) {
			return [];
		}

		$authStatus = $this->webAuthnAuthenticator->startPasswordlessAuthentication();
		if ( !$authStatus->isGood() ) {
			return [];
		}

		// TODO make AuthenticationRequest::getUsernameFromRequests() recognize the username
		// from this, so that ThrottlePreAuthenticationProvider will apply the password throttle
		return [ new WebAuthnAuthenticationRequest( $authStatus->getValue()['json'], false ) ];
	}

	/** @inheritDoc */
	public function beginPrimaryAuthentication( array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, WebAuthnAuthenticationRequest::class );
		if ( !$req || $req->credential === '' ) {
			return AuthenticationResponse::newAbstain();
		}

		$oathUser = $this->webAuthnAuthenticator->determineUser( $req->credential );
		if ( !$oathUser ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'oathauth-webauthn-error-passkey-verification-failed' )
			);
		}
		$user = $this->userFactory->newFromUserIdentity( $oathUser->getUser() );

		// Check for (and increment) rate limiter before doing the auth
		if ( $user->pingLimiter( 'badoath' ) ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'oathauth-throttled' )
			);
		}

		$authResult = $this->webAuthnAuthenticator->continueAuthentication( $oathUser, $req->credential );
		if ( $authResult->isGood() ) {
			$this->logger->info( 'OATHAuth user {user} completed passwordless login from {clientip}', [
				'user'     => $user->getName(),
				'clientip' => $user->getRequest()->getIP(),
			] );
			$this->oathLogger->logSuccessfulVerification( $user );
			// Record the fact that the user logged in with a passkey, so that
			// our SecondaryAuthenticationProvider will skip the 2FA step
			$this->manager->setAuthenticationSessionData( self::SUCCESS_KEY, true );
			return AuthenticationResponse::newPass( $user->getName() );
		}

		$this->logger->info( 'OATHAuth user {user} failed passwordless login from {clientip}', [
			'user'     => $user->getName(),
			'clientip' => $user->getRequest()->getIP(),
		] );

		$this->oathLogger->logFailedVerification( $user );

		$messages = $authResult->getMessages();
		// Return the first error from the authenticator, if there is any
		$failureMessage = $messages ? wfMessage( $messages[0] ) :
			wfMessage( 'oathauth-webauthn-error-passkey-verification-failed' );

		return AuthenticationResponse::newFail( $failureMessage );
	}

	/** @inheritDoc */
	public function accountCreationType() {
		return self::TYPE_NONE;
	}

	/** @inheritDoc */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new BadMethodCallException( 'Shouldn\'t call this when accountCreationType() is NONE' );
	}

	/** @inheritDoc */
	public function testUserExists( $username, $flags = \Wikimedia\Rdbms\IDBAccessObject::READ_NORMAL ) {
		// We rely on other primary authentication providers to manage user existence
		return false;
	}

	/** @inheritDoc */
	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
		// Not supported
		return Status::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		// Do nothing; passkeys can't be managed this way
	}
}
