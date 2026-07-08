<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

use Cose\Algorithms;
use Exception;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * This class serves as an authentication/registration
 * proxy, connecting the users to their keys and carrying out
 * the authentication process
 */
class WebAuthnAuthenticator {

	use KeySessionStorageTrait;

	private const string SESSION_KEY = 'webauthn_session_data';

	/** 5 minutes in ms */
	private const int CLIENT_ACTION_TIMEOUT = 300000;

	private const int MAX_ACTIVE_CHALLENGES = 5;

	private ?string $serverId;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly WebAuthn $module,
		private readonly RecoveryCodes $recoveryCodesModule,
		private readonly OATHAuthLogger $oathLogger,
		private readonly IContextSource $context,
		private readonly LoggerInterface $logger,
		private readonly AuthManager $authManager,
		private readonly UrlUtils $urlUtils,
		private readonly UserFactory $userFactory,
	) {
		$this->serverId = $this->getServerId();
	}

	public function isEnabled( OATHUser $user ): bool {
		return $this->module->isEnabled( $user );
	}

	public function getRequest(): WebRequest {
		return $this->authManager->getRequest();
	}

	public function canAuthenticate( OATHUser $user ): Status {
		if ( !$this->isEnabled( $user ) ) {
			return Status::newFatal(
				'oathauth-webauthn-error-module-not-enabled',
				$this->module->getName(),
				$user->getUser()->getName()
			);
		}

		return Status::newGood();
	}

	public function canRegister( OATHUser $user ): Status {
		if ( $this->userFactory->newFromUserIdentity( $user->getUser() )->isAllowed( 'oathauth-enable' ) ) {
			return Status::newGood();
		}

		return Status::newFatal(
			'oathauth-webauthn-error-cannot-register',
			$user->getUser()->getName()
		);
	}

	public function startAuthentication( OATHUser $user ): Status {
		return $this->startAuthenticationInternal( $user, false );
	}

	/**
	 * Initiate a new passwordless authentication session.
	 *
	 * This returns a credential request that is not specific to any given user.
	 */
	public function startPasswordlessAuthentication(): Status {
		return $this->startAuthenticationInternal( null, true );
	}

	private function startAuthenticationInternal( ?OATHUser $user, bool $userVerificationRequired ): Status {
		if ( $user ) {
			$canAuthenticate = $this->canAuthenticate( $user );
			if ( !$canAuthenticate->isGood() ) {
				$this->logger->error(
					"User {$user->getUser()->getName()} cannot authenticate"
				);
				return $canAuthenticate;
			}
		}
		$authInfo = $this->getAuthInfo( $user, $userVerificationRequired );
		$this->addPendingRequest( $authInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $authInfo, 'json' ),
			'raw' => $authInfo
		] );
	}

	public function determineUser( string $credential ): ?OATHUser {
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		try {
			$publicKeyCredential = $serializer->deserialize(
				$credential,
				PublicKeyCredential::class,
				'json',
			);
		} catch ( Throwable ) {
			return null;
		}
		$response = $publicKeyCredential->response;
		if ( !$response instanceof AuthenticatorAssertionResponse ) {
			return null;
		}
		$userHandle = $response->userHandle;
		if ( $userHandle === null ) {
			return null;
		}
		return $this->userRepo->findByUserHandle( $userHandle );
	}

	public function continueAuthentication(
		OATHUser $user,
		string $credential
	): Status {
		$canAuthenticate = $this->canAuthenticate( $user );
		if ( !$canAuthenticate->isGood() ) {
			$this->logger->error(
				"User {$user->getUser()->getName()} lost authenticate ability mid-request"
			);
			return $canAuthenticate;
		}

		$request = $this->getPendingRequestWithChallenge(
			PublicKeyCredentialRequestOptions::class,
			$this->getChallengeFromCredential( $credential )
		);
		$this->clearPendingRequests( PublicKeyCredentialRequestOptions::class );
		if ( $request === null ) {
			$this->oathLogger->logFailedVerification( $user->getUser() );
			return Status::newFatal( 'oathauth-webauthn-error-verification-failed' );
		}

		$verificationData = [
			'authInfo' => $request,
			'credential' => $credential
		];

		if ( $this->module->verify( $user, $verificationData ) ) {
			$this->logger->info(
				"User {$user->getUser()->getName()} logged in using WebAuthn"
			);
			return Status::newGood( $user );
		}
		$this->logger->warning(
			"Webauthn login failed for user {$user->getUser()->getName()}"
		);

		$this->oathLogger->logFailedVerification( $user->getUser() );

		return Status::newFatal( 'oathauth-webauthn-error-verification-failed' );
	}

	public function startRegistration( OATHUser $user, bool $passkeyMode = false ): Status {
		$canRegister = $this->canRegister( $user );
		if ( !$canRegister->isGood() ) {
			$this->logger->error(
				"User {$user->getUser()->getName()} cannot register a credential"
			);
			return $canRegister;
		}
		$registerInfo = $this->getRegisterInfo( $user, $passkeyMode );
		$this->addPendingRequest( $registerInfo );

		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();

		return Status::newGood( [
			'json' => $serializer->serialize( $registerInfo, 'json' ),
			'raw' => $registerInfo
		] );
	}

	public function continueRegistration(
		OATHUser $user, string $credential, string $friendlyName = '', bool $passkeyMode = false
	): Status {
		$canRegister = $this->canRegister( $user );
		if ( !$canRegister->isGood() ) {
			$username = $user->getUser()->getName();
			$this->logger->error(
				"User $username lost registration ability mid-request"
			);
			return $canRegister;
		}

		$registerInfo = $this->getPendingRequestWithChallenge(
			PublicKeyCredentialCreationOptions::class,
			$this->getChallengeFromCredential( $credential )
		);
		if ( $registerInfo === null ) {
			return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
		}

		$key = $this->module->newKey();
		try {
			$registered = $key->verifyRegistration(
				$friendlyName,
				$credential,
				$registerInfo,
				$user
			);
			if ( $passkeyMode ) {
				$key->setPasswordlessSupport( true );
			}
			if ( $registered ) {
				$this->userRepo->createKey(
					$user,
					$this->module,
					$key->jsonSerialize(),
					$this->getRequest()->getIP()
				);

				$this->recoveryCodesModule->ensureExistence(
					$user,
					$this->getKeyDataInSession( 'RecoveryCodeKeys' )
				);

				$this->clearPendingRequests( PublicKeyCredentialCreationOptions::class );
				return Status::newGood();
			}
		} catch ( Exception $ex ) {
			$this->logger->warning( 'WebAuthn registration failed due to: {message}', [
				'message' => $ex->getMessage(),
				'exception' => $ex,
				'user' => $user->getUser()->getName(),
			] );
			return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
		}
		return Status::newFatal( 'oathauth-webauthn-error-registration-failed' );
	}

	private function getChallengeFromCredential( string $credential ): string {
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		$publicKeyCredential = $serializer->deserialize(
			$credential,
			PublicKeyCredential::class,
			'json',
		);
		return $publicKeyCredential->response->clientDataJSON->challenge;
	}

	private function addPendingRequest( PublicKeyCredentialOptions $data ) {
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		$sessionKey = self::SESSION_KEY . '_' . $data::class;
		$requests = $this->getRequest()->getSession()->getSecret( $sessionKey ) ?? [];
		$requests[] = [
			'json' => $serializer->serialize( $data, 'json' ),
			'expires' => time() + ceil( $data->timeout / 1000 )
		];
		$requests = $this->filterExpiredRequests( $requests );
		$this->getRequest()->getSession()->setSecret( $sessionKey, $requests );
	}

	private function clearPendingRequests( string $returnClass ) {
		$this->getRequest()->getSession()->remove( self::SESSION_KEY . '_' . $returnClass );
	}

	/**
	 * @template T of PublicKeyCredentialOptions
	 * @param class-string<T> $returnClass
	 * @return T[]
	 */
	private function getPendingRequests( string $returnClass ): array {
		$sessionKey = self::SESSION_KEY . '_' . $returnClass;
		$requests = $this->getRequest()->getSession()->getSecret( $sessionKey );
		if ( $requests === null ) {
			return [];
		}
		$filteredRequests = $this->filterExpiredRequests( $requests );
		if ( count( $filteredRequests ) < count( $requests ) ) {
			$this->getRequest()->getSession()->setSecret( $sessionKey, $filteredRequests );
		}
		$serializer = ( new WebAuthnSerializerFactory( WebAuthnKey::getAttestationSupportManager() ) )->create();
		return array_map(
			static fn ( $r ) => $serializer->deserialize( $r['json'], $returnClass, 'json' ),
			$filteredRequests
		);
	}

	/**
	 * @template T of PublicKeyCredentialOptions
	 * @param class-string<T> $returnClass
	 * @param string $challenge
	 * @return T|null
	 */
	private function getPendingRequestWithChallenge( string $returnClass, string $challenge ) {
		$requests = $this->getPendingRequests( $returnClass );
		return array_find( $requests, static fn ( $request ) => $request->challenge === $challenge );
	}

	private function filterExpiredRequests( array $requests ): array {
		$now = time();
		return array_slice(
			array_filter( $requests, static fn ( $r ) => $r['expires'] >= $now ),
			-self::MAX_ACTIVE_CHALLENGES
		);
	}

	/**
	 * Information to be sent to the client to start the authentication process
	 */
	private function getAuthInfo( ?OATHUser $user, bool $userVerificationRequired ): PublicKeyCredentialRequestOptions {
		$keys = $user ? WebAuthn::getWebAuthnKeys( $user ) : [];
		$credentialDescriptors = [];
		foreach ( $keys as $key ) {
			$credentialDescriptors[] = new PublicKeyCredentialDescriptor(
				$key->getType(),
				$key->getAttestedCredentialData()->credentialId,
				$key->getTransports()
			);
		}

		return PublicKeyCredentialRequestOptions::create(
			random_bytes( 32 ),
			$this->serverId,
			$credentialDescriptors,
			$userVerificationRequired ?
				PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED :
				PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			self::CLIENT_ACTION_TIMEOUT
		);
	}

	/**
	 * Information to be sent to the client to start the registration process
	 */
	private function getRegisterInfo(
		OATHUser $user,
		bool $passkeyMode = false
	): PublicKeyCredentialCreationOptions {
		$serverName = $this->getServerName();
		$rpEntity = new PublicKeyCredentialRpEntity( $serverName, $this->serverId );

		// Exclude all already registered keys for user
		/** @var WebAuthnKey[] $webauthnKeys */
		$webauthnKeys = $user->getKeysForModule( WebAuthn::MODULE_ID );
		'@phan-var WebAuthnKey[] $webauthnKeys';
		$excludedPublicKeyDescriptors = array_map( static fn ( $key ) => new PublicKeyCredentialDescriptor(
			PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
			$key->getAttestedCredentialData()->credentialId
		), $webauthnKeys );

		$mwUser = $this->userFactory->newFromUserIdentity( $user->getUser() );
		$userHandle = $user->getUserHandle() ?? random_bytes( 64 );
		$realName = $mwUser->getRealName() ?: $mwUser->getName();
		$userEntity = new PublicKeyCredentialUserEntity(
			$mwUser->getName(),
			$userHandle,
			$realName
		);

		$publicKeyCredParametersList = [
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_ES256
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_ES512
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_EDDSA
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS1
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS256
			),
			new PublicKeyCredentialParameters(
				'public-key',
				Algorithms::COSE_ALGORITHM_RS512
			),
		];

		if ( $passkeyMode ) {
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
				residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
			);
		} else {
			$authSelectorCriteria = AuthenticatorSelectionCriteria::create(
				authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM,
			);
		}

		return PublicKeyCredentialCreationOptions::create(
			$rpEntity,
			$userEntity,
			random_bytes( 32 ),
			$publicKeyCredParametersList,
			$authSelectorCriteria,
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			$excludedPublicKeyDescriptors,
			self::CLIENT_ACTION_TIMEOUT
		);
	}

	/**
	 * Get identifier for this server
	 */
	private function getServerId(): ?string {
		$rpId = $this->context->getConfig()->get( 'WebAuthnRelyingPartyID' );
		if ( $rpId && is_string( $rpId ) ) {
			return $rpId;
		}

		$server = $this->context->getConfig()->get( 'Server' );
		$serverBits = $this->urlUtils->parse( $server );
		if ( $serverBits !== null ) {
			return $serverBits['host'];
		}

		return null;
	}

	/**
	 * Get the name for this server
	 */
	private function getServerName(): string {
		$serverName = $this->context->getConfig()->get( 'WebAuthnRelyingPartyName' );
		if ( $serverName && is_string( $serverName ) ) {
			return $serverName;
		}
		if ( $this->context->getConfig()->has( 'Sitename' ) ) {
			return $this->context->getConfig()->get( 'Sitename' );
		}

		return WikiMap::getCurrentWikiId();
	}
}
