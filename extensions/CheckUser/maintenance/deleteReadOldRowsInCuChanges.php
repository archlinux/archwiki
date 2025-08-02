<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Delete entries in cu_changes where the cuc_only_for_read_old column is set to 1.
 *
 * This is done as the log entries are stored in other tables and are no longer needed in cu_changes as the
 * CheckUser interfaces no longer read these rows that are only for read old from cu_changes.
 */
class DeleteReadOldRowsInCuChanges extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Delete entries in cu_changes that are only for use when reading old for event tables migration.'
		);
		$this->setBatchSize( 100 );

		$this->requireExtension( 'CheckUser' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		// Check if the table is empty, and if it is then there is nothing to delete (so return early).
		$cuChangesRows = $dbw->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			// We can limit this to 1 row as we only need to know if there are any rows in the table, so selecting
			// any more rows than that is unnecessary.
			->limit( 1 )
			->fetchRowCount();
		if ( !$cuChangesRows ) {
			$this->output( "cu_changes is empty; nothing to delete.\n" );
			return true;
		}

		// If the cuc_only_for_read_old column does not exist in cu_changes, then there are no read old rows to delete
		// This should be the case on an install on MW version 1.43 or later.
		if ( !$dbw->fieldExists( 'cu_changes', 'cuc_only_for_read_old', __METHOD__ ) ) {
			$this->output( "cu_changes cannot hold entries only for use when reading old; nothing to delete.\n" );
			return true;
		}

		// If the batch size is 1, then the script will break. As such, we should increase the batch size to 2.
		if ( $this->getBatchSize() === 1 ) {
			$this->output( "Batch size is 1, which will cause the script to break. Increasing batch size to 2.\n" );
			$this->setBatchSize( 2 );
		}

		// Actually perform the delete
		$start = (int)$dbw->newSelectQueryBuilder()
			->field( 'MIN(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();
		$end = (int)$dbw->newSelectQueryBuilder()
			->field( 'MAX(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();

		$this->output(
			"Deleting rows only for use when reading old from cu_changes with cuc_id from $start to $end.\n"
		);

		// The first batch is from $start to $start + $this->mBatchSize - 1
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;
		// The end index should be increased by one batch size to ensure that the last rows are processed. Otherwise,
		// some rows may be skipped.
		$end += $this->mBatchSize - 1;

		while ( $blockStart <= $end ) {
			$this->output(
				"...searching for entries that are only for read old with cuc_id from $blockStart to $blockEnd\n"
			);
			// Find all rows in the current batch that are only for read old and get their cuc_id.
			$matchingIds = $dbw->newSelectQueryBuilder()
				->field( 'cuc_id' )
				->table( 'cu_changes' )
				->where( [
					$dbw->expr( 'cuc_id', '>=', $blockStart ),
					$dbw->expr( 'cuc_id', '<=', $blockEnd ),
					'cuc_only_for_read_old' => 1,
				] )
				->caller( __METHOD__ )
				->fetchFieldValues();
			// If there are any matching rows, delete them.
			if ( count( $matchingIds ) ) {
				$dbw->newDeleteQueryBuilder()
					->table( 'cu_changes' )
					->where( [ 'cuc_id' => $matchingIds ] )
					->caller( __METHOD__ )
					->execute();
			}
			// Move to the next batch and wait for the replica DBs to catch up.
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$this->waitForReplication();
		}

		$this->output( "...all entries only for use when reading old in cu_changes have been deleted.\n" );

		return true;
	}
}

$maintClass = DeleteReadOldRowsInCuChanges::class;
require_once RUN_MAINTENANCE_IF_MAIN;
