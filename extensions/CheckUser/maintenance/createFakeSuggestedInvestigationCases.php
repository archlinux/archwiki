<?php

namespace MediaWiki\CheckUser\Maintenance;

use LogicException;
use MediaWiki\Auth\AuthManager;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWiki\User\UserRigorOptions;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Populates the Suggested investigation database tables with fake data useful for testing.
 *
 * WARNING: This should never be run on production wikis. This is intended only for
 * local testing wikis where the DB can be cleared without issue.
 *
 * @codeCoverageIgnore This is a script intended to only be run on development wikis, so we do not need to track
 * PHPUnit test coverage for it.
 */
class CreateFakeSuggestedInvestigationCases extends Maintenance {

	private const VALID_SIGNALS = [
		[ 'name' => 'dev-signal-1', 'maxUsersInCaseWithThisSignal' => 1, 'valueType' => 'boolean' ],
		[ 'name' => 'dev-signal-2', 'maxUsersInCaseWithThisSignal' => INF, 'valueType' => 'string' ],
	];

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Creates fake data that is inserted into the suggested investigation database tables. ' .
			'Useful for testing the feature.'
		);
		$this->addOption(
			'num-cases',
			'How many cases should be created. Default is 10.',
			false,
			true
		);
		$this->addOption(
			'max-users-per-case',
			'The maximum number of users that can be in one case. ' .
			'The number of users to add to a case is randomly chosen between 0 and this number. Default is 20.',
			false,
			true
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

		$numCases = $this->ensureArgumentIsInt(
			$this->getOption( 'num-cases', 10 ),
			'Number of cases'
		);
		$maxUsersPerCase = $this->ensureArgumentIsInt(
			$this->getOption( 'max-users-per-case', 20 ),
			'Maximum number of users in any created case'
		);

		$tempAccountsEnabled = $this->getServiceContainer()->getTempUserConfig()->isEnabled();
		/** @var SuggestedInvestigationsCaseManagerService $suggestedInvestigationsCaseManager */
		$suggestedInvestigationsCaseManager = $this->getServiceContainer()->get(
			'CheckUserSuggestedInvestigationsCaseManager'
		);

		for ( $i = 0; $i < $numCases; $i++ ) {
			// Randomly choose which signal to use out of the list of valid signals.
			$randomSignalIndex = intval( $this->getRandomFloat() * count( self::VALID_SIGNALS ) );
			$signalData = self::VALID_SIGNALS[$randomSignalIndex];

			$signalValue = match ( $signalData['valueType'] ) {
				'boolean' => true,
				'string' => 'abc',
				default => throw new LogicException( "Unknown value type provided for signal {$signalData['name']}" )
			};

			$matchedSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				$signalData['name'], $signalValue, false
			);

			// Generate the users for the suggested investigations case
			$numberOfUsersToCreate = min(
				$this->getRandomFloat() * $maxUsersPerCase,
				$signalData['maxUsersInCaseWithThisSignal']
			);

			$users = [];
			for ( $j = 0; $j < $numberOfUsersToCreate; $j++ ) {
				if ( $this->getRandomFloat() < 0.5 || !$tempAccountsEnabled ) {
					$users[] = $this->createRegisteredUser();
				} else {
					$users[] = $this->getServiceContainer()->getTempUserCreator()
						->create( null, new FauxRequest() )->getUser();
				}
			}

			// Create the suggested investigations case
			$suggestedInvestigationsCaseManager->createCase( $users, [ $matchedSignal ] );
		}
	}

	/**
	 * Ensure an argument provided via the command line is an integer.
	 * If it is not, then exit the script with a fatal error message.
	 *
	 * @param mixed $argument The argument from the command line (usually in string form)
	 * @param string $name The name of the argument used if the argument is not an integer
	 *   in the fatal error message.
	 * @return int The argument as an integer (exit is called if the argument was invalid).
	 */
	private function ensureArgumentIsInt( mixed $argument, string $name ): int {
		if ( !$argument || !intval( $argument ) ) {
			$this->fatalError( "$name must be an integer" );
		}

		return intval( $argument );
	}

	/**
	 * Create a user on the wiki with a username prefixed with CheckUserSimulated and then a random string of
	 * hexadecimal characters.
	 *
	 * @return ?User A user that has just been created or null if this failed.
	 */
	private function createRegisteredUser(): ?User {
		$services = $this->getServiceContainer();
		// Find a username that doesn't exist.
		$attemptsMade = 0;
		do {
			$user = $services->getUserFactory()->newFromName(
				'CheckUserSimulated-' . wfRandomString(), UserRigorOptions::RIGOR_CREATABLE
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
	 * @return float A float in the range [0, 1)
	 */
	protected function getRandomFloat(): float {
		return floatval( wfRandom() );
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreateFakeSuggestedInvestigationCases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
