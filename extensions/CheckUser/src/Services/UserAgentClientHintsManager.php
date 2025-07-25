<?php

namespace MediaWiki\CheckUser\Services;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Revision\RevisionLookup;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Service to insert and delete user-agent client hint values and their associations with rows in cu_changes,
 * cu_log_event and cu_private_event.
 */
class UserAgentClientHintsManager {

	public const CONSTRUCTOR_OPTIONS = [
		'CUDMaxAge',
		MainConfigNames::UpdateRowsPerQuery,
	];

	public const SUPPORTED_TYPES = [
		'revision',
		'privatelog',
	];

	/**
	 * TINYINT references for use in cu_useragent_clienthints_map.uachm_reference_type
	 */
	// Identifier for the cu_changes table
	public const IDENTIFIER_CU_CHANGES = 0;
	// Identifier for the cu_log_event table
	public const IDENTIFIER_CU_LOG_EVENT = 1;
	// Identifier for the cu_private_event table
	public const IDENTIFIER_CU_PRIVATE_EVENT = 2;

	public const IDENTIFIER_TO_TABLE_NAME_MAP = [
		self::IDENTIFIER_CU_CHANGES => 'cu_changes',
		self::IDENTIFIER_CU_LOG_EVENT => 'cu_log_event',
		self::IDENTIFIER_CU_PRIVATE_EVENT => 'cu_private_event',
	];
	public const IDENTIFIER_TO_COLUMN_NAME_MAP = [
		self::IDENTIFIER_CU_CHANGES => 'cuc_this_oldid',
		self::IDENTIFIER_CU_LOG_EVENT => 'cule_log_id',
		self::IDENTIFIER_CU_PRIVATE_EVENT => 'cupe_id',
	];
	private IDatabase $dbw;
	private IReadableDatabase $dbr;
	private RevisionLookup $revisionLookup;
	private ServiceOptions $options;
	private LoggerInterface $logger;

	public function __construct(
		IConnectionProvider $connectionProvider,
		RevisionLookup $revisionLookup,
		ServiceOptions $options,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->dbw = $connectionProvider->getPrimaryDatabase();
		$this->dbr = $connectionProvider->getReplicaDatabase();
		$this->revisionLookup = $revisionLookup;
		$this->logger = $logger;
	}

	/**
	 * Given an array of client hint data, a reference ID, and an identifier type, record the data to the
	 * cu_useragent_clienthints and cu_useragent_clienthints_map tables.
	 *
	 * @param ClientHintsData $clientHintsData
	 * @param int $referenceId An ID to use in `uachm_reference_id` column in the
	 *   cu_useragent_clienthints_map table
	 * @param string $type The type of event this data is associated with. Valid values are the values in
	 *   {@link UserAgentClientHintsManager::SUPPORTED_TYPES}.
	 * @param bool $usePrimary If true, use the primary DB for SELECT queries.
	 * @return StatusValue
	 */
	public function insertClientHintValues(
		ClientHintsData $clientHintsData, int $referenceId, string $type, bool $usePrimary = false
	): StatusValue {
		// Check if there are rows to insert to the map table.
		$rows = $clientHintsData->toDatabaseRows();
		if ( !count( $rows ) ) {
			// Nothing to insert, so return early.
			// Having nothing to insert isn't considered "bad", so return a new good
			// For example, a browser could choose to provide no Client Hints data but
			// still send an empty API request.
			return StatusValue::newGood();
		}

		// Check for existing entry.
		$existingRecord = $this->dbr->newSelectQueryBuilder()
			->table( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => $this->getMapIdByType( $type ),
				'uachm_reference_id' => $referenceId
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( $existingRecord ) {
			return StatusValue::newFatal(
				'checkuser-api-useragent-clienthints-mappings-exist',
				[ $type, $referenceId ]
			);
		}

		$clientHintsMappings = $this->selectClientHintMappings( $rows, $usePrimary, true );
		if ( $clientHintsMappings === false ) {
			$usePrimary = true;
			$clientHintsMappings = $this->selectClientHintMappings( $rows, $usePrimary, false );
		}
		return $this->insertMappingRows( $clientHintsMappings, $referenceId, $type );
	}

	/**
	 * Given an identifier for the type of event (e.g. 'revision'), return the relevant TINYINT
	 * for the table that the database entry for cu_useragent_clienthints_map refers to
	 *
	 * @param string $type
	 * @return int One of self::IDENTIFIER_* constants
	 */
	private function getMapIdByType( string $type ): int {
		switch ( $type ) {
			case 'revision':
				return self::IDENTIFIER_CU_CHANGES;
			case 'log':
				return self::IDENTIFIER_CU_LOG_EVENT;
			case 'privatelog':
				return self::IDENTIFIER_CU_PRIVATE_EVENT;
			default:
				throw new LogicException( "Invalid type $type" );
		}
	}

	/**
	 * Insert rows into the cu_useragent_clienthints_map table.
	 *
	 * This links a foreign ID (e.g. "revision 1234") with client hint data values stored in cu_useragent_clienthints.
	 *
	 * @param int[] $clientHintMapping
	 * @param int $foreignId
	 * @param string $type
	 * @return StatusValue
	 * @see insertClientHintValues, which invokes this method.
	 *
	 */
	private function insertMappingRows(
		array $clientHintMapping, int $foreignId, string $type
	): StatusValue {
		// TINYINT reference to cu_changes, cu_log_event or cu_private_event.
		$idType = $this->getMapIdByType( $type );
		$mapRows = [];
		foreach ( $clientHintMapping as $clientHintId ) {
			$mapRows[] = [
				'uachm_uach_id' => $clientHintId,
				'uachm_reference_type' => $idType,
				'uachm_reference_id' => $foreignId,
			];
		}

		if ( count( $mapRows ) ) {
			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'cu_useragent_clienthints_map' )
				->ignore()
				->rows( $mapRows )
				->caller( __METHOD__ )
				->execute();
		}
		return StatusValue::newGood();
	}

	/**
	 * Given reference IDs this method finds and deletes
	 * the mapping entries for these reference IDs.
	 *
	 * @param ClientHintsReferenceIds $clientHintsReferenceIds
	 * @return int The number of mapping rows deleted.
	 */
	public function deleteMappingRows( ClientHintsReferenceIds $clientHintsReferenceIds ): int {
		// Keep a track of the number of mapping rows that are deleted.
		$mappingRowsDeleted = 0;
		foreach ( $clientHintsReferenceIds->getReferenceIds() as $mapId => $referenceIds ) {
			if ( !count( $referenceIds ) ) {
				continue;
			}
			// Delete the rows in cu_useragent_clienthints_map associated with these reference IDs
			do {
				// Fetch a batch of rows to delete from the DB (the primary key is all rows in the table,
				// so we need to fetch all of them).
				$batchToDelete = $this->dbw->newSelectQueryBuilder()
					->select( [ 'uachm_uach_id', 'uachm_reference_type', 'uachm_reference_id' ] )
					->from( 'cu_useragent_clienthints_map' )
					->where( [
						'uachm_reference_id' => $referenceIds,
						'uachm_reference_type' => $mapId,
					] )
					->limit( $this->options->get( MainConfigNames::UpdateRowsPerQuery ) )
					->caller( __METHOD__ )
					->fetchResultSet();
				if ( !$batchToDelete->count() ) {
					break;
				}
				// Construct a list of WHERE conditions which would delete all the rows for this batch.
				$batchDeleteConds = [];
				foreach ( $batchToDelete as $row ) {
					$batchDeleteConds[] = $this->dbw->andExpr( [
						'uachm_uach_id' => $row->uachm_uach_id,
						'uachm_reference_type' => $row->uachm_reference_type,
						'uachm_reference_id' => $row->uachm_reference_id,
					] );
				}
				// Perform the deletion for this batch
				$this->dbw->newDeleteQueryBuilder()
					->deleteFrom( 'cu_useragent_clienthints_map' )
					->where( $this->dbw->orExpr( $batchDeleteConds ) )
					->caller( __METHOD__ )
					->execute();
				$mappingRowsDeleted += $this->dbw->affectedRows();
			} while ( $batchToDelete->count() );
		}
		if ( !$mappingRowsDeleted ) {
			$this->logger->info( "No mapping rows deleted." );
		} else {
			$this->logger->debug(
				"Deleted {mapping_rows_deleted} mapping rows.",
				[ 'mapping_rows_deleted' => $mappingRowsDeleted ]
			);
		}
		return $mappingRowsDeleted;
	}

	/**
	 * Checks 100 rows with the smallest uachm_reference_id
	 * for each uachm_reference_type value to see whether their
	 * associated entry referenced by the uachm_reference_id
	 * value has been already purged.
	 *
	 * If it reaches an entry that is not orphaned, the checks are
	 * stopped as items with a larger reference ID are unlikely to
	 * be orphaned.
	 *
	 * This catches rows that have been left without deletion
	 * due to unforeseen circumstances, as described in T350681.
	 *
	 * @return int The number of orphaned map rows deleted.
	 */
	public function deleteOrphanedMapRows(): int {
		// Keep a track of the number of mapping rows that are deleted.
		$mappingRowsDeleted = 0;
		foreach ( self::IDENTIFIER_TO_TABLE_NAME_MAP as $mappingId => $table ) {
			// Get 100 rows with the given mapping ID
			$resultSet = $this->dbr->newSelectQueryBuilder()
				->select( 'uachm_reference_id' )
				->table( 'cu_useragent_clienthints_map' )
				->where( [ 'uachm_reference_type' => $mappingId ] )
				->orderBy( 'uachm_reference_id' )
				->groupBy( 'uachm_reference_id' )
				->limit( 100 )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $resultSet as $row ) {
				// For each row, check if the ::isMapRowOrphaned method
				// indicates that the row is orphaned.
				$referenceId = $row->uachm_reference_id;
				$mapRowIsOrphaned = $this->isMapRowOrphaned( $referenceId, $mappingId );
				if ( $mapRowIsOrphaned ) {
					// If the map row is orphaned, then perform the deletion
					// and add the affected rows count to the return count.
					$this->dbw->newDeleteQueryBuilder()
						->deleteFrom( 'cu_useragent_clienthints_map' )
						->where( [
							'uachm_reference_id' => $referenceId,
							'uachm_reference_type' => $mappingId,
						] )
						->caller( __METHOD__ )
						->execute();
					$mappingRowsDeleted += $this->dbw->affectedRows();
				} else {
					// If the map row is probably not orphaned, then just stop processing
					// the rows in this table.
					break;
				}
			}
		}
		if ( $mappingRowsDeleted ) {
			$this->logger->info(
				"Deleted {mapping_rows_deleted} orphaned mapping rows.",
				[ 'mapping_rows_deleted' => $mappingRowsDeleted ]
			);
		}
		return $mappingRowsDeleted;
	}

	/**
	 * Returns whether rows with the given $referenceId and $mappingId
	 * in cu_useragent_clienthints_map are likely orphaned.
	 *
	 * @param int $referenceId
	 * @param int $mappingId
	 * @return bool
	 */
	private function isMapRowOrphaned( int $referenceId, int $mappingId ): bool {
		if ( !array_key_exists( $mappingId, self::IDENTIFIER_TO_TABLE_NAME_MAP ) ) {
			throw new LogicException( "Unrecognised map ID '$mappingId'" );
		}
		if ( !in_array( $mappingId, [ self::IDENTIFIER_CU_LOG_EVENT, self::IDENTIFIER_CU_CHANGES ] ) ) {
			// If the mapping ID is not for cu_changes or cu_log_event,
			// query the table directly to check if the associated reference ID
			// exists in the table.
			return !$this->dbr->newSelectQueryBuilder()
				->field( '1' )
				->table( self::IDENTIFIER_TO_TABLE_NAME_MAP[$mappingId] )
				->where( [ self::IDENTIFIER_TO_COLUMN_NAME_MAP[$mappingId] => $referenceId ] )
				->caller( __METHOD__ )
				->fetchField();
		}
		// If the mapping ID is for cu_changes or cu_log_event,
		// then query the revision table or logging table respectively
		// for the associated timestamp to determine if the map
		// row should have already been deleted.
		$associatedTimestamp = false;
		if ( $mappingId === self::IDENTIFIER_CU_CHANGES ) {
			// Get the timestamp from the revision lookup service
			$revisionRecord = $this->revisionLookup->getRevisionById( $referenceId );
			if ( $revisionRecord ) {
				$associatedTimestamp = $revisionRecord->getTimestamp();
			}
		} elseif ( $mappingId === self::IDENTIFIER_CU_LOG_EVENT ) {
			// Get the timestamp from using DatabaseLogEntry::newFromId
			$logObject = DatabaseLogEntry::newFromId( $referenceId, $this->dbr );
			if ( $logObject ) {
				$associatedTimestamp = $logObject->getTimestamp();
			}
		}
		// The map rows are considered orphaned if of the following any apply:
		// * There is no timestamp for the revision or log event (should be generally impossible for this
		//   to be the case).
		// * No such reference ID exists (i.e. no such revision ID or log ID)
		// * The timestamp associated with the revision or log event is before the
		//   wgCUDMaxAge + 100 seconds ago to the current time.
		//
		// The 100 seconds are added to wgCUDMaxAge to prevent attempting to delete map rows
		// that would have been normally deleted. This code is intended to catch map rows that
		// were not deleted normally.
		return !$associatedTimestamp ||
			$associatedTimestamp < ConvertibleTimestamp::convert(
				TS_MW,
				ConvertibleTimestamp::time() - ( $this->options->get( 'CUDMaxAge' ) + 100 )
			);
	}

	/**
	 * Helper method to find the id for client hint values.
	 * Missing values are inserted to generate new ids on the database.
	 * In that case the function needs to be recalled to select the new ids.
	 *
	 * E.g. if "architecture: arm" does not exists as a name/value pair, insert it.
	 *
	 * @param array[] $rows An array of arrays, where each array contains a key/value pair:
	 *  uach_name => "some name",
	 *  uach_value => "some value"
	 * @param bool $usePrimary If true, use the primary DB for SELECT queries.
	 * @param bool $insertMissingData If true, insert missing data
	 * @return int[]|false
	 */
	private function selectClientHintMappings( array $rows, bool $usePrimary, bool $insertMissingData ) {
		$db = $usePrimary ? $this->dbw : $this->dbr;

		$orExpr = [];
		$rowsToInsert = [];
		foreach ( $rows as $row ) {
			$orExpr[] = $db->andExpr( $row );
			$rowsToInsert[$row['uach_name'] . ':' . $row['uach_value']] = $row;
		}

		$dbRes = $db->newSelectQueryBuilder()
			->select( [ 'uach_id', 'uach_name', 'uach_value' ] )
			->from( 'cu_useragent_clienthints' )
			->where( $db->orExpr( $orExpr ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		$clientHintMapping = [];
		foreach ( $dbRes as $dbRow ) {
			$clientHintMapping[] = (int)$dbRow->uach_id;
			unset( $rowsToInsert[$dbRow->uach_name . ':' . $dbRow->uach_value] );
		}

		if ( count( $rowsToInsert ) ) {
			if ( $insertMissingData ) {
				$this->dbw->newInsertQueryBuilder()
					->insertInto( 'cu_useragent_clienthints' )
					->ignore()
					->rows( array_values( $rowsToInsert ) )
					->caller( __METHOD__ )
					->execute();
				// False indicates the data has to be re-read to find ids for new inserted data
				return false;
			}
			foreach ( $rowsToInsert as $row ) {
				$this->logger->warning(
					"Lookup failed for cu_useragent_clienthints row with name {name} and value {value}.",
					[ $row['uach_name'], $row['uach_value'] ]
				);
			}
		}

		return $clientHintMapping;
	}

}
