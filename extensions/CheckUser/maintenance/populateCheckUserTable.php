<?php

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\RecentChanges\RecentChange;
use Wikimedia\IPUtils;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

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
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $services->get( 'CheckUserInsert' );

		$rcQuery = RecentChange::getQueryInfo();

		// Acquire an ID in the cu_useragent table for a User Agent that is an empty string,
		// as we will have no value for the user agent for rows from recentchanges
		$emptyUserAgentId = $checkUserInsert->acquireUserAgentTableId( '' );

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
				// RC_CATEGORIZE recent changes are generally triggered by other edits, so there is no reason to store
				// checkuser data about them (T125209). Also excluded by the CheckUserInsert service.
				if ( $row->rc_source === RecentChange::SRC_CATEGORIZE ) {
					continue;
				}

				// Exclude RecentChanges with sources other than flow edits and MediaWiki core sources,
				// as usually these events are not specific to the local wiki (T125664).
				// Also excluded by the CheckUserInsert service.
				if ( $row->rc_source !== 'flow' && !in_array( $row->rc_source, RecentChange::INTERNAL_SOURCES ) ) {
					continue;
				}

				$comment = $commentStore->getComment( 'rc_comment', $row );
				if ( $row->rc_source == RecentChange::SRC_LOG ) {
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
							'cupe_ip_hex' => $row->rc_ip ? IPUtils::toHex( $row->rc_ip ) : null,
							'cupe_agent_id' => $emptyUserAgentId,
						];
					} else {
						$cuLogEventBatch[] = [
							'cule_timestamp' => $row->rc_timestamp,
							'cule_actor' => $row->rc_actor,
							'cule_log_id' => $row->rc_logid,
							'cule_ip_hex' => $row->rc_ip ? IPUtils::toHex( $row->rc_ip ) : null,
							'cule_agent_id' => $emptyUserAgentId,
						];
					}
				} else {
					$cuChangesBatch[] = [
						'cuc_timestamp' => $row->rc_timestamp,
						'cuc_namespace' => $row->rc_namespace,
						'cuc_title' => $row->rc_title,
						'cuc_actor' => $row->rc_actor,
						'cuc_comment_id' => $comment->id,
						'cuc_minor' => $row->rc_minor,
						'cuc_page_id' => $row->rc_cur_id,
						'cuc_this_oldid' => $row->rc_this_oldid,
						'cuc_last_oldid' => $row->rc_last_oldid,
						'cuc_type' => CheckUserInsert::getTypeFromRCSource( $row->rc_source ),
						'cuc_ip_hex' => $row->rc_ip ? IPUtils::toHex( $row->rc_ip ) : null,
						'cuc_agent_id' => $emptyUserAgentId,
					];
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

// @codeCoverageIgnoreStart
$maintClass = PopulateCheckUserTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
