<?php

namespace MediaWiki\Extension\OATHAuth\Auth;

use LogicException;
use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

class SecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	/**
	 * @param string $action
	 * @param array $options
	 *
	 * @return array
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/**
	 * @param User $user
	 * @param User $creator
	 * @param array|AuthenticationRequest[] $reqs
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * If the user has enabled two-factor authentication, request a second factor.
	 *
	 * @param User $user
	 * @param array $reqs
	 *
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$authUser = OATHAuthServices::getInstance()->getUserRepository()->findByUser( $user );

		if ( !$authUser->isTwoFactorAuthEnabled() ) {
			return AuthenticationResponse::newAbstain();
		}

		$module = $this->getModule( $authUser, $reqs );
		if ( !$module ) {
			throw new LogicException( 'Not possible' );
		}
		$response = $this->getProviderForModule( $module )->beginSecondaryAuthentication( $user, [] );

		// Include information about used module in request so that the correct
		// provider can be used when continuing
		$this->maybeAddSelectAuthenticationRequest( $authUser, $response, $module );

		return $response;
	}

	/**
	 * @inheritDoc
	 */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		$authUser = OATHAuthServices::getInstance()->getUserRepository()->findByUser( $user );

		$module = $this->getModule( $authUser, $reqs );
		if ( !$module ) {
			return AuthenticationResponse::newFail( wfMessage( 'oathauth-invalidrequest' ) );
		}
		$provider = $this->getProviderForModule( $module );

		/** @var TwoFactorModuleSelectAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, TwoFactorModuleSelectAuthenticationRequest::class );
		if ( $request && $request->newModule ) {
			// The user is switching modules, restart
			$response = $provider->beginSecondaryAuthentication( $user, [] );
		} else {
			$response = $provider->continueSecondaryAuthentication( $user, $reqs );
		}

		if ( $response->status === AuthenticationResponse::PASS ) {
			$user->getRequest()->getSession()->set( OATHAuth::AUTHENTICATED_OVER_2FA, true );
		}

		$this->maybeAddSelectAuthenticationRequest( $authUser, $response, $module );
		return $response;
	}

	private function getModule( OATHUser $authUser, array $reqs ): ?string {
		return $this->getModuleFromRequest( $authUser, $reqs )
			?? $this->getDefaultModule( $authUser );
	}

	/**
	 * Return the ID of the module corresponding to the 2FA type option the user selected in the
	 * login form (or null if not selected / invalid).
	 * @param OATHUser $authUser
	 * @param AuthenticationRequest[] $reqs
	 * @return string|null
	 */
	private function getModuleFromRequest( OATHUser $authUser, array $reqs ): ?string {
		/** @var TwoFactorModuleSelectAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, TwoFactorModuleSelectAuthenticationRequest::class );
		if ( !$request ) {
			return null;
		}
		$module = $request->newModule ?: $request->currentModule;

		// Validate that the specified module ID is valid
		// and enabled for the user.
		foreach ( $authUser->getKeys() as $key ) {
			if ( $key->getModule() === $module ) {
				return $module;
			}
		}

		return null;
	}

	private function getDefaultModule( OATHUser $authUser ): ?string {
		// HACK: If the request came from the clientlogin API, and the user has both
		// TOTP and other modules enabled, only present TOTP. This is needed to avoid
		// breaking the Wikipedia mobile apps until they can handle users with multiple
		// modules enabled. (T399654)
		if ( defined( 'MW_API' ) && $authUser->getKeysForModule( 'totp' ) ) {
			return 'totp';
		}

		// Use the highest-priority module the user has
		foreach ( $this->config->get( 'OATHPrioritizedModules' ) as $module ) {
			if ( $authUser->getKeysForModule( $module ) ) {
				return $module;
			}
		}

		// Return the first key from the db if the user doesn't have any of the prioritized modules
		return $authUser->getKeys() ? $authUser->getKeys()[0]->getModule() : null;
	}

	private function getProviderForModule( string $moduleId ): AbstractSecondaryAuthenticationProvider {
		$module = OATHAuthServices::getInstance()
			->getModuleRegistry()
			->getModuleByKey( $moduleId );

		$provider = $module->getSecondaryAuthProvider();
		$services = MediaWikiServices::getInstance();
		$provider->init(
			$this->logger,
			$this->manager,
			$services->getHookContainer(),
			$this->config,
			$services->getUserNameUtils()
		);
		return $provider;
	}

	private function maybeAddSelectAuthenticationRequest(
		OATHUser $authUser,
		AuthenticationResponse $response,
		string $currentModule
	): void {
		if ( !in_array( $response->status, [ AuthenticationResponse::UI, AuthenticationResponse::REDIRECT ] ) ) {
			return;
		}

		$allowedModules = [];
		$moduleRegistry = OATHAuthServices::getInstance( MediaWikiServices::getInstance() )
			->getModuleRegistry();
		foreach ( $authUser->getKeys() as $key ) {
			$module = $moduleRegistry->getModuleByKey( $key->getModule() );
			$allowedModules[$module->getName()] = $module->getDisplayName();
		}
		// Do not add the select request if there's nothing else to select.
		if ( count( $allowedModules ) > 1 ) {
			$selectRequest = new TwoFactorModuleSelectAuthenticationRequest( $currentModule, $allowedModules );
			$response->neededRequests[] = $selectRequest;
		}
	}
}
