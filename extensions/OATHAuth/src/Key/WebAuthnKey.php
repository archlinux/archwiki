<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\RS1;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS512;
use LogicException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\AAGUIDLookup;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\WebAuthnSerializerFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AttestedCredentialData;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\TrustPath\TrustPath;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * This holds the information on user's private key
 * and does the actual authentication of data passed
 * by the client with data saved on server
 */
class WebAuthnKey extends AuthKey {
	private const MODE_CREATE = 'webauthn.create';
	private const MODE_AUTHENTICATE = 'webauthn.authenticate';

	/**
	 * User handle represents the unique ID of the user.
	 *
	 * It is a randomly generated 64-bit string.
	 *
	 * It can change if the user disables and then re-enables
	 * webauthn module, but MUST be the same for each key
	 * if the user has multiple keys set at once.
	 */
	protected string $userHandle;

	protected AttestedCredentialData $attestedCredentialData;

	protected int $signCounter = 0;

	protected string $credentialType = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

	protected string $credentialAttestationType = '';

	protected TrustPath $credentialTrustPath;

	protected LoggerInterface $logger;

	protected array $credentialTransports = [];

	/**
	 * Create a new empty key instance.
	 *
	 * Used for new keys.
	 */
	public static function newKey(): self {
		return new static(
			null,
			null,
			null,
			static::MODE_CREATE,
			RequestContext::getMain()
		);
	}

	/**
	 * Create a new key instance from given data.
	 *
	 * Used for existing keys.
	 */
	public static function newFromData( array $data ): self {
		$key = new static(
			$data['id'] ?? null,
			$data['friendlyName'] ?? null,
			$data['created_timestamp'] ?? null,
			static::MODE_AUTHENTICATE,
			RequestContext::getMain()
		);
		$key->setDataFromEncodedDBData( $data );
		return $key;
	}

	protected function __construct(
		?int $id,
		?string $friendlyName,
		?string $createdTimestamp,
		protected string $mode,
		protected RequestContext $context
	) {
		parent::__construct( $id, $friendlyName, $createdTimestamp );
		// There is no documentation on what this trust path is
		// and how it should be used
		$this->credentialTrustPath = new EmptyTrustPath();
		$this->logger = LoggerFactory::getInstance( 'authentication' );
	}

	public function jsonSerialize(): array {
		return [
			"userHandle" => base64_encode( $this->userHandle ),
			"publicKeyCredentialId" => base64_encode( $this->attestedCredentialData->credentialId ),
			"credentialPublicKey" => base64_encode(
				(string)$this->attestedCredentialData->credentialPublicKey
			),
			"aaguid" => (string)$this->attestedCredentialData->aaguid,
			"friendlyName" => $this->friendlyName,
			"counter" => $this->signCounter,
			"type" => $this->credentialType,
			"transports" => $this->getTransports(),
			"attestationType" => $this->credentialAttestationType,
			"trustPath" => $this->credentialTrustPath,
			"supportsPasswordless" => $this->supportsPasswordless
		];
	}

	/**
	 * Set the key up with data coming from DB
	 */
	public function setDataFromEncodedDBData( array $data ): void {
		$this->userHandle = base64_decode( $data['userHandle'] );
		$this->signCounter = $data['counter'];
		$this->credentialTransports = $data['transports'];
		$this->supportsPasswordless = $data['supportsPasswordless'] ?? false;
		$this->attestedCredentialData = new AttestedCredentialData(
			Uuid::fromString( $data['aaguid'] ),
			base64_decode( $data['publicKeyCredentialId'] ),
			base64_decode( $data['credentialPublicKey'] )
		);
	}

	public function setPasswordlessSupport( bool $supportsPasswordlessMode ): void {
		$this->supportsPasswordless = $supportsPasswordlessMode;
	}

	/**
	 * Sets friendly name
	 * If value exists, it will be appended with a unique suffix
	 */
	private function setFriendlyName( string $name ): void {
		$this->friendlyName = trim( $name );
		$this->checkFriendlyName();
	}

	public function getAttestedCredentialData(): AttestedCredentialData {
		return $this->attestedCredentialData;
	}

	public function getUserHandle(): string {
		return $this->userHandle;
	}

	public function getSignCounter(): int {
		return $this->signCounter;
	}

	public function setSignCounter( int $newCount ): void {
		$this->signCounter = $newCount;
	}

	public function getAttestationType(): string {
		return $this->credentialAttestationType;
	}

	public function getTrustPath(): TrustPath {
		return $this->credentialTrustPath;
	}

	public function verify( OATHUser $user, array $data ): bool {
		if ( $this->mode !== static::MODE_AUTHENTICATE ) {
			$this->logger->error( 'Authentication attempt by user {user} while not in authenticate mode', [
				'user' => $user->getUser()->getName(),
			] );
			throw new LogicException( 'WebAuthnKey::verify(): invalid mode' );
		}
		return $this->authenticationCeremony(
			$data['credential'],
			$data['authInfo'],
			$user
		);
	}

	public function verifyRegistration(
		string $friendlyName,
		string $data,
		PublicKeyCredentialCreationOptions $registrationObject,
		OATHUser $user
	): bool {
		if ( $this->mode !== static::MODE_CREATE ) {
			$this->logger->error( 'Registration attempt by user {user} while not in register mode', [
				'user' => $user->getUser()->getName(),
			] );
			throw new LogicException( 'WebAuthnKey::verifyRegistration(): invalid mode' );
		}
		return $this->registrationCeremony( $data, $registrationObject, $user, $friendlyName );
	}

	/**
	 * Get the credential type
	 */
	public function getType(): string {
		return $this->credentialType;
	}

	/**
	 * Get transports available for the credential assigned to this key
	 */
	public function getTransports(): array {
		return $this->credentialTransports;
	}

	private function checkFriendlyName(): void {
		$repo = OATHAuthServices::getInstance()->getUserRepository();

		$friendlyNames = [];
		foreach ( WebAuthn::getWebAuthnKeys( $repo->findByUser( $this->context->getUser() ) ) as $key ) {
			$friendlyName = $key->getFriendlyName();
			if ( $friendlyName === null ) {
				continue;
			}
			$friendlyNames[] = strtolower( $friendlyName );
		}

		$original = $this->friendlyName;
		$inc = 2;
		while ( in_array( strtolower( $this->friendlyName ), $friendlyNames ) ) {
			$this->friendlyName = "$original #$inc";
			$inc++;
		}
	}

	private function registrationCeremony(
		string $data,
		PublicKeyCredentialCreationOptions $registrationObject,
		OATHUser $user,
		string $friendlyName = ''
	): bool {
		try {
			$attestationStatementSupportManager = self::getAttestationSupportManager();

			$serializer = ( new WebAuthnSerializerFactory( $attestationStatementSupportManager ) )->create();
			$publicKeyCredential = $serializer->deserialize(
				$data,
				PublicKeyCredential::class,
				'json',
			);

			$response = $publicKeyCredential->response;

			if ( !$response instanceof AuthenticatorAttestationResponse ) {
				return false;
			}

			$stepManagerFactory = new CeremonyStepManagerFactory();
			$stepManagerFactory->setAttestationStatementSupportManager( $attestationStatementSupportManager );
			$stepManagerFactory->setExtensionOutputCheckerHandler( new ExtensionOutputCheckerHandler() );
			$stepManagerFactory->setAllowedOrigins( [ $this->context->getConfig()->get( 'CanonicalServer' ) ] );

			$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
				ceremonyStepManager: $stepManagerFactory->creationCeremony()
			);

			$authenticatorAttestationResponseValidator->setLogger( $this->logger );

			$authenticatorAttestationResponseValidator->check(
				$response,
				$registrationObject,
				$this->getHost( $this->context->getRequest() ),
			);
		} catch ( Throwable $ex ) {
			$this->logger->warning(
				"WebAuthn key registration failed due to: {$ex->getMessage()}"
			);
			return false;
		}

		if ( $response->attestationObject->authData->hasAttestedCredentialData() ) {
			$this->userHandle = $registrationObject->user->id;
			$this->attestedCredentialData = $response->attestationObject
				->authData->attestedCredentialData;
			$this->signCounter = $response->attestationObject->authData->signCount;
			$this->credentialTransports = $response->transports;

			if ( trim( $friendlyName ) === '' ) {
				$aaguid = (string)$this->attestedCredentialData->aaguid;
				$friendlyName = AAGUIDLookup::generateFriendlyName( $aaguid );
			}
			$this->setFriendlyName( $friendlyName );

			$this->logger->info(
				"User {$user->getUser()->getName()} registered new WebAuthn key"
			);
			return true;
		}
		$this->logger->warning(
			'WebAuthn key registration failed due to: No AttestedCredentialData in the response'
		);
		return false;
	}

	private function authenticationCeremony(
		string $data,
		PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions,
		OATHUser $user
	): bool {
		try {
			$serializer = ( new WebAuthnSerializerFactory( self::getAttestationSupportManager() ) )->create();
			$publicKeyCredential = $serializer->deserialize(
				$data,
				PublicKeyCredential::class,
				'json',
			);

			$response = $publicKeyCredential->response;
			if ( !$response instanceof AuthenticatorAssertionResponse ) {
				return false;
			}

			$pubKeySource = $this->findOneByCredentialId( $user, $publicKeyCredential->rawId );
			if ( $pubKeySource === null ) {
				return false;
			}

			$coseAlgorithmManager = new Manager();
			$coseAlgorithmManager->add(
				new ES256(),
				new ES512(),
				new EdDSA(),
				new RS1(),
				new RS256(),
				new RS512()
			);

			$stepManagerFactory = new CeremonyStepManagerFactory();
			$stepManagerFactory->setExtensionOutputCheckerHandler( new ExtensionOutputCheckerHandler() );
			$stepManagerFactory->setAlgorithmManager( $coseAlgorithmManager );
			$stepManagerFactory->setAllowedOrigins( [ $this->context->getConfig()->get( 'CanonicalServer' ) ] );

			$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
				ceremonyStepManager: $stepManagerFactory->requestCeremony(),
			);
			$authenticatorAssertionResponseValidator->setLogger( $this->logger );

			// Check the response against the attestation request
			$authenticatorAssertionResponseValidator->check(
				$pubKeySource,
				$publicKeyCredential->response,
				$publicKeyCredentialRequestOptions,
				$this->getHost( $this->context->getRequest() ),
				$this->userHandle,
			);
			return true;
		} catch ( Throwable $ex ) {
			$this->logger->warning( 'WebAuthn authentication failed due to: {message}', [
				'message' => $ex->getMessage(),
				'exception' => $ex,
				'user' => $user->getUser()->getName(),
			] );
			return false;
		}
	}

	private function getHost( WebRequest $request ): string {
		return parse_url( $request->getFullRequestURL(), PHP_URL_HOST );
	}

	private function findOneByCredentialId(
		OATHUser $user,
		string $publicKeyCredentialId
	): ?CredentialRecord {
		foreach ( WebAuthn::getWebAuthnKeys( $user ) as $key ) {
			if ( $key->getAttestedCredentialData()->credentialId !== $publicKeyCredentialId ) {
				continue;
			}

			return CredentialRecord::create(
				publicKeyCredentialId: $key->getAttestedCredentialData()->credentialId,
				type: $key->getType(),
				transports: $key->getTransports(),
				attestationType: $key->getAttestationType(),
				trustPath: $key->getTrustPath(),
				aaguid: $key->getAttestedCredentialData()->aaguid,
				credentialPublicKey: (string)$key->getAttestedCredentialData()->credentialPublicKey,
				userHandle: $key->getUserHandle(),
				counter: $key->getSignCounter(),
			);
		}

		return null;
	}

	public static function getAttestationSupportManager(): AttestationStatementSupportManager {
		return new AttestationStatementSupportManager( [
			// FIXME supporting all these formats probably doesn't do much good as long as we
			//  set the attestation conveyance preference to 'none' in WebAuthnAuthenticator::getRegisterInfo()
			new FidoU2FAttestationStatementSupport(),
			new PackedAttestationStatementSupport( new Manager() ),
			new AndroidKeyAttestationStatementSupport(),
			new AppleAttestationStatementSupport(),
			new TPMAttestationStatementSupport( ConvertibleTimestamp::getClock() ),
		] );
	}

	/** @inheritDoc */
	public function getModule(): string {
		return WebAuthn::MODULE_ID;
	}
}
