<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Move log entries from cu_changes to cu_private_event.
 *
 * This script copies entries with cuc_type equal to 3 to cu_private_event,
 * and updates the cu_changes entries that were copied to have the column
 * cuc_only_for_read_old set to 1.
 *
 * Based on parts of multiple other maintenance scripts in this extension.
 */
class MoveLogEntriesFromCuChanges extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Move log entries from cu_changes to cu_private_event' );
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

		// Check if the table is empty
		$cuChangesRows = $dbw->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( !$cuChangesRows ) {
			$this->output( "cu_changes is empty; nothing to move.\n" );
			return true;
		}

		// If the cuc_only_for_read_old column does not exist in cu_changes, then there cannot be log entries in
		// cu_changes as the event tables migration is already done. This should be the case on an install on MW
		// version 1.43 or later.
		if ( !$dbw->fieldExists( 'cu_changes', 'cuc_only_for_read_old', __METHOD__ ) ) {
			$this->output( "cu_changes cannot hold log entries; nothing to move.\n" );
			return true;
		}

		// Now move the entries.
		$this->moveLogEntriesFromCuChanges();

		return true;
	}

	/**
	 * Actually performs the move of entries from cu_changes to
	 * cu_private_event. The entries are not deleted in cu_changes,
	 * but instead marked only for read old. These will be deleted
	 * using a different maintenance script.
	 *
	 * @return void
	 */
	private function moveLogEntriesFromCuChanges() {
		$dbw = $this->getDB( DB_PRIMARY );

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
		// Do remaining chunk
		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;

		$this->output(
			"Moving log entries from cu_changes to cu_private_event with cuc_id from $start to $end.\n"
		);

		while ( $blockStart <= $end ) {
			$this->output( "...checking and moving log entries with cuc_id from $blockStart to $blockEnd\n" );
			$res = $dbw->newSelectQueryBuilder()
				->fields( [
					'cuc_id',
					'cuc_namespace',
					'cuc_title',
					'cuc_actor',
					'cuc_actiontext',
					'cuc_comment_id',
					'cuc_page_id',
					'cuc_timestamp',
					'cuc_ip',
					'cuc_ip_hex',
					'cuc_xff',
					'cuc_xff_hex',
					'cuc_agent',
					'cuc_private'
				] )
				->table( 'cu_changes' )
				->where( [
					$dbw->expr( 'cuc_id', '>=', $blockStart ),
					$dbw->expr( 'cuc_id', '<=', $blockEnd ),
					'cuc_type' => RC_LOG,
					'cuc_only_for_read_old' => 0
				] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$batch = [];
			$setOnlyForReadOldBatch = [];
			foreach ( $res as $row ) {
				$batch[] = [
					'cupe_timestamp' => $row->cuc_timestamp,
					'cupe_namespace' => $row->cuc_namespace,
					'cupe_title' => $row->cuc_title,
					'cupe_actor' => $row->cuc_actor,
					'cupe_page' => $row->cuc_page_id,
					'cupe_log_action' => 'migrated-cu_changes-log-event',
					'cupe_log_type' => 'checkuser-private-event',
					'cupe_params' => LogEntryBase::makeParamBlob( [ '4::actiontext' => $row->cuc_actiontext ] ),
					'cupe_comment_id' => $row->cuc_comment_id,
					'cupe_ip' => $row->cuc_ip,
					'cupe_ip_hex' => $row->cuc_ip_hex,
					'cupe_xff' => $row->cuc_xff,
					'cupe_xff_hex' => $row->cuc_xff_hex,
					'cupe_agent' => $row->cuc_agent,
					'cupe_private' => $row->cuc_private
				];
				$setOnlyForReadOldBatch[] = $row->cuc_id;
			}
			if ( count( $batch ) ) {
				$dbw->newInsertQueryBuilder()
					->table( 'cu_private_event' )
					->rows( $batch )
					->caller( __METHOD__ )
					->execute();
			}
			if ( count( $setOnlyForReadOldBatch ) ) {
				// A separate maintenance script will delete these entries just
				// before cuc_only_for_read_old is removed.
				$dbw->newUpdateQueryBuilder()
					->table( 'cu_changes' )
					->set( [ 'cuc_only_for_read_old' => 1 ] )
					->where( [ 'cuc_id' => $setOnlyForReadOldBatch ] )
					->caller( __METHOD__ )
					->execute();
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$this->waitForReplication();
		}

		$this->output( "...all log entries in cu_changes have been moved to cu_private_event table.\n" );
	}
}

$maintClass = MoveLogEntriesFromCuChanges::class;
require_once RUN_MAINTENANCE_IF_MAIN;
