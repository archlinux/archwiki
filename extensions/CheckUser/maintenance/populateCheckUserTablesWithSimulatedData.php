<?php

namespace MediaWiki\CheckUser\Maintenance;

use MailAddress;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Populates the CheckUser tables with simulated data that can be useful for
 * testing.
 *
 * WARNING: This should never be run on production wikis. This is intended only for
 * local testing wikis where the DB can be cleared without issue.
 */
class PopulateCheckUserTablesWithSimulatedData extends Maintenance {

	private const VALID_LOG_EVENTS = [
		'move' => [ 'move', 'move_redir' ],
		'delete' => [ 'delete', 'restore' ],
		'suppress' => [ 'delete' ],
		'merge' => [ 'merge' ]
	];

	/** @var array<string,?ClientHintsData> */
	private array $userAgentsToClientHintsMap;

	private RecentChangeSaveHandler $recentChangeSaveHandler;
	private CheckUserPrivateEventsHandler $privateEventsHandler;

	private User $userToEmailAndSendPasswordResetsFor;

	private ?ClientHintsData $currentClientHintsData;

	private array $ipv4Ranges = [];

	private array $ipv6Ranges = [];

	private array $ipsToUse;

	private FauxRequest $mainRequest;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'If you use --num-temp with this script, set ' .
			'$wgTempAccountNameAcquisitionThrottle to null to avoid rate limiting on ' .
			'temporary account name acquisitions' );
		$this->addOption(
			'num-users',
			'How many users should be created and used for the simulated actions. ' .
			'The number of actions performed will roughly be split equally between the users. Default is 10.',
			false,
			true
		);
		$this->addOption(
			'num-anon',
			'How many IPs should be used for the simulated actions. ' .
			'The number of actions performed will roughly be split equally between the IPs. Default is 5.',
			false,
			true
		);
		$this->addOption(
			'num-temp',
			'How many temporary accounts should be used for the simulated actions. ' .
			'The number of actions performed will roughly be split equally between the temporary accounts.' .
			'This is ignored if temporary account creation is disabled. If not ignored, the default is 10.',
			false,
			true
		);
		$this->addOption(
			'num-used-ips',
			'How many IPs to select from the ranges in ranges-for-ips. Must not be smaller than num-anon. ' .
			'These IPs will be used for anon edits, temporary account and user actions. These will also be used ' .
			'in the XFF header (if set) for actions. Default is 5.',
			false,
			true
		);
		$this->addOption(
			'ranges-for-ips',
			'What ranges should the IPs be selected from. Default is one IPv4 and IPv6 range inside ' .
			'ranges defined as internal.',
			false, true, false, true
		);
		$this->addArg(
			'count',
			'How many items to be added to the CheckUser tables. Default is 1,000 items.',
			false
		);

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		// Check development mode is enabled
		if ( $this->getConfig()->get( 'CheckUserDeveloperMode' ) !== true ) {
			$this->fatalError(
				"CheckUser development mode must be enabled to use this script. To do this, set " .
				"wgCheckUserDeveloperMode to true. Only do this on localhost testing wikis."
			);
		}
		// Set-up and argument parsing.
		$count = $this->ensureArgumentIsInt(
			$this->getArg( 0, 1000 ),
			'Count'
		);
		$numUsers = $this->ensureArgumentIsInt(
			$this->getOption( 'num-users', 10 ),
			'Number of registered users'
		);
		$numAnon = $this->ensureArgumentIsInt(
			$this->getOption( 'num-anon', 5 ),
			'Number of anonymous users'
		);
		$numTemp = $this->ensureArgumentIsInt(
			$this->getOption( 'num-temp', 10 ),
			'Number of temporary users'
		);
		$numIpsToUse = $this->ensureArgumentIsInt(
			$this->getOption( 'num-used-ips', 5 ),
			'Number of IPs to use'
		);
		$ipRanges = $this->getOption( 'ranges-for-ips', [ '127.0.0.1/24', 'fd12:3456:789a:1::/40' ] );

		foreach ( $ipRanges as $range ) {
			if ( !IPUtils::isValidRange( $range ) ) {
				$this->fatalError( 'range-for-ips option must be a valid IP address range.' );
			}
			if ( IPUtils::isIPv4( $range ) ) {
				$this->ipv4Ranges[] = $range;
			} else {
				$this->ipv6Ranges[] = $range;
			}
		}

		if ( $numAnon > $numIpsToUse ) {
			$this->fatalError( 'Number of anon users making edits should not exceed the number of IPs used.' );
		}

		$services = $this->getServiceContainer();
		if ( !$services->getTempUserConfig()->isEnabled() ) {
			// Only add temporary users if temporary user creation is enabled.
			$numTemp = 0;
		}

		$actionsPerActor = intval( floor( $count / array_sum( [ $numUsers, $numAnon, $numTemp ] ) ) );
		$remainderActions = $count % array_sum( [ $numUsers, $numAnon, $numTemp ] );

		if ( $actionsPerActor < 5 ) {
			$minCount = array_sum( [ $numUsers, $numAnon, $numTemp ] ) * 5;
			$this->fatalError(
				"Minimum actions per actor must be 5. Increase the 'count' argument to at least {$minCount}."
			);
		}

		// Start code that can assume it is safe to perform un-reversible testing actions.
		$this->privateEventsHandler = new CheckUserPrivateEventsHandler(
			$services->get( 'CheckUserInsert' ),
			$this->getConfig(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getReadOnlyMode(),
			$services->get( 'UserAgentClientHintsManager' ),
			$services->getJobQueueGroup(),
			$services->getConnectionProvider()
		);
		$this->recentChangeSaveHandler = new RecentChangeSaveHandler(
			$services->get( 'CheckUserInsert' ),
			$services->getJobQueueGroup(),
			$services->getConnectionProvider()
		);
		$userForEmails = $this->createRegisteredUser();
		if ( $userForEmails === null ) {
			$this->fatalError(
				"Unable to create a new user to be used as the target for emails and password resets.\n"
			);
		}
		$this->userToEmailAndSendPasswordResetsFor = $userForEmails;
		$this->mainRequest = new FauxRequest();
		RequestContext::getMain()->setRequest( $this->mainRequest );
		$this->initUserAgentAndClientHintsCombos();

		// Get $numIpsToUse IPs.
		$this->ipsToUse = [];
		// First try to get one random IPv4 in the allowed IPv4 range(s).
		if ( count( $this->ipv4Ranges ) ) {
			$this->ipsToUse[] = $this->generateNewIPv4();
			$numIpsToUse--;
		}
		// Next try to get one random IPv6 in the allowed IPv6 range(s).
		if ( count( $this->ipv6Ranges ) && $numIpsToUse > 0 ) {
			$this->ipsToUse[] = $this->generateNewIPv6();
			$numIpsToUse--;
		}
		// If IPs can still be chosen then randomly generate
		// them from either IPv4 or IPv6 ranges.
		if ( $numIpsToUse > 0 ) {
			foreach ( range( 0, $numIpsToUse ) as $ignored ) {
				$this->ipsToUse[] = $this->generateNewIp();
			}
		}

		// Get the first IP, user agent and client hints data
		$this->getNewIp();
		$this->getNewUserAgentAndAssociatedClientHints();

		// First populate using users
		for ( $i = 0; $i < $numUsers; $i++ ) {
			$actionsLeft = $actionsPerActor;
			$this->applyRemainderAction( $actionsLeft, $remainderActions );

			// Find a username that is not already being used
			$this->setNewRandomFakeTime();
			$lowerLimit = time() - ConvertibleTimestamp::time();
			$user = $this->createRegisteredUser();
			// Creating an account causes a log event.
			$actionsLeft--;
			if ( $user === null ) {
				$this->output( "Unable to create new user. Skipping this actor.\n" );
				continue;
			}
			$this->output( "Processing user with username {$user->getName()}.\n" );

			while ( $actionsLeft > 0 ) {
				if ( $this->getRandomFloat() < 0.3 ) {
					// Assign a new IP to the main request 30% of the time.
					$this->getNewIp();
				}

				$actionsLeft -= $this->performInsertBatch( $user, $actionsLeft, $lowerLimit );
			}
		}

		// Secondly populate using temporary accounts
		for ( $i = 0; $i < $numTemp; $i++ ) {
			$actionsLeft = $actionsPerActor;
			$this->applyRemainderAction( $actionsLeft, $remainderActions );

			$this->setNewRandomFakeTime();
			$lowerLimit = time() - ConvertibleTimestamp::time();
			$user = $services->getTempUserCreator()->create(
				null, $this->mainRequest
			)->getUser();
			// Creating a temporary user creates a log event.
			$actionsLeft--;
			$this->output( "Processing temporary user with username {$user->getName()}.\n" );

			while ( $actionsLeft > 0 ) {
				if ( $this->getRandomFloat() < 0.3 ) {
					// Assign a new IP to the main request 30% of the time.
					$this->getNewIp();
				}

				$actionsLeft -= $this->performInsertBatch( $user, $actionsLeft, $lowerLimit );
			}
		}

		// Lastly populate using IPs
		if ( count( $this->ipsToUse ) < 3 ) {
			// If less than 3 IPs to choose from, keep the original ordering.
			$ipsInOrder = $this->ipsToUse;
		} else {
			// If three or more IPs to choose from, pick the first two and a random
			// selection of the other IPs. This ensures at least one IPv4 and IPv6
			// address is used if allowed.
			$ipsInOrder = array_slice( $this->ipsToUse, 2 );
			shuffle( $ipsInOrder );
			array_unshift( $ipsInOrder, $this->ipsToUse[0], $this->ipsToUse[1] );
		}
		for ( $i = 0; $i < $numAnon; $i++ ) {
			$actionsLeft = $actionsPerActor;
			$this->applyRemainderAction( $actionsLeft, $remainderActions );

			$user = UserIdentityValue::newAnonymous( IPUtils::prettifyIP( $ipsInOrder[$i] ) );
			// Assign the request IP as the anon user being used for this loop.
			RequestContext::getMain()->getRequest()->setIP( $user->getName() );
			$this->output( "Processing anon user with IP {$user->getName()}.\n" );

			while ( $actionsLeft > 0 ) {
				$this->randomlyAssignXFFHeader( $user->getName() );
				$actionsLeft -= $this->performInsertBatch( $user, $actionsLeft );
			}
		}
	}

	/**
	 * Ensure an argument provided via the command line is an integer.
	 * If it is not, then exit the script with a fatal error message.
	 *
	 * @param mixed $argument The argument from the command line (usually in string form)
	 * @param string $name The name of the argument used if the argument is not an integer
	 * in the fatal error message.
	 * @return int The argument as an integer (exit is called if the argument was invalid).
	 */
	private function ensureArgumentIsInt( $argument, string $name ): int {
		if ( ( !$argument || !intval( $argument ) ) && $argument !== '0' ) {
			$this->fatalError( "$name must be an integer" );
		}

		return intval( $argument );
	}

	/**
	 * Reduce the remainder argument by 1 and increase the actions left
	 * argument by 1, as long as the remainder argument is above 0.
	 *
	 * @param int &$actionsLeft The actions left for an actor
	 * @param int &$remainderActions The remainder of the floor division
	 * @return void
	 */
	private function applyRemainderAction( int &$actionsLeft, int &$remainderActions ) {
		if ( $remainderActions > 0 ) {
			$actionsLeft += 1;
			$remainderActions -= 1;
		}
	}

	/**
	 * Create a user on the wiki with a username
	 * prefixed with CheckUserSimulated and then
	 * a random string of hexadecimal characters.
	 *
	 * @return ?User A user that has just been created or null if this failed.
	 */
	private function createRegisteredUser(): ?User {
		$services = $this->getServiceContainer();
		// Find a username that doesn't exist.
		$attemptsMade = 0;
		do {
			$user = $services->getUserFactory()->newFromName(
				$this->getPrefix() . wfRandomString(), UserRigorOptions::RIGOR_CREATABLE
			);
			if ( $attemptsMade > 100 ) {
				return null;
			}
			$attemptsMade++;
		} while ( $user === null || $user->isRegistered() );
		'@phan-var User $user';
		// Create an account using this username
		$services->getAuthManager()->autoCreateUser(
			$user,
			AuthManager::AUTOCREATE_SOURCE_MAINT,
			false
		);
		return $user;
	}

	/**
	 * Calls wfRandom and returns the value.
	 *
	 * Called by other code in this class so that tests
	 * can mock the return value.
	 *
	 * @return float A float in the range [0, 1]
	 */
	protected function getRandomFloat(): float {
		return floatval( wfRandom() );
	}

	/**
	 * Calls mt_rand and returns the value.
	 *
	 * Called by code in this class so that tests
	 * can mock the return value to test behaviour
	 * that is determined randomly using mt_rand.
	 *
	 * @param int $min See mt_rand documentation
	 * @param int $max See mt_rand documentation
	 * @return int A random integer, see mt_rand documentation for more details.
	 */
	protected function mtRand( $min, $max ): int {
		return mt_rand( $min, $max );
	}

	/**
	 * This method 30% of the time will apply a XFF header to the request
	 * and in other cases will clear any existing XFF header.
	 *
	 * @todo Make the XFF IPs make a bit more sense than using random IPs and/or
	 * make some of the XFF strings trusted.
	 *
	 * @param string $currentIp The current IP address of the request that will not
	 * be used in the XFF header.
	 */
	private function randomlyAssignXFFHeader( string $currentIp ): void {
		if ( $this->getRandomFloat() < 0.3 ) {
			$xffIp = $this->returnRandomIpExceptExcluded( [ $currentIp ] );
			if ( !$xffIp ) {
				$xffValue = false;
			} else {
				$xffValue = IPUtils::prettifyIP( $xffIp );
				if ( $this->getRandomFloat() < 0.7 ) {
					$xffIp = $this->returnRandomIpExceptExcluded( [ $currentIp, $xffIp ] );
					if ( $xffIp ) {
						$xffValue = $xffValue . ', ' . IPUtils::prettifyIP( $xffIp );
					}
				}
			}
		} else {
			$xffValue = false;
		}
		$this->mainRequest->setHeaders( [ 'X-Forwarded-For' => $xffValue ] );
	}

	/**
	 * Return a random IP address from the list of IPs chosen
	 * in the property self::ipsToUse excluding those provided
	 * in the arguments.
	 *
	 * @param array $ipsExcluded The IPs to exclude from the random selection
	 * @return string|null A random IP or null if no IPs are left after the exclusion step.
	 */
	private function returnRandomIpExceptExcluded( array $ipsExcluded ): ?string {
		$ipsToChoose = array_flip( array_filter(
			$this->ipsToUse,
			static function ( $item ) use ( $ipsExcluded ) {
				return !in_array( $item, $ipsExcluded );
			}
		) );
		if ( count( $ipsToChoose ) ) {
			return array_rand( $ipsToChoose );
		}
		return null;
	}

	/**
	 * Randomly pick either an IPv4 or IPv6 address
	 * from the list of IPs that were already chosen randomly.
	 * Also assign the IP as the IP used in the main request.
	 *
	 * @return string The IP that was chosen
	 */
	private function getNewIp(): string {
		$ip = array_rand( array_flip( $this->ipsToUse ) );
		$this->randomlyAssignXFFHeader( $ip );
		RequestContext::getMain()->getRequest()->setIP( $ip );
		return $ip;
	}

	/**
	 * Generate a randomly chosen IPv4 or IPv6 address that sits within the allowed ranges.
	 * If the set of allowed ranges contain both IPv4 and IPv6 ranges, an IPv4 address is returned
	 * 50% of the time on average.
	 *
	 * @return string
	 */
	private function generateNewIp(): string {
		if ( count( $this->ipv4Ranges ) === 0 ) {
			return $this->generateNewIPv6();
		}

		if ( count( $this->ipv6Ranges ) === 0 ) {
			return $this->generateNewIPv4();
		}

		if ( $this->getRandomFloat() < 0.5 ) {
			return $this->generateNewIPv4();
		} else {
			return $this->generateNewIPv6();
		}
	}

	/**
	 * Randomly pick a new IPv4 address that comes from
	 * one of the defined ranges.
	 *
	 * @return string The IP that was chosen
	 */
	private function generateNewIPv4(): string {
		[ $start, $end ] = IPUtils::parseRange( array_rand( array_flip( $this->ipv4Ranges ) ) );
		$start = ip2long( IPUtils::formatHex( $start ) );
		$end = ip2long( IPUtils::formatHex( $end ) );
		$ipAsLong = $this->mtRand( $start, $end );
		$ip = long2ip( $ipAsLong );
		return $ip;
	}

	/**
	 * Randomly pick a new IPv6 address that comes
	 * from one of the defined IPv6 ranges.
	 *
	 * @return string The IP that was chosen
	 */
	private function generateNewIPv6(): string {
		[ $start, $end ] = IPUtils::parseRange( array_rand( array_flip( $this->ipv6Ranges ) ) );
		$ip = '';
		$seenDifference = false;
		$lastOnEdgeOfRange = false;
		for ( $i = 0; $i < strlen( $start ); $i++ ) {
			if ( !$seenDifference && $start[$i] === $end[$i] ) {
				// Same character in both end and start of range
				// therefore the randomly selected IPv6 must have
				// this character
				$ip .= $start[$i];
			} elseif ( !$seenDifference ) {
				// Not the same character, but this is the first difference
				// seen in the characters between $start and $end so far.
				//
				// Choose a random hex character between the hex characters in
				// $start and $end.
				$startAtiAsDec = hexdec( $start[$i] );
				$endAtiAsDec = hexdec( $end[$i] );
				$newHexCharacter = dechex( $this->mtRand( $startAtiAsDec, $endAtiAsDec ) );
				$ip .= $newHexCharacter;
				$seenDifference = true;
				// If the randomly selected hex character is the same as the
				// start of the end character, then the next hex character
				// must be greater than or less than respectively than
				// the character at $i.
				if ( $newHexCharacter == $start[$i] ) {
					$lastOnEdgeOfRange = 'start';
				} elseif ( $newHexCharacter == $end[$i] ) {
					$lastOnEdgeOfRange = 'end';
				}
			} elseif ( $lastOnEdgeOfRange === 'start' ) {
				// Ensure the random selection never exceeds the value
				// at $start[$i]. This is to prevent the IP being outside the range.
				$startAtiAsDec = hexdec( $start[$i] );
				$newHexCharacter = dechex( $this->mtRand( $startAtiAsDec, 15 ) );
				$ip .= $newHexCharacter;
				if ( $newHexCharacter !== $start[$i] ) {
					$lastOnEdgeOfRange = false;
				}
			} elseif ( $lastOnEdgeOfRange === 'end' ) {
				// Ensure the random selection never exceeds the value
				// at $end[$i]. This is to prevent the IP being outside the range.
				$endAtiAsDec = hexdec( $end[$i] );
				$newHexCharacter = dechex( $this->mtRand( 0, $endAtiAsDec ) );
				$ip .= $newHexCharacter;
				if ( $newHexCharacter !== $end[$i] ) {
					$lastOnEdgeOfRange = false;
				}
			} else {
				// Randomly choose any hex character.
				$ip .= dechex( $this->mtRand( 0, 15 ) );
			}
		}
		$ip = IPUtils::formatHex( $ip );
		return $ip;
	}

	/**
	 * This method randomly chooses a User-Agent header string, assigns that
	 * to the request and then applies Client Hints headers if the browser
	 * that uses the selected User-Agent supports Client Hints.
	 *
	 * @return void
	 */
	private function getNewUserAgentAndAssociatedClientHints(): void {
		$userAgent = array_rand( $this->userAgentsToClientHintsMap );
		$this->mainRequest->setHeader( 'User-Agent', $userAgent );
		/** @var ?ClientHintsData $clientHintsData */
		$clientHintsData = $this->userAgentsToClientHintsMap[$userAgent];
		// Unset any existing Client Hints data.
		$clientHintHeadersToUnset = array_filter(
			array_keys( $this->mainRequest->getAllHeaders() ),
			static fn ( $headerName ) => str_starts_with( $headerName, 'SEC-CH-UA' )
		);
		foreach ( $clientHintHeadersToUnset as $clientHintHeader ) {
			$this->mainRequest->setHeaders( [ $clientHintHeader => false ] );
		}
		if ( $clientHintsData !== null ) {
			// Set the Client Hints headers in the faux request.
			$clientHintHeadersToSet = array_filter( array_keys(
				$this->getConfig()->get( 'CheckUserClientHintsHeaders' )
			) );
			foreach ( $clientHintHeadersToSet as $clientHintHeader ) {
				$propertyName = ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME[$clientHintHeader];
				$this->mainRequest->setHeader(
					$clientHintHeader, $clientHintsData->jsonSerialize()[$propertyName]
				);
			}
		}
		$this->currentClientHintsData = $clientHintsData;
	}

	/**
	 * Perform a insert batch for a given actor. The inserts will stop and the method
	 * will return early if the actions performed reaches $actionsLeft.
	 *
	 * This method inserts edits by actually performing the edit. It uses ManualLogEntry
	 * to create log entries that are visible in Special:Log. Other log events, such as
	 * logging in, which are not shown in Special:Log are created by calling the
	 * specified hook handler.
	 *
	 * @param UserIdentity $actor The user/IP/temporary account that will perform these actions
	 * @param int &$actionsLeft Must be greater than 0. Represents the number of actions left.
	 * @param ?int $lowerLimit The furthest ago the random fake time can be from the current time in seconds.
	 * @return int The actions actually performed in this batch.
	 */
	private function performInsertBatch( UserIdentity $actor, int &$actionsLeft, ?int $lowerLimit = null ): int {
		$this->setNewRandomFakeTime( $lowerLimit );
		if ( $this->getRandomFloat() < 0.3 ) {
			// Assign a new user agent and client hints combo 30% of the time
			$this->getNewUserAgentAndAssociatedClientHints();
		}
		$services = $this->getServiceContainer();
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $services->getService( 'UserAgentClientHintsManager' );
		$actorAsUserObject = $services->getUserFactory()->newFromUserIdentity( $actor );
		$actionsPerformed = 0;
		// Simulate a failed login 10% of the time.
		if ( $actor->isRegistered() && $this->getRandomFloat() < 0.1 ) {
			$failReasons = [];
			// Simulate good password 30% of the time.
			if ( $this->getRandomFloat() < 0.3 ) {
				$failReasons[] = "good password";
				// The phrase "locked" comes from the CentralAuth extension and indicates that
				// the account login was made on an account that was locked but the request
				// otherwise used the correct password.
				$failReasons[] = "locked";
			} else {
				$failReasons[] = "bad password";
			}
			$this->privateEventsHandler->onAuthManagerLoginAuthenticateAudit(
				AuthenticationResponse::newFail( wfMessage( 'test' ), $failReasons ),
				$actorAsUserObject,
				$actor->getName(),
				[]
			);
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		if ( $actor->isRegistered() ) {
			// Simulate a login.
			$this->privateEventsHandler->onAuthManagerLoginAuthenticateAudit(
				AuthenticationResponse::newPass( $actor->getName() ),
				$actorAsUserObject,
				$actor->getName(),
				[]
			);
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		// Perform a random number of edits, capped at 3.
		$editsToPerform = intval( $this->getRandomFloat() * 3 );
		if ( !$actor->isRegistered() ) {
			// Always perform at least one edit if an anon user.
			$editsToPerform += 1;
		}
		foreach ( range( 0, $editsToPerform ) as $ignored ) {
			$title = null;
			if ( $this->getRandomFloat() < 0.3 ) {
				$title = Title::newFromText( $this->getPrefix() . 'Existing page' );
			}
			$revisionId = $this->performEdit( $actorAsUserObject, $title );
			// Send a REST API request for the edit with Client Hints data, if there is data specified.
			if ( $this->currentClientHintsData !== null && $revisionId !== null ) {
				$userAgentClientHintsManager->insertClientHintValues(
					ClientHintsData::newFromJsApi( $this->currentClientHintsData->jsonSerialize() ),
					$revisionId,
					'revision'
				);
			}
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		// Simulate a random number of log actions, capped at 2.
		$logsToPerform = intval( $this->getRandomFloat() * 2 );
		foreach ( range( 0, $logsToPerform ) as $ignored ) {
			if ( $actor->isRegistered() ) {
				$type = array_rand( self::VALID_LOG_EVENTS );
			} else {
				$type = 'move';
			}
			$action = array_rand( array_flip( self::VALID_LOG_EVENTS[$type] ) );
			$this->simulateLogAction( $type, $action, $actor );
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		// Simulate an email 10% of the time
		if ( $actor->isRegistered() && $this->getRandomFloat() < 0.1 ) {
			$from = MailAddress::newFromUser( $actorAsUserObject );
			$to = MailAddress::newFromUser( $this->userToEmailAndSendPasswordResetsFor );
			$subject = 'Test';
			$text = wfRandomString();
			$error = [];
			$this->privateEventsHandler->onEmailUser( $to, $from, $subject, $text, $error );
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		// Send password reset 10% of the time.
		if ( $this->getRandomFloat() < 0.1 ) {
			$this->privateEventsHandler->onUser__mailPasswordInternal(
				$actorAsUserObject,
				RequestContext::getMain()->getRequest()->getIP(),
				$this->userToEmailAndSendPasswordResetsFor
			);
			if ( !$this->incrementAndCheck( $actionsPerformed, $actionsLeft ) ) {
				return $actionsPerformed;
			}
		}
		// Logout 50% of the time
		if ( $actor->isRegistered() && $this->getRandomFloat() < 0.5 ) {
			$html = '';
			$anonUser = $services->getUserFactory()->newFromName(
				RequestContext::getMain()->getRequest()->getIP(),
				UserRigorOptions::RIGOR_NONE
			);
			if ( $anonUser ) {
				$this->privateEventsHandler->onUserLogoutComplete( $anonUser, $html, $actor->getName() );
			}
			$this->incrementAndCheck( $actionsPerformed, $actionsLeft );
		}
		return $actionsPerformed;
	}

	/**
	 * Simulate a log action by creating an entry in Special:Log but not actually
	 * performing the action that is referenced in the log entry.
	 *
	 * Then tell CheckUser about this log entry, so that it is stored in the
	 * results list.
	 *
	 * @param string $type The log type
	 * @param string $action The log subtype (otherwise known as action)
	 * @param UserIdentity $actor The intended performer of this log action.
	 * @return void
	 */
	private function simulateLogAction( string $type, string $action, UserIdentity $actor ): void {
		$logEntry = new ManualLogEntry( $type, $action );
		$logEntry->setPerformer( $actor );
		$logEntry->setTarget( Title::newFromText( $this->getPrefix() . 'Existing page' ) );
		$logEntry->setComment( wfRandomString() );
		if ( $type === 'move' ) {
			$logEntry->setParameters( [
				'4::target' => $this->getPrefix() . wfRandomString(),
				'5::noredir' => '0'
			] );
		} elseif ( $type === 'merge' ) {
			$logEntry->setParameters( [
				'4::dest' => $this->getPrefix() . wfRandomString(),
				'5::mergepoint' => $logEntry->getTimestamp()
			] );
		} elseif ( $type === 'delete' && $action === 'undelete' ) {
			$logEntry->setParameters( [
				':assoc:count' => [
					'revisions' => 123,
					'files' => 1,
				],
			] );
		}
		$id = $logEntry->insert();
		$this->recentChangeSaveHandler->onRecentChange_save( $logEntry->getRecentChange( $id ) );
	}

	/**
	 * Actually perform an edit using the given actor
	 * that is published to Special:RecentChanges (and
	 * then by extension Special:CheckUser)
	 *
	 * @param User $actor The user which is performing the edit
	 * @param Title|null $title The title of the page the edit will be performed on. Use null for a random title.
	 * @return int|null The revision ID of the edit if successful, otherwise null.
	 */
	private function performEdit( User $actor, ?Title $title ): ?int {
		$tags = 0;
		if ( $this->getRandomFloat() < 0.5 ) {
			// Add minor edit flag 50% of the time.
			$tags = EDIT_MINOR;
		}
		$title ??= Title::newFromText( $this->getPrefix() . wfRandomString() );
		if ( !$title ) {
			return null;
		}
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$status = $page->doUserEditContent(
			ContentHandler::makeContent(
				wfRandomString(),
				$title,
				// Regardless of how the wiki is configure or what extensions are present,
				// force this page to be a wikitext one.
				CONTENT_MODEL_WIKITEXT
			),
			$actor,
			wfRandomString(),
			$tags
		);
		if ( !$status->isOK() ) {
			return null;
		}
		return $status->getNewRevision()->getId();
	}

	/**
	 * Increment the actions performed counter, move the fake time forward
	 * by a random time no greater than 240 seconds and then check if more
	 * actions can be performed by checking against the second parameter
	 *
	 * @param int &$actionsPerformed The number of actions performed on this insert batch
	 * @param int $actionsLeft The number of actions left to perform for this actor
	 * @return bool Whether more actions can be performed
	 */
	private function incrementAndCheck( int &$actionsPerformed, int $actionsLeft ): bool {
		$actionsPerformed++;
		$this->moveFakeTimeForward();
		return $actionsPerformed < $actionsLeft;
	}

	/**
	 * Set the time to a fake time between now and CUDMaxAge seconds ago.
	 *
	 * @param ?int $lowerLimit The maximum number of seconds ago this random timestamp can be. An hour
	 *  is always added to this number.
	 * @return void
	 */
	private function setNewRandomFakeTime( ?int $lowerLimit = null ): void {
		// Clear any fake time (to allow the ConvertibleTimestamp::time() call to use the real time).
		ConvertibleTimestamp::setFakeTime( false );
		// Set the new fake time
		//
		// Ensure the new fake time is at least an hour ago from the actual time.
		$newFakeTime = ConvertibleTimestamp::time() - 3600;
		// Ensure the new fake time is appropriately chosen from any time period results can be in.
		if ( $lowerLimit === null ) {
			// Default is to ensure random time cannot be more than CUDMaxAge seconds ago minus 2 hours.
			$lowerLimit = $this->getConfig()->get( 'CUDMaxAge' ) - ( 3600 * 2 );
		}
		$newFakeTime -= intval( $this->getRandomFloat() * $lowerLimit );
		ConvertibleTimestamp::setFakeTime( $newFakeTime );
	}

	/**
	 * Move the fake time forward by a random number of seconds between 0 and 240 seconds.
	 *
	 * @return void
	 */
	private function moveFakeTimeForward(): void {
		ConvertibleTimestamp::setFakeTime(
			ConvertibleTimestamp::time() + intval( $this->getRandomFloat() * 239 ) + 1
		);
	}

	/**
	 * Initialise the User-Agent header and Client Hints combinations
	 * as the ClientHints objects cannot be created in a constant property.
	 *
	 * @return void
	 */
	private function initUserAgentAndClientHintsCombos(): void {
		$this->userAgentsToClientHintsMap = [
			'Mozilla/5.0 (iPhone13,2; U; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/602.1.50 ' .
			'(KHTML, like Gecko) Version/10.0 Mobile/15E148 Safari/602.1' =>
				null,
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/116.0' =>
				null,
		];
		$this->userAgentsToClientHintsMap[
			'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) ' .
			'Chrome/115.0.0.0 Mobile Safari/537.36'
		] = new ClientHintsData(
			"",
			"64",
			[
				[ "brand" => "Not/A)Brand", "version" => "99" ],
				[ "brand" => "Google Chrome", "version" => "115" ],
				[ "brand" => "Chromium", "version" => "115" ],
			],
			null,
			[
				[ "brand" => "Not/A)Brand", "version" => "99.0.0.0" ],
				[ "brand" => "Google Chrome", "version" => "115.0.5790.171" ],
				[ "brand" => "Chromium", "version" => "115.0.5790.171" ],
			],
			true,
			"SM-G965U",
			"Android",
			"10.0.0",
			false
		);
		$this->userAgentsToClientHintsMap[
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
			'Chrome/112.0.0.0 Safari/537.36 OPR/98.0.0.0'
		] = new ClientHintsData(
			"x86",
			"64",
			[
				[ "brand" => "Chromium", "version" => "112" ],
				[ "brand" => "Not_A Brand", "version" => "24" ],
				[ "brand" => "Opera GX", "version" => "98" ],
			],
			null,
			[
				[ "brand" => "Chromium", "version" => "112.0.5615.165" ],
				[ "brand" => "Not_A Brand", "version" => "24.0.0.0" ],
				[ "brand" => "Opera GX", "version" => "98.0.4759.82" ],
			],
			false,
			"",
			"Windows",
			"15.0.0",
			false
		);
		$this->userAgentsToClientHintsMap[
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
			'Chrome/115.0.0.0 Safari/537.36'
		] = new ClientHintsData(
			"x86",
			"64",
			[
				[ "brand" => "Not/A)Brand", "version" => "99" ],
				[ "brand" => "Google Chrome", "version" => "115" ],
				[ "brand" => "Chromium", "version" => "115" ],
			],
			null,
			[
				[ "brand" => "Not/A)Brand", "version" => "99.0.0.0" ],
				[ "brand" => "Google Chrome", "version" => "115.0.5790.171" ],
				[ "brand" => "Chromium", "version" => "115.0.5790.171" ],
			],
			false,
			"",
			"Windows",
			"15.0.0",
			false
		);
		$this->userAgentsToClientHintsMap[
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
			'Chrome/114.0.0.0 Safari/537.36'
		] = new ClientHintsData(
			"x86",
			null,
			[
				[ "brand" => "Not/A)Brand", "version" => "99" ],
				[ "brand" => "Google Chrome", "version" => "114" ],
				[ "brand" => "Chromium", "version" => "114" ],
			],
			null,
			null,
			false,
			"",
			"Windows",
			null,
			null
		);
	}

	private function getPrefix(): string {
		return 'CheckUserSimulated-';
	}
}

$maintClass = PopulateCheckUserTablesWithSimulatedData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
