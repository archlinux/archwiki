<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\CaseNotFoundException;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\ISuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseUser;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\RawSQLValue;

class SuggestedInvestigationsCaseManagerService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	/**
	 * When cusi_signal.sis_trigger_type has this value,
	 * it means that cusi_signal.sis_trigger_id is a revision ID.
	 */
	public const TRIGGER_TYPE_REVISION = 1;
	/**
	 * When cusi_signal.sis_trigger_type has this value,
	 * it means that cusi_signal.sis_trigger_id is a logging table ID.
	 */
	public const TRIGGER_TYPE_LOGGING = 2;

	/**
	 * @var string[] Maps values of cusi_signal.sis_trigger_type to the database table they represent
	 */
	public const TRIGGER_TYPE_TO_TABLE_NAME_MAP = [
		self::TRIGGER_TYPE_REVISION => 'revision',
		self::TRIGGER_TYPE_LOGGING => 'logging',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly IConnectionProvider $dbProvider,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly ISuggestedInvestigationsInstrumentationClient $instrumentationClient,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Inserts a new Suggested Investigations case to the database with the provided users and signals.
	 *
	 * For now, only cases with one signal are supported, so the $signals array must contain exactly one element.
	 *
	 * @param UserIdentity[]|SuggestedInvestigationsCaseUser[] $users
	 * @phan-param non-empty-array $users
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 * @phan-param non-empty-array $signals
	 * @return int The ID of the created case
	 * @throws InvalidArgumentException If $users is empty or more than one signal is provided, this throws.
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function createCase( array $users, array $signals ): int {
		$this->assertSuggestedInvestigationsEnabled();
		if ( count( $users ) === 0 ) {
			throw new InvalidArgumentException( 'At least one user must be provided to create a case' );
		}
		if ( count( $signals ) !== 1 ) {
			throw new InvalidArgumentException( 'Exactly one signal must be provided to create a case' );
		}

		$dbw = $this->getPrimaryDatabase();

		try {
			$dbw->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );

			$caseUrlIdentifier = $this->generateUniqueUrlIdentifier();

			$dbw->newInsertQueryBuilder()
				->insert( 'cusi_case' )
				->row( [
					'sic_url_identifier' => $caseUrlIdentifier,
					'sic_created_timestamp' => $dbw->timestamp(),
					'sic_updated_timestamp' => $dbw->timestamp(),
				] )
				->caller( __METHOD__ )
				->execute();
			$caseId = $dbw->insertId();

			// We don't need to check if the case exists, so let's just use the internal versions of methods
			$this->addUsersToCaseInternal( $caseId, $this->convertUsersToCaseUsers( $users ) );
			$this->addSignalsToCaseInternal( $caseId, $signals );
			$dbw->endAtomic( __METHOD__ );
		} catch ( \Exception $e ) {
			// Ensure we cancel the atomic block if an exception is thrown
			$dbw->cancelAtomic( __METHOD__ );
			throw $e;
		}

		$this->instrumentationClient->submitInteraction(
			RequestContext::getMain(),
			'case_open',
			[
				'case_id' => $caseId,
				'case_url_identifier' => $caseUrlIdentifier,
				'signals_in_case' => array_map( static fn ( $signal ) => $signal->getName(), $signals ),
				'users_in_case' => $this->instrumentationClient->getUserFragmentsArray( $users ),
				'case_note' => '',
			]
		);

		return $caseId;
	}

	/**
	 * Adds an array of users to an existing Suggested Investigations case. If a user
	 * is already attached to the case, they will not be added again.
	 *
	 * @deprecated Since 1.46. Use {@link self::updateCase} instead
	 * @throws InvalidArgumentException When $caseId does not match an existing case
	 * @param int $caseId
	 * @param UserIdentity[] $users
	 */
	public function addUsersToCase( int $caseId, array $users ): void {
		wfDeprecated( __METHOD__, '1.46' );
		$this->updateCase( $caseId, $users, [] );
	}

	/**
	 * Updates an existing Suggested Investigations case with a list of additional users
	 * and signals.
	 *
	 * If a user is already attached to a case, they will not be added again.
	 * Any signal already in the case will not be added again (this equality check also
	 * considers the associated revision or log ID that triggered the signal).
	 *
	 * @param int $caseId
	 * @param UserIdentity[]|SuggestedInvestigationsCaseUser[] $users
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 * @throws InvalidArgumentException When $caseId does not match an existing case
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function updateCase( int $caseId, array $users, array $signals ): void {
		$this->assertSuggestedInvestigationsEnabled();
		$this->assertCasesExist( [ $caseId ] );

		if ( count( $signals ) === 0 && count( $users ) === 0 ) {
			return;
		}

		$instrumentationData = [
			'case_id' => $caseId,
			'signals_in_case' => $this->getSignalNamesInCase( $caseId ),
		];

		$usersInCase = $this->getUsersInCase( $caseId );

		if ( count( $signals ) !== 0 ) {
			$this->addSignalsToCaseInternal( $caseId, $signals );

			$instrumentationData['signals_in_case'] = array_unique( array_merge(
				$instrumentationData['signals_in_case'],
				array_map( static fn ( $signal ) => $signal->getName(), $signals )
			) );
		}

		if ( count( $users ) !== 0 ) {
			$this->addUsersToCaseInternal( $caseId, $this->convertUsersToCaseUsers( $users ) );

			$newUsers = array_udiff(
				$users,
				$usersInCase,
				static fn ( UserIdentity $a, UserIdentity $b ) => $a->getId() - $b->getId()
			);
			$usersInCase = array_merge( $usersInCase, $newUsers );
		}

		$instrumentationData['users_in_case'] = $this->instrumentationClient->getUserFragmentsArray(
			$usersInCase
		);

		$dbw = $this->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_updated_timestamp' => $dbw->timestamp() ] )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();

		$this->instrumentationClient->submitInteraction(
			RequestContext::getMain(),
			'case_updated',
			$instrumentationData
		);
	}

	/**
	 * Changes the status of a given case.
	 *
	 * Note we don't currently restrict what status transitions are allowed
	 * (for example, a Resolved case may be set back to Open).
	 *
	 * @param int $caseId The ID of the case to modify.
	 * @param CaseStatus $status The new case status.
	 * @param string $reason Optionally, a reason for the status change.
	 * @param int $performerUserId The user ID of the performer, or 0 for system actions.
	 *
	 * @return void
	 *
	 * @throws CaseNotFoundException if $caseId does not match an existing case.
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function setCaseStatus(
		int $caseId,
		CaseStatus $status,
		string $reason = '',
		int $performerUserId = 0
	): void {
		$this->assertSuggestedInvestigationsEnabled();
		$this->assertCasesExist( [ $caseId ] );

		$dbr = $this->getPrimaryDatabase();
		$oldCaseStatus = (int)$dbr->newSelectQueryBuilder()
			->select( 'sic_status' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		$dbw = $this->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->table( 'cusi_case' )
			->set( [
				'sic_status' => $status->value,
				'sic_status_reason' => trim( $reason ),
				'sic_status_changed_by' => $performerUserId,
			] )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();

		// Track when statuses are changed on cases
		if ( $oldCaseStatus !== $status->value ) {
			$interactionData = [
				'action_subtype' => strtolower( $status->name ),
				'case_id' => $caseId,
				'case_note' => $reason,
			];

			if ( $performerUserId !== 0 ) {
				$interactionData['performer'] = [ 'id' => $performerUserId ];
			}

			$this->instrumentationClient->submitInteraction(
				RequestContext::getMain(),
				'case_status_change',
				$interactionData
			);
		}
	}

	/**
	 * Adds users to a case, skipping the input data checks.
	 * @param int $caseId
	 * @param SuggestedInvestigationsCaseUser[] $users
	 */
	private function addUsersToCaseInternal( int $caseId, array $users ): void {
		$dbw = $this->getPrimaryDatabase();

		// We need to perform the INSERTs grouped by the value being set as siu_info so that we can update it
		// using a bitwise operator on a duplicate key violation.
		$userInfoFlagsToUserIds = [];
		foreach ( $users as $user ) {
			if ( !array_key_exists( $user->getUserInfoBitFlags(), $userInfoFlagsToUserIds ) ) {
				$userInfoFlagsToUserIds[$user->getUserInfoBitFlags()] = [];
			}

			$userInfoFlagsToUserIds[$user->getUserInfoBitFlags()][] = $user->getId();
		}

		foreach ( $userInfoFlagsToUserIds as $userInfoFlags => $userIds ) {
			$dbw->newInsertQueryBuilder()
				->insert( 'cusi_user' )
				->rows( array_map( static fn ( $userId ) => [
					'siu_sic_id' => $caseId,
					'siu_user_id' => $userId,
					'siu_info' => $userInfoFlags,
				], $userIds ) )
				->onDuplicateKeyUpdate()
				->uniqueIndexFields( [ 'siu_sic_id', 'siu_user_id' ] )
				// Note that passing the $userInfoFlags as raw SQL is fine because the value
				// is an PHP integer and so cannot contain SQL injection
				->set( [ 'siu_info' => new RawSQLValue( 'siu_info | ' . $userInfoFlags ) ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Adds signals to a case, skipping the input data checks.
	 *
	 * @param int $caseId
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 */
	private function addSignalsToCaseInternal( int $caseId, array $signals ): void {
		$dbw = $this->getPrimaryDatabase();

		// Using array_values to silence Phan warning about $rows being associative
		$rows = array_map( fn ( $signal ) => [
			'sis_sic_id' => $caseId,
			'sis_name' => $signal->getName(),
			'sis_value' => $signal->getValue(),
			'sis_trigger_id' => $signal->getTriggerId(),
			'sis_trigger_type' => $this->getTriggerTypeByDatabaseTable( $signal->getTriggerIdTable() ),
		], array_values( $signals ) );

		$dbw->newInsertQueryBuilder()
			->insert( 'cusi_signal' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Given a database table (e.g. 'revision'), return the value of cusi_signal.sis_trigger_type that
	 * corresponds to that database table. When the database table is an empty string, this will be
	 * interpreted as the signal having no trigger ID and so 0 is returned to represent this.
	 *
	 * @param string $databaseTable
	 * @return int One of self::TRIGGER_TYPE_* constants, or 0 if $databaseTable is an empty string
	 */
	private function getTriggerTypeByDatabaseTable( string $databaseTable ): int {
		if ( $databaseTable === '' ) {
			return 0;
		}

		return array_flip( self::TRIGGER_TYPE_TO_TABLE_NAME_MAP )[$databaseTable]
			?? throw new InvalidArgumentException( "Unrecognised database table $databaseTable" );
	}

	/**
	 * Returns a random integer that can be used in the sic_url_identifier column of the cusi_case table.
	 * This random integer will never be 0, as this indicates that a URL identifier has not been set yet.
	 *
	 * This does not validate that the value has already been used in an existing cusi_case row.
	 * Callers are expected to validate this before attempting to set the value.
	 */
	protected function generateUrlIdentifier(): int {
		return random_int( 1, $this->getMaxInteger() );
	}

	/**
	 * Generates a random integer that can be used as the value of sic_url_identifier in a new
	 * cusi_case row.
	 *
	 * This method checks that the returned integer is not used in any existing cusi_case row.
	 *
	 * @internal Only public to allow use in {@link PopulateSicUrlIdentifier}
	 * @param bool $usePrimary Whether to use a primary DB connection to determine if
	 *   sic_url_identifier is already in use. If false, uses a replica DB connection.
	 */
	public function generateUniqueUrlIdentifier( bool $usePrimary = false ): int {
		if ( $usePrimary ) {
			$db = $this->getPrimaryDatabase();
		} else {
			$db = $this->getReplicaDatabase();
		}

		do {
			$urlIdentifier = $this->generateUrlIdentifier();

			$isUrlIdentifierAlreadyInUse = $db->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cusi_case' )
				->where( [ 'sic_url_identifier' => $urlIdentifier ] )
				->caller( __METHOD__ )
				->fetchField();
		} while ( $isUrlIdentifierAlreadyInUse !== false );

		return $urlIdentifier;
	}

	/**
	 * Returns the maximum length of an integer column for the given database type ensuring that
	 * the maximum value does not translate to a hexadecimal string of anything longer than
	 * 8 characters.
	 *
	 * Needed because PostgreSQL does not support unsigned integers and so cannot store as large
	 * integer as other DB types.
	 */
	private function getMaxInteger(): int {
		return match ( $this->getPrimaryDatabase()->getType() ) {
			'postgres' => 2147483647,
			default => 4294967295,
		};
	}

	/**
	 * Updates the `sic_updated_timestamp` of each case in $caseIds to the current time.
	 * This surfaces cases in the queue without adding signals or users.
	 *
	 * @param int[] $caseIds
	 */
	public function updateCasesUpdatedAtTimestamps( array $caseIds ): void {
		$this->assertSuggestedInvestigationsEnabled();

		if ( !$caseIds ) {
			return;
		}

		$this->assertCasesExist( $caseIds );

		$dbw = $this->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_updated_timestamp' => $dbw->timestamp() ] )
			->where( [ 'sic_id' => $caseIds ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Asserts that all cases with the given IDs exist.
	 *
	 * @param int[] $caseIds IDs for the cases to test for.
	 *
	 * @throws CaseNotFoundException if any ID does not match an existing case
	 */
	private function assertCasesExist( array $caseIds ): void {
		$existingIds = array_map(
			'intval',
			$this->getReplicaDatabase()->newSelectQueryBuilder()
				->select( 'sic_id' )
				->from( 'cusi_case' )
				->where( [ 'sic_id' => $caseIds ] )
				->caller( __METHOD__ )
				->fetchFieldValues()
		);
		$missingIds = array_diff( $caseIds, $existingIds );

		if ( $missingIds ) {
			throw new CaseNotFoundException(
				'Case IDs do not exist: ' . implode( ', ', $missingIds )
			);
		}
	}

	/**
	 * Helper function to return early if SI is not enabled, so we don't interact with non-existing tables in DB
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	private function assertSuggestedInvestigationsEnabled(): void {
		if ( !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			throw new RuntimeException( 'Suggested Investigations is not enabled' );
		}
	}

	/** Returns a connection to the primary database with SI tables */
	private function getPrimaryDatabase(): IDatabase {
		return $this->dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
	}

	/** Returns a connection to the replica database with SI tables */
	private function getReplicaDatabase(): IReadableDatabase {
		return $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
	}

	/**
	 * Gets a list of signals in a given case, using the cusi_signal table as the data source
	 *
	 * Used for instrumentation events only, so not added to
	 * {@link SuggestedInvestigationsCaseLookupService} and then made inherently stable
	 */
	private function getSignalNamesInCase( int $caseId ): array {
		$dbr = $this->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( 'sis_name' )
			->distinct()
			->from( 'cusi_signal' )
			->where( [ 'sis_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Gets a list of all users in a given case, using the cusi_user table as the data source
	 *
	 * Used for instrumentation events only, so not added to
	 * {@link SuggestedInvestigationsCaseLookupService} and then made inherently stable
	 *
	 * @return UserIdentity[]
	 */
	private function getUsersInCase( int $caseId ): array {
		$dbr = $this->getReplicaDatabase();
		$userIds = $dbr->newSelectQueryBuilder()
			->select( 'siu_user_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_filter( array_map(
			$this->userIdentityLookup->getUserIdentityByUserId( ... ),
			$userIds
		) );
	}

	/**
	 * Converts a list of {@link UserIdentity} objects to a list of {@link SuggestedInvestigationsCaseUser}
	 * objects. {@link SuggestedInvestigationsCaseUser} objects in the provided list are untouched, while
	 * converted {@link UserIdentity} objects have the user info bit flags set to 0 (i.e. no flags set).
	 *
	 * @param UserIdentity[]|SuggestedInvestigationsCaseUser[] $users
	 * @return SuggestedInvestigationsCaseUser[]
	 */
	private function convertUsersToCaseUsers( array $users ): array {
		return array_map( static function ( $user ) {
			if ( $user instanceof SuggestedInvestigationsCaseUser ) {
				return $user;
			}

			return new SuggestedInvestigationsCaseUser( $user, 0 );
		}, $users );
	}
}
