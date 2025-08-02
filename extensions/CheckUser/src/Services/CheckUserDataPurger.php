<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use Wikimedia\Rdbms\IDatabase;

/**
 * This service provides methods which can be used to purge data from the
 * local CheckUser tables
 *
 * @internal For use by PruneCheckUserDataJob and purgeOldData.php only
 */
class CheckUserDataPurger implements CheckUserQueryInterface {

	/**
	 * Gets the key for the lock to be acquired before code tries to purge CheckUser data.
	 * Used to ensure that jobs and the purge maintenance script do not attempt to purge at the same time.
	 *
	 * @param string $domainID The result of {@link IDatabase::getDomainID} for the DB handle being used to purge
	 * @return string The lock key
	 */
	public static function getPurgeLockKey( string $domainID ): string {
		return "$domainID:PruneCheckUserData";
	}

	/**
	 * Purge rows from the given local CheckUser result table.
	 *
	 * @param IDatabase $dbw A primary database connection for the database we are purging rows from. This is provided
	 *   via the arguments in case the connection has an exclusive lock for the purging of rows.
	 * @param string $table The relevant CheckUser result table (e.g. cu_changes)
	 * @param string $cutoff The timestamp used as a "cutoff", where rows which have a timestamp before the given
	 *   cutoff are eligible to be purged from the database
	 * @param ClientHintsReferenceIds $deletedReferenceIds A {@link ClientHintsReferenceIds} instance used to collect
	 *   the reference IDs associated with rows that were purged by the call to this method. The caller is
	 *   responsible for purging the Client Hints data for these reference IDs.
	 * @param string $fname The name of the calling method / function, used for the SQL comments
	 * @param int $totalRowsToPurge The maximum number of rows to purge, default 500
	 * @return int The number of rows that were purged
	 */
	public function purgeDataFromLocalTable(
		IDatabase $dbw, string $table, string $cutoff, ClientHintsReferenceIds $deletedReferenceIds,
		string $fname, int $totalRowsToPurge = 500
	): int {
		// Get the Client Hints reference field for the given table
		$clientHintMapTypeIdentifier = array_flip( UserAgentClientHintsManager::IDENTIFIER_TO_TABLE_NAME_MAP )[$table];
		$clientHintReferenceField =
			UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$clientHintMapTypeIdentifier];
		// Get the timestamp and ID columns for the given $table
		$idField = self::RESULT_TABLE_TO_PREFIX[$table] . 'id';
		$timestampField = self::RESULT_TABLE_TO_PREFIX[$table] . 'timestamp';
		// Get at most 500 rows to purge from the given $table, selecting the row ID and associated Client Hints
		// data reference ID
		$idQueryBuilder = $dbw->newSelectQueryBuilder()
			->field( $idField )
			->table( $table )
			->conds( $dbw->expr( $timestampField, '<', $cutoff ) )
			->limit( $totalRowsToPurge )
			->caller( $fname );
		if ( $clientHintReferenceField !== $idField ) {
			$idQueryBuilder->field( $clientHintReferenceField );
		}
		$result = $idQueryBuilder->fetchResultSet();
		// Group the row IDs into an array so that we can process them shortly. While doing this, also
		// add the reference IDs for these rows for purging to the ClientHintsReferenceIds object.
		$ids = [];
		foreach ( $result as $row ) {
			$ids[] = $row->$idField;
			$deletedReferenceIds->addReferenceIds( $row->$clientHintReferenceField, $clientHintMapTypeIdentifier );
		}
		// Perform the purging of the rows with IDs in $ids
		if ( $ids ) {
			$dbw->newDeleteQueryBuilder()
				->table( $table )
				->where( [ $idField => $ids ] )
				->caller( $fname )
				->execute();
		}
		// The number of IDs found will be the number of rows purged, as the delete statement above will have purged
		// them. This being accurate (i.e. some rows were not deleted) is not important, as it is used only
		// for stats purposes in purgeOldData.php.
		return count( $ids );
	}
}
