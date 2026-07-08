<?php

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Service to facilitate querying data in the central user index.
 */
class CheckUserCentralIndexLookup implements CheckUserQueryInterface {
	/**
	 * The maximum number of rows to fetch in getUsersActiveSinceTimestamp() per batch.
	 */
	private const MAX_ACTIVE_BATCH_SIZE = 1000;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	/**
	 * Get the central user IDs of users who have been active since a given timestamp.
	 *
	 * @param string $timestamp A timestamp in MW_TS format
	 * @param int $batchSize The number of user IDs to return per batch (maximum is self::MAX_ACTIVE_BATCH_SIZE)
	 * @return iterable An iterable lazily yielding central user IDs
	 */
	public function getUsersActiveSinceTimestamp(
		string $timestamp,
		int $batchSize = self::MAX_ACTIVE_BATCH_SIZE
	): iterable {
		$dbr = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );
		$batchSize = min( $batchSize, self::MAX_ACTIVE_BATCH_SIZE );

		$lastCentralId = null;
		while ( true ) {
			$criteria = [ 'MAX(ciu_timestamp) > ' . $dbr->addQuotes( $dbr->timestamp( $timestamp ) ) ];

			if ( $lastCentralId !== null ) {
				$criteria[] = $dbr->expr( 'ciu_central_id', '>', $lastCentralId );
			}

			// Leverage the ciu_central_id_timestamp index to fetch distinct central IDs
			// with their latest activity timestamp.
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'ciu_central_id', 'last_active' => 'MAX(ciu_timestamp)' ] )
				->from( 'cuci_user' )
				->groupBy( 'ciu_central_id' )
				->having( $criteria )
				->orderBy( 'ciu_central_id', SelectQueryBuilder::SORT_ASC )
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $rows->numRows() === 0 ) {
				// No more rows to process
				break;
			}

			foreach ( $rows as $row ) {
				$lastCentralId = (int)$row->ciu_central_id;
				yield $lastCentralId;
			}
		}
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @return array A list of wiki IDs associated with the user ID
	 */
	public function getActiveWikisForUser( UserIdentity $userIdentity ): array {
		$dbr = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );
		$rows = $dbr->newSelectQueryBuilder()
			->select( 'ciu_ciwm_id' )
			->distinct()
			->from( 'cuci_user' )
			->where( [ 'ciu_central_id' => $userIdentity->getId() ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( !$rows ) {
			return [];
		}
		$wikiIds = array_map( 'intval', $rows );
		return $dbr->newSelectQueryBuilder()
			->select( 'ciwm_wiki' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_id' => $wikiIds ] )
			->orderBy( 'ciwm_wiki', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias( CheckUserCentralIndexLookup::class, 'MediaWiki\\CheckUser\\Services\\CheckUserCentralIndexLookup' );
// @codeCoverageIgnoreEnd
