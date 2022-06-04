<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script that merges the revision_actor_temp table into the
 * revision table.
 *
 * @ingroup Maintenance
 * @since 1.37
 */
class MigrateRevisionActorTemp extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Copy the data from the revision_actor_temp into the revision table'
		);
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
		$this->addOption( 'start', 'Start after this rev_id', false, true );
	}

	protected function getUpdateKey() {
		return __CLASS__;
	}

	protected function doDBUpdates() {
		$batchSize = $this->getBatchSize();

		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->fieldExists( 'revision', 'rev_actor', __METHOD__ ) ) {
			$this->output( "Run update.php to create rev_actor.\n" );
			return false;
		}
		if ( !$dbw->tableExists( 'revision_actor_temp', __METHOD__ ) ) {
			$this->output( "revision_actor_temp does not exist, so nothing to do.\n" );
			return true;
		}

		$this->output( "Merging the revision_actor_temp table into the revision table...\n" );
		$conds = [];
		$updated = 0;
		$start = (int)$this->getOption( 'start', 0 );
		if ( $start > 0 ) {
			$conds[] = 'rev_id >= ' . $dbw->addQuotes( $start );
		}
		while ( true ) {
			$res = $dbw->newSelectQueryBuilder()
				->select( [ 'rev_id', 'rev_actor', 'revactor_actor' ] )
				->from( 'revision' )
				->join( 'revision_actor_temp', null, 'rev_id=revactor_rev' )
				->where( $conds )
				->limit( $batchSize )
				->orderBy( 'rev_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

			$numRows = $res->numRows();

			$last = null;
			foreach ( $res as $row ) {
				$last = $row->rev_id;
				if ( !$row->rev_actor ) {
					$dbw->update(
						'revision',
						[ 'rev_actor' => $row->revactor_actor ],
						[ 'rev_id' => $row->rev_id ],
						__METHOD__
					);
					$updated += $dbw->affectedRows();
				} elseif ( $row->rev_actor !== $row->revactor_actor ) {
					$this->error(
						"Revision ID $row->rev_id has rev_actor = $row->rev_actor and "
						. "revactor_actor = $row->revactor_actor. Ignoring the latter."
					);
				}
			}

			if ( $numRows < $batchSize ) {
				// We must have reached the end
				break;
			}

			$this->output( "... rev_id=$last, updated $updated\n" );
			$conds = [ 'rev_id > ' . $dbw->addQuotes( $last ) ];

			// Sleep between batches for replication to catch up
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->waitForReplication();
			$sleep = (int)$this->getOption( 'sleep', 0 );
			if ( $sleep > 0 ) {
				sleep( $sleep );
			}
		}

		$this->output(
			"Completed merge of revision_actor into the revision table, "
			. "$updated rows updated.\n"
		);

		return true;
	}

}

$maintClass = MigrateRevisionActorTemp::class;
require_once RUN_MAINTENANCE_IF_MAIN;
