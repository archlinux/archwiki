<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\RecentChanges\RecentChange;
use Wikimedia\IPUtils;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Populate the CheckUser result tables needed for CheckUser queries with
 * data from recent changes.
 *
 * This is automatically run during first installation within update.php
 * but --force parameter should be set if you want to manually run thereafter.
 */
class PopulateCheckUserTable extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate CheckUser result tables with entries from recentchanges' );
		$this->addOption( 'cutoff', 'Cut-off time for rc_timestamp' );
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
		$db = $this->getPrimaryDB();

		// Check if the table is empty
		$rcRows = $db->newSelectQueryBuilder()
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( !$rcRows ) {
			$this->output( "recentchanges is empty; nothing to add.\n" );
			return true;
		}

		$cutoff = $this->getOption( 'cutoff' );
		if ( $cutoff ) {
			// Something leftover... clear old entries to minimize dupes
			$cutoff = $db->timestamp( $cutoff );
			$db->newDeleteQueryBuilder()
				->deleteFrom( 'cu_changes' )
				->where( $db->expr( 'cuc_timestamp', '<', $cutoff ) )
				->caller( __METHOD__ )
				->execute();
			$cutoffCond = $db->expr( 'rc_timestamp', '<', $cutoff );
		} else {
			$cutoffCond = null;
		}

		$start = (int)$db->newSelectQueryBuilder()
			->field( 'MIN(rc_id)' )
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchField();
		$end = (int)$db->newSelectQueryBuilder()
			->field( 'MAX(rc_id)' )
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchField();
		// Do remaining chunk
		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;

		$this->output(
			"Starting population of cu_changes with recentchanges rc_id from $start to $end.\n"
		);

		$services = $this->getServiceContainer();
		$commentStore = $services->getCommentStore();
		$rcQuery = RecentChange::getQueryInfo();

		while ( $blockStart <= $end ) {
			$this->output( "...migrating rc_id from $blockStart to $blockEnd\n" );
			$queryBuilder = $db->newSelectQueryBuilder()
				->fields( $rcQuery['fields'] )
				->tables( $rcQuery['tables'] )
				->joinConds( $rcQuery['joins'] )
				->conds( [
					$db->expr( 'rc_id', '>=', $blockStart ),
					$db->expr( 'rc_id', '<=', $blockEnd ),
				] )
				->caller( __METHOD__ );
			if ( $cutoffCond ) {
				$queryBuilder->andWhere( $cutoffCond );
			}
			$res = $queryBuilder->fetchResultSet();
			$cuChangesBatch = [];
			$cuPrivateEventBatch = [];
			$cuLogEventBatch = [];
			foreach ( $res as $row ) {
				$comment = $commentStore->getComment( 'rc_comment', $row );
				if ( $row->rc_type == RC_LOG ) {
					$logEntry = null;
					if ( $row->rc_logid != 0 ) {
						$logEntry = DatabaseLogEntry::newFromId( $row->rc_logid, $db );
					}
					if ( $logEntry === null ) {
						$cuPrivateEventBatch[] = [
							'cupe_timestamp' => $row->rc_timestamp,
							'cupe_actor' => $row->rc_actor,
							'cupe_namespace' => $row->rc_namespace,
							'cupe_title' => $row->rc_title,
							'cupe_comment_id' => $comment->id,
							'cupe_page' => $row->rc_cur_id,
							'cupe_log_action' => $row->rc_log_action,
							'cupe_log_type' => $row->rc_log_type,
							'cupe_params' => $row->rc_params,
							'cupe_ip' => $row->rc_ip,
							'cupe_ip_hex' => IPUtils::toHex( $row->rc_ip ),
						];
					} else {
						$cuLogEventBatch[] = [
							'cule_timestamp' => $row->rc_timestamp,
							'cule_actor' => $row->rc_actor,
							'cule_log_id' => $row->rc_logid,
							'cule_ip' => $row->rc_ip,
							'cule_ip_hex' => IPUtils::toHex( $row->rc_ip ),
						];
					}
				} else {
					$cuChangesRow = [
						'cuc_timestamp' => $row->rc_timestamp,
						'cuc_namespace' => $row->rc_namespace,
						'cuc_title' => $row->rc_title,
						'cuc_actor' => $row->rc_actor,
						'cuc_comment_id' => $comment->id,
						'cuc_minor' => $row->rc_minor,
						'cuc_page_id' => $row->rc_cur_id,
						'cuc_this_oldid' => $row->rc_this_oldid,
						'cuc_last_oldid' => $row->rc_last_oldid,
						'cuc_type' => $row->rc_type,
						'cuc_ip' => $row->rc_ip,
						'cuc_ip_hex' => IPUtils::toHex( $row->rc_ip ),
					];
					$cuChangesBatch[] = $cuChangesRow;
				}
			}
			if ( count( $cuChangesBatch ) ) {
				$db->newInsertQueryBuilder()
					->insertInto( 'cu_changes' )
					->rows( $cuChangesBatch )
					->caller( __METHOD__ )
					->execute();
			}
			if ( count( $cuPrivateEventBatch ) ) {
				$db->newInsertQueryBuilder()
					->insertInto( 'cu_private_event' )
					->rows( $cuPrivateEventBatch )
					->caller( __METHOD__ )
					->execute();
			}
			if ( count( $cuLogEventBatch ) ) {
				$db->newInsertQueryBuilder()
					->insertInto( 'cu_log_event' )
					->rows( $cuLogEventBatch )
					->caller( __METHOD__ )
					->execute();
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$this->waitForReplication();
		}

		$this->output( "...cu_changes table has been populated.\n" );

		// Run the population script for the central indexes now that the local CheckUser tables have been populated
		$child = $this->createChild( PopulateCentralCheckUserIndexTables::class );
		$child->execute();

		return true;
	}
}

$maintClass = PopulateCheckUserTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
