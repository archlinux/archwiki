<?php
/*
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
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCase;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\RawSQLExpression;

class SuggestedInvestigationsCaseLookupService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly IConnectionProvider $dbProvider,
		private readonly LoggerInterface $logger,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Looks up cases where the provided signal could be merged into that case. Ignores the allowMerging flag on the
	 * signal. Additionally, the value of sis_trigger_id and sis_trigger_type are ignored for the comparison check.
	 *
	 * @param SuggestedInvestigationsSignalMatchResult $signal
	 * @return SuggestedInvestigationsCase[]
	 * @throws InvalidArgumentException if a negative signal match is provided
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function getMergeableCasesForSignal( SuggestedInvestigationsSignalMatchResult $signal ): array {
		$this->assertSuggestedInvestigationsEnabled();

		if ( !$signal->isMatch() ) {
			throw new InvalidArgumentException( 'Cannot look up for a negative signal match' );
		}

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		// DISTINCT is needed because there may be many matching cusi_signal rows for a given cusi_case row.
		$mergeableCases = $dbr->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_status', 'sic_status_reason', 'sic_status_changed_by' ] )
			->distinct()
			->from( 'cusi_signal' )
			->join( 'cusi_case', null, 'sis_sic_id = sic_id' )
			->where( [
				'sis_name' => array_merge( [ $signal->getName() ], $signal->getEquivalentNamesForMerging() ),
				'sis_value' => $signal->getValue(),
				'sic_status' => CaseStatus::Open->value,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		return $this->getCaseObjectsFromRows( $mergeableCases );
	}

	/**
	 * Looks up cases that match a given signal exactly (though the value of sis_trigger_id and sis_trigger_type are
	 * ignored for the comparison check). Ignores the allowMerging flag on the signal.
	 *
	 * @param SuggestedInvestigationsSignalMatchResult $signal
	 * @param CaseStatus[]|null $statuses If set, only cases with these statuses will be returned.
	 * If null, all cases will be returned.
	 * @return SuggestedInvestigationsCase[]
	 * @throws InvalidArgumentException if a negative signal match is provided
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function getCasesForSignal(
		SuggestedInvestigationsSignalMatchResult $signal,
		?array $statuses = null
	): array {
		$this->assertSuggestedInvestigationsEnabled();

		if ( !$signal->isMatch() ) {
			throw new InvalidArgumentException( 'Cannot look up for a negative signal match' );
		}

		if ( $statuses === [] ) {
			return [];
		}

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		// DISTINCT is needed because there may be many matching cusi_signal rows for a given cusi_case row.
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_status', 'sic_status_reason', 'sic_status_changed_by' ] )
			->distinct()
			->from( 'cusi_signal' )
			->join( 'cusi_case', null, 'sis_sic_id = sic_id' )
			->where( [
				'sis_name' => $signal->getName(),
				'sis_value' => $signal->getValue(),
			] )
			->caller( __METHOD__ );

		// @phan-suppress-next-line PhanImpossibleTypeComparison Phan thinks null is matched by `=== []` but it's not
		if ( $statuses !== null ) {
			$queryBuilder->where( [
				'sic_status' => array_map( static fn ( $s ) => $s->value, $statuses ),
			] );
		}

		return $this->getCaseObjectsFromRows( $queryBuilder->fetchResultSet() );
	}

	/**
	 * Gets the case ID of the `cusi_case` row with the given URL identifier.
	 *
	 * @param string $urlIdentifier The URL identifier in hexadecimal format
	 * @return int|false false if no case with the given URL identifier exists or the URL identifier was invalid.
	 *   Otherwise the `sic_id` (case ID) for the matching `cusi_case` row
	 */
	public function getCaseIdForUrlIdentifier( string $urlIdentifier ): int|false {
		if ( !ctype_xdigit( $urlIdentifier ) ) {
			return false;
		}

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$rowId = $dbr->newSelectQueryBuilder()
			->select( 'sic_id' )
			->from( 'cusi_case' )
			->where( [ 'sic_url_identifier' => hexdec( $urlIdentifier ) ] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $rowId === false ) {
			return false;
		}
		return (int)$rowId;
	}

	/**
	 * Fetches the list of local wiki user IDs of users listed in a given case
	 *
	 * @return int[]
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function getUserIdsInCase( int $caseId ): array {
		$this->assertSuggestedInvestigationsEnabled();

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$userIds = $dbr->newSelectQueryBuilder()
			->select( 'siu_user_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $userIds );
	}

	/**
	 * Fetches the list of open cases Ids referenced for a given local user ID
	 *
	 * @return int[]
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function getOpenCaseIdsForUser( int $userId ): array {
		$this->assertSuggestedInvestigationsEnabled();

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$userIds = $dbr->newSelectQueryBuilder()
			->select( 'sic_id' )
			->from( 'cusi_user' )
			->join( 'cusi_case', null, 'siu_sic_id = sic_id' )
			->where( [
				'siu_user_id' => $userId,
				'sic_status' => CaseStatus::Open->value,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $userIds );
	}

	/**
	 * Gets whether the provided user is in any suggested investigations case,
	 * regardless of the status of the case.
	 *
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	public function isUserInAnyCase( UserIdentity $userIdentity ): bool {
		$this->assertSuggestedInvestigationsEnabled();

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		return (bool)$dbr->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => $userIdentity->getId() ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Returns a {@link CaseStatus} object describing the status of the provided case ID
	 *
	 * @throws RuntimeException if Suggested Investigations is not enabled
	 * @throws InvalidArgumentException if no case with the given ID exists
	 */
	public function getCaseStatus( int $caseId ): CaseStatus {
		$this->assertSuggestedInvestigationsEnabled();

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$rawStatus = $dbr->newSelectQueryBuilder()
			->select( 'sic_status' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $rawStatus === false ) {
			throw new InvalidArgumentException( "No case found with id $caseId" );
		}

		return CaseStatus::from( $rawStatus );
	}

	/**
	 * Given a iterable object or array of rows from the cusi_case table, construct an array of
	 * {@link SuggestedInvestigationsCase} objects for those rows and return it.
	 *
	 * @param iterable $rows The rows from the DB query
	 * @return SuggestedInvestigationsCase[]
	 */
	private function getCaseObjectsFromRows( iterable $rows ): array {
		$cases = [];
		foreach ( $rows as $row ) {
			$caseStatus = CaseStatus::tryFrom( (int)$row->sic_status );
			if ( $caseStatus === null ) {
				$this->logger->error(
					'Invalid status "{status}" of a Suggested Investigations case with id "{caseId}"',
					[ 'status' => $row->sic_status, 'caseId' => $row->sic_id ]
				);
				continue;
			}

			$statusChangedBy = $row->sic_status_changed_by !== null ? (int)$row->sic_status_changed_by : null;
			$cases[] = new SuggestedInvestigationsCase(
				(int)$row->sic_id,
				$caseStatus,
				$row->sic_status_reason,
				$statusChangedBy
			);
		}

		return $cases;
	}

	/**
	 * @param int[] $userIds Local wiki user IDs to check
	 * @param CaseStatus[] $statusesFilter If non-empty, only users in cases with these statuses are returned.
	 * If empty, users in cases of any status are returned.
	 * @return int[] The user IDs (from the input) that appear in at least one case
	 * @throws RuntimeException if Suggested Investigations is not enabled
	 */
	public function getUserIdsWithCases( array $userIds, array $statusesFilter = [] ): array {
		$this->assertSuggestedInvestigationsEnabled();

		if ( !$userIds ) {
			return [];
		}

		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( 'siu_user_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => $userIds ] )
			->groupBy( 'siu_user_id' )
			->caller( __METHOD__ );

		if ( $statusesFilter !== [] ) {
			$caseSubquery = $dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cusi_case' )
				->where( [
					'sic_id = siu_sic_id',
					'sic_status' => array_map( static fn ( $s ) => $s->value, $statusesFilter ),
				] )
				->getSQL();

			$queryBuilder->where( [ new RawSQLExpression( 'EXISTS(' . $caseSubquery . ')' ) ] );
		}

		return array_map( 'intval', $queryBuilder->fetchFieldValues() );
	}

	/**
	 * Returns whether the Suggested Investigations feature is enabled
	 */
	public function areSuggestedInvestigationsEnabled(): bool {
		return $this->options->get( 'CheckUserSuggestedInvestigationsEnabled' );
	}

	/**
	 * Helper function to return early if SI is not enabled, so we don't interact with non-existing tables in DB
	 * @throws RuntimeException if SuggestedInvestigations is not enabled.
	 */
	private function assertSuggestedInvestigationsEnabled(): void {
		if ( !$this->areSuggestedInvestigationsEnabled() ) {
			throw new RuntimeException( 'Suggested Investigations is not enabled' );
		}
	}
}
