<?php

namespace MediaWiki\User\TempUser;

use ExtensionRegistry;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Throttler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserRigorOptions;
use MediaWiki\Utils\MWTimestamp;
use UnexpectedValueException;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Service for temporary user creation. For convenience this also proxies the
 * TempUserConfig methods.
 *
 * This is separate from TempUserConfig to avoid dependency loops. Special pages
 * and actions are free to use this class, but services should take it as a
 * constructor parameter only if necessary.
 *
 * @since 1.39
 */
class TempUserCreator implements TempUserConfig {
	private RealTempUserConfig $config;
	private UserFactory $userFactory;
	private AuthManager $authManager;
	private CentralIdLookup $centralIdLookup;
	private ?Throttler $throttler;
	private array $serialProviderConfig;
	private array $serialMappingConfig;
	private ObjectFactory $objectFactory;
	private ?SerialProvider $serialProvider;
	private ?SerialMapping $serialMapping;

	/** ObjectFactory specs for the core serial providers */
	private const SERIAL_PROVIDERS = [
		'local' => [
			'class' => LocalSerialProvider::class,
			'services' => [ 'DBLoadBalancer' ],
		]
	];

	/** ObjectFactory specs for the core serial maps */
	private const SERIAL_MAPPINGS = [
		'plain-numeric' => [
			'class' => PlainNumericSerialMapping::class,
		],
		'localized-numeric' => [
			'class' => LocalizedNumericSerialMapping::class,
			'services' => [ 'LanguageFactory' ],
		],
		'filtered-radix' => [
			'class' => FilteredRadixSerialMapping::class,
		],
		'scramble' => [
			'class' => ScrambleMapping::class,
		]
	];

	public function __construct(
		RealTempUserConfig $config,
		ObjectFactory $objectFactory,
		UserFactory $userFactory,
		AuthManager $authManager,
		CentralIdLookup $centralIdLookup,
		?Throttler $throttler
	) {
		$this->config = $config;
		$this->objectFactory = $objectFactory;
		$this->userFactory = $userFactory;
		$this->authManager = $authManager;
		$this->centralIdLookup = $centralIdLookup;
		$this->throttler = $throttler;
		$this->serialProviderConfig = $config->getSerialProviderConfig();
		$this->serialMappingConfig = $config->getSerialMappingConfig();
	}

	/**
	 * Acquire a serial number, create the corresponding user and log in.
	 *
	 * @param string|null $name Previously acquired name
	 * @param WebRequest|null $request Request details, used for throttling
	 * @return CreateStatus
	 */
	public function create( $name = null, WebRequest $request = null ): CreateStatus {
		$status = new CreateStatus;
		if ( $request && $this->throttler ) {
			// TODO: This is duplicated from ThrottlePreAuthenticationProvider
			// and should be factored out, see T261744
			$result = $this->throttler->increase(
				null, $request->getIP(), 'TempUserCreator' );
			if ( $result ) {
				// TODO: Use a custom message here (T357777, T357802)
				$message = wfMessage( 'acct_creation_throttle_hit' )->params( $result['count'] )
					->durationParams( $result['wait'] );
				$status->fatal( $message );
				return $status;
			}
		}

		if ( $name === null ) {
			$name = $this->acquireName();
			if ( $name === null ) {
				// If the $name remains null after calling ::acquireName, then
				// we cannot generate a username and therefore cannot create a user.
				// In this case return a CreateStatus indicating no user was created.
				return CreateStatus::newFatal( 'temp-user-unable-to-acquire' );
			}
		}
		$createStatus = $this->attemptAutoCreate( $name );

		if ( $createStatus->isOK() ) {
			// The temporary account name didn't already exist, so now attempt to login
			// using ::attemptAutoCreate as there isn't a public method to just login.
			$this->attemptAutoCreate( $name, true );
		}
		return $createStatus;
	}

	public function isEnabled() {
		return $this->config->isEnabled();
	}

	public function isAutoCreateAction( string $action ) {
		return $this->config->isAutoCreateAction( $action );
	}

	public function shouldAutoCreate( Authority $authority, string $action ) {
		return $this->config->shouldAutoCreate( $authority, $action );
	}

	public function isTempName( string $name ) {
		return $this->config->isTempName( $name );
	}

	public function isReservedName( string $name ) {
		return $this->config->isReservedName( $name );
	}

	public function getPlaceholderName(): string {
		return $this->config->getPlaceholderName();
	}

	public function getMatchPattern(): Pattern {
		return $this->config->getMatchPattern();
	}

	public function getMatchPatterns(): array {
		return $this->config->getMatchPatterns();
	}

	public function getMatchCondition( IReadableDatabase $db, string $field, string $op ): IExpression {
		return $this->config->getMatchCondition( $db, $field, $op );
	}

	public function getExpireAfterDays(): ?int {
		return $this->config->getExpireAfterDays();
	}

	public function getNotifyBeforeExpirationDays(): ?int {
		return $this->config->getNotifyBeforeExpirationDays();
	}

	/**
	 * Attempts to auto create a temporary user using
	 * AuthManager::autoCreateUser, and optionally log them
	 * in if $login is true.
	 *
	 * @param string $name
	 * @param bool $login Whether to also log the user in to this temporary account.
	 * @return CreateStatus
	 */
	private function attemptAutoCreate( string $name, bool $login = false ): CreateStatus {
		$createStatus = new CreateStatus;
		// Verify the $name is usable.
		$user = $this->userFactory->newFromName( $name, UserRigorOptions::RIGOR_USABLE );
		if ( !$user ) {
			$createStatus->fatal( 'internalerror_info',
				'Unable to create user with automatically generated name' );
			return $createStatus;
		}
		$status = $this->authManager->autoCreateUser(
			$user,
			AuthManager::AUTOCREATE_SOURCE_TEMP,
			$login, // login
			false // log
		);
		$createStatus->merge( $status );
		// If a userexists warning is a part of the status, then
		// add the fatal error temp-user-unable-to-acquire.
		if ( $createStatus->hasMessage( 'userexists' ) ) {
			$createStatus->fatal( 'temp-user-unable-to-acquire' );
		}
		if ( $createStatus->isOK() ) {
			$createStatus->value = $user;
		}
		return $createStatus;
	}

	/**
	 * Acquire a new username and return it. Permanently reserve the ID in
	 * the database.
	 *
	 * @return string|null The username, or null if the auto-generated username is
	 *    already in use.
	 */
	private function acquireName(): ?string {
		$year = null;
		if ( $this->serialProviderConfig['useYear'] ?? false ) {
			$year = MWTimestamp::getInstance()->format( 'Y' );
		}
		// Check if the temporary account name is already in use as the ID provided
		// may not be properly collision safe (T353390)
		$index = $this->getSerialProvider()->acquireIndex( (int)$year );
		$serialId = $this->getSerialMapping()->getSerialIdForIndex( $index );
		$username = $this->config->getGeneratorPattern()->generate( $serialId, $year );

		// Because the ::acquireIndex method may not always return a unique index,
		// make sure that the temporary account name does not already exist. This
		// is needed because of the problems discussed in T353390.
		// The problems discussed at that task should not require the use of a primary lookup.
		$centralId = $this->centralIdLookup->centralIdFromName(
			$username,
			CentralIdLookup::AUDIENCE_RAW
		);
		if ( !$centralId ) {
			// If no user exists with this name centrally, then return the $username.
			return $username;
		}
		return null;
	}

	/**
	 * Get the serial provider
	 * @return SerialProvider
	 */
	private function getSerialProvider(): SerialProvider {
		if ( !isset( $this->serialProvider ) ) {
			$this->serialProvider = $this->createSerialProvider();
		}
		return $this->serialProvider;
	}

	/**
	 * Create the serial provider
	 * @return SerialProvider
	 */
	private function createSerialProvider(): SerialProvider {
		$type = $this->serialProviderConfig['type'];
		if ( isset( self::SERIAL_PROVIDERS[$type] ) ) {
			$spec = self::SERIAL_PROVIDERS[$type];
		} else {
			$extensionProviders = ExtensionRegistry::getInstance()
				->getAttribute( 'TempUserSerialProviders' );
			if ( isset( $extensionProviders[$type] ) ) {
				$spec = $extensionProviders[$type];
			} else {
				throw new UnexpectedValueException( __CLASS__ . ": unknown serial provider \"$type\"" );
			}
		}

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		// @phan-suppress-next-line PhanTypeInvalidCallableArrayKey
		return $this->objectFactory->createObject(
			$spec,
			[
				'assertClass' => SerialProvider::class,
				'extraArgs' => [ $this->serialProviderConfig ]
			]
		);
	}

	/**
	 * Get the serial mapping
	 * @return SerialMapping
	 */
	private function getSerialMapping(): SerialMapping {
		if ( !isset( $this->serialMapping ) ) {
			$this->serialMapping = $this->createSerialMapping();
		}
		return $this->serialMapping;
	}

	/**
	 * Create the serial map
	 * @return SerialMapping
	 */
	private function createSerialMapping(): SerialMapping {
		$type = $this->serialMappingConfig['type'];
		if ( isset( self::SERIAL_MAPPINGS[$type] ) ) {
			$spec = self::SERIAL_MAPPINGS[$type];
		} else {
			$extensionMappings = ExtensionRegistry::getInstance()
				->getAttribute( 'TempUserSerialMappings' );
			if ( isset( $extensionMappings[$type] ) ) {
				$spec = $extensionMappings[$type];
			} else {
				throw new UnexpectedValueException( __CLASS__ . ": unknown serial mapping \"$type\"" );
			}
		}
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		// @phan-suppress-next-line PhanTypeInvalidCallableArrayKey
		return $this->objectFactory->createObject(
			$spec,
			[
				'assertClass' => SerialMapping::class,
				'extraArgs' => [ $this->serialMappingConfig ]
			]
		);
	}

	/**
	 * Permanently acquire a username, stash it in a session, and return it.
	 * Do not create the user.
	 *
	 * If this method was called before with the same session ID, return the
	 * previously stashed username instead of acquiring a new one.
	 *
	 * @param Session $session
	 * @return string|null The username, or null if no username could be acquired
	 */
	public function acquireAndStashName( Session $session ) {
		$name = $session->get( 'TempUser:name' );
		if ( $name !== null ) {
			return $name;
		}
		$name = $this->acquireName();
		if ( $name !== null ) {
			$session->set( 'TempUser:name', $name );
			$session->save();
		}
		return $name;
	}

	/**
	 * Return a possible acquired and stashed username in a session.
	 * Do not acquire or create the user.
	 *
	 * If this method is called with the same session ID as function acquireAndStashName(),
	 * it returns the previously stashed username.
	 *
	 * @since 1.41
	 * @param Session $session
	 * @return ?string The username, if it was already acquired
	 */
	public function getStashedName( Session $session ): ?string {
		return $session->get( 'TempUser:name' );
	}
}
