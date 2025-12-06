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

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentity;
use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class SuggestedInvestigationsCaseManagerService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly IConnectionProvider $dbProvider,
		private readonly SuggestedInvestigationsInstrumentationClient $instrumentationClient,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Inserts a new Suggested Investigations case to the database with the provided users and signals.
	 *
	 * For now, only cases with one signal are supported, so the $signals array must contain exactly one element.
	 * @throws InvalidArgumentException If $users is empty or more than one signal is provided, this throws.
	 * @param UserIdentity[] $users
	 * @phan-param non-empty-array $users
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 * @phan-param non-empty-array $signals
	 * @return int The ID of the created case
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
			$dbw->newInsertQueryBuilder()
				->insert( 'cusi_case' )
				->row( [
					'sic_created_timestamp' => $dbw->timestamp(),
				] )
				->caller( __METHOD__ )
				->execute();
			$caseId = $dbw->insertId();

			// We don't need to check if the case exists, so let's just use the internal versions of methods
			$this->addUsersToCaseInternal( $caseId, $users );
			$this->addSignalsToCaseInternal( $caseId, $signals );
			$dbw->endAtomic( __METHOD__ );
		} catch ( \Exception $e ) {
			// Ensure we cancel the atomic block if an exception is thrown
			$dbw->cancelAtomic( __METHOD__ );
			throw $e;
		}

		$this->createInstrumentationEvent(
			'case_open',
			null,
			[
				'case_id' => $caseId,
				'signals' => array_map( static fn ( $signal ) => $signal->getName(), $signals ),
				'number_of_users' => count( $users ),
			]
		);

		return $caseId;
	}

	/**
	 * Adds an array of users to an existing Suggested Investigations case. If a user
	 * is already attached to the case, they will not be added again.
	 * @throws InvalidArgumentException When $caseId does not match an existing case
	 * @param int $caseId
	 * @param UserIdentity[] $users
	 */
	public function addUsersToCase( int $caseId, array $users ): void {
		$this->assertSuggestedInvestigationsEnabled();
		$this->assertCaseExists( $caseId );

		if ( count( $users ) === 0 ) {
			return;
		}

		$instrumentationContext = [
			'case_id' => $caseId,
			'signals' => $this->getSignalNamesInCase( $caseId ),
			'number_of_users' => $this->getNumberOfUsersInCase( $caseId ),
		];

		$instrumentationContext['number_of_users'] += $this->addUsersToCaseInternal( $caseId, $users );

		$this->createInstrumentationEvent( 'case_updated', null, $instrumentationContext );
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
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException if $caseId does not match an existing case.
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function setCaseStatus( int $caseId, CaseStatus $status, string $reason = '' ): void {
		$this->assertSuggestedInvestigationsEnabled();
		$this->assertCaseExists( $caseId );

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
			] )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();

		// Track when statuses are changed on cases
		if ( $oldCaseStatus !== $status->value ) {
			$this->createInstrumentationEvent(
				'case_status_change',
				strtolower( $status->name ),
				[
					'case_id' => $caseId,
					'signals' => $this->getSignalNamesInCase( $caseId ),
					'number_of_users' => $this->getNumberOfUsersInCase( $caseId ),
					'has_note' => trim( $reason ) !== '',
				]
			);
		}
	}

	/**
	 * Adds users to a case, skipping the input data checks.
	 * @param int $caseId
	 * @param UserIdentity[] $users
	 * @return int The number of users who were actually added to the case
	 */
	private function addUsersToCaseInternal( int $caseId, array $users ): int {
		$dbw = $this->getPrimaryDatabase();

		// Using array_values to silence Phan warning about $rows being associative
		$rows = array_map( static fn ( $user ) => [
			'siu_sic_id' => $caseId,
			'siu_user_id' => $user->getId(),
		], array_values( $users ) );

		$dbw->newInsertQueryBuilder()
			->insert( 'cusi_user' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
		return $dbw->affectedRows();
	}

	/**
	 * Adds signals to a case, skipping the input data checks.
	 *
	 * Currently, there's no public method to add a signal, as we support only having a single signal
	 * on a case. Once that changes, we can expose a public method, similar to {@link addUsersToCase}.
	 *
	 * @param int $caseId
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 */
	private function addSignalsToCaseInternal( int $caseId, array $signals ): void {
		$dbw = $this->getPrimaryDatabase();

		// Using array_values to silence Phan warning about $rows being associative
		$rows = array_map( static fn ( $signal ) => [
			'sis_sic_id' => $caseId,
			'sis_name' => $signal->getName(),
			'sis_value' => $signal->getValue(),
		], array_values( $signals ) );

		$dbw->newInsertQueryBuilder()
			->insert( 'cusi_signal' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/** Helper function to check if a case with given ID exists */
	private function caseExists( int $caseId ): bool {
		$dbr = $this->getReplicaDatabase();
		$rowCount = $dbr->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		return $rowCount > 0;
	}

	/**
	 * Asserts that a case with the given ID exists.
	 *
	 * @param int $caseId ID for the case to test for.
	 * @return void
	 *
	 * @throws InvalidArgumentException if $caseId does not match an existing case
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	private function assertCaseExists( int $caseId ): void {
		if ( !$this->caseExists( $caseId ) ) {
			throw new InvalidArgumentException( "Case ID $caseId does not exist" );
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
	 * Creates an interaction event in the Suggested Investigations interaction stream.
	 * Only does something if the EventLogging extension is installed.
	 *
	 * @param string $action The action parameter for {@link MetricsClient::submitInteraction}
	 * @param string|null $actionSubtype The value of the action_subtype key for the interaction data
	 *   passed to {@link MetricsClient::submitInteraction}. If `null`, then the key is not added to the array.
	 * @param array $actionContext The value of the action_context key for the interaction data passed to
	 *   {@link MetricsClient::submitInteraction}
	 */
	private function createInstrumentationEvent( string $action, ?string $actionSubtype, array $actionContext ): void {
		$interactionData = [ 'action_context' => json_encode( $actionContext ) ];
		if ( $actionSubtype !== null ) {
			$interactionData['action_subtype'] = $actionSubtype;
		}

		$this->instrumentationClient->submitInteraction( RequestContext::getMain(), $action, $interactionData );
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
			->from( 'cusi_signal' )
			->where( [ 'sis_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Gets the count of all users in a given case, using the cusi_user table as the data source
	 *
	 * Used for instrumentation events only, so not added to
	 * {@link SuggestedInvestigationsCaseLookupService} and then made inherently stable
	 */
	private function getNumberOfUsersInCase( int $caseId ): int {
		$dbr = $this->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
	}
}
