<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\SelectQueryBuilder;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Populates the cuci_user and cuci_temp_edit tables with data from the local CheckUser tables.
 */
class PopulateCentralCheckUserIndexTables extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate the cuci_user and cuci_temp_edit tables' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$this->populateCentralIndexTablesFromTable( $table );
		}
		return true;
	}

	/**
	 * Populates the cuci_temp_edit table with rows from the local CheckUser result tables.
	 *
	 * This is done by calling {@link CheckUserCentralIndexManager::recordActionInCentralIndexes} for each
	 * combination of performer and IP in the local CheckUser result table.
	 *
	 * @return void
	 */
	private function populateCentralIndexTablesFromTable( string $table ) {
		$dbr = $this->getReplicaDB();
		// Possible values: cuc_, cule_, cupe_
		$columnAlias = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];

		$tableHasRows = (bool)$dbr->newSelectQueryBuilder()
			->table( $table )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( !$tableHasRows ) {
			$this->output( "Skipping importing data from $table to central index tables as the table is empty\n" );
			return;
		}

		$userGroupManager = $this->getServiceContainer()->getUserGroupManager();
		$actorStore = $this->getServiceContainer()->getActorStore();
		/** @var CheckUserCentralIndexManager $checkUserCentralIndexManager */
		$checkUserCentralIndexManager = $this->getServiceContainer()->get( 'CheckUserCentralIndexManager' );

		$this->output( "Now importing data from $table to the central index tables\n" );

		$lastActorId = 0;
		do {
			// Get a batch of usernames for processing
			$batchOfActorIds = $dbr->newSelectQueryBuilder()
				->select( "{$columnAlias}actor" )
				->distinct()
				->table( $table )
				->andWhere( $dbr->expr( "{$columnAlias}actor", '>', $lastActorId ) )
				->orderBy( "{$columnAlias}actor", SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchFieldValues();

			$lastActorId = end( $batchOfActorIds );
			$firstActorId = reset( $batchOfActorIds );
			if ( $firstActorId !== false ) {
				$this->output( "...Processing users with actor IDs $firstActorId to $lastActorId\n" );
			}

			foreach ( $batchOfActorIds as $actorId ) {
				$performer = $actorStore->getActorById( $actorId, $dbr );

				// Skip anonymous users or users which do not exist, as the tables do not support these users
				// and therefore will always never result in any inserts.
				if ( !$performer || !$performer->isRegistered() ) {
					continue;
				}

				// Skip users in excluded groups to save multiple calls to the service which will always not
				// result in any inserts.
				if ( count( array_intersect(
					$userGroupManager->getUserGroups( $performer ),
					$this->getServiceContainer()->getMainConfig()->get( 'CheckUserCentralIndexGroupsToExclude' )
				) ) ) {
					continue;
				}

				$lastIp = null;
				do {
					// Get a batch of IPs used by this user for processing
					$ipQueryBuilder = $dbr->newSelectQueryBuilder()
						->select( "{$columnAlias}ip" )
						->distinct()
						->from( $table )
						->where( [ "{$columnAlias}actor" => $actorId ] )
						->limit( $this->mBatchSize )
						->orderBy( "{$columnAlias}ip", SelectQueryBuilder::SORT_ASC );

					if ( $lastIp !== null ) {
						$ipQueryBuilder->where( $dbr->expr( "{$columnAlias}ip", '>', $lastIp ) );
					}

					$batchOfIPs = $ipQueryBuilder->caller( __METHOD__ )->fetchFieldValues();

					foreach ( $batchOfIPs as $ip ) {
						// Using this combination of $ip and $performer, make calls to CheckUserCentralIndexManager
						// ::recordActionInCentralIndexes to update the central index.

						// Get the last timestamp used to make an action by this $performer and $ip combination.
						$lastTimestamp = $dbr->newSelectQueryBuilder()
							->select( "MAX({$columnAlias}timestamp)" )
							->from( $table )
							->where( [ "{$columnAlias}actor" => $actorId, "{$columnAlias}ip" => $ip ] )
							->limit( $this->mBatchSize )
							->caller( __METHOD__ )
							->fetchField();

						// Record an entry in the central index tables with the last found timestamp
						$checkUserCentralIndexManager->recordActionInCentralIndexes(
							$performer, $ip, $dbr->getDomainID(), $lastTimestamp, false
						);

						// If the $table is cu_changes, then we should also call the method again while filtering
						// for actions that have an associated revision ID. This is necessary as the cuci_temp_edit
						// central index table only stores timestamps associated with edit actions.
						if ( $table === CheckUserQueryInterface::CHANGES_TABLE ) {
							$lastEditTimestamp = $dbr->newSelectQueryBuilder()
								->select( 'MAX(cuc_timestamp)' )
								->from( $table )
								->where( [
									'cuc_actor' => $actorId,
									'cuc_ip' => $ip,
									$dbr->expr( 'cuc_this_oldid', '!=', 0 ),
								] )
								->limit( $this->mBatchSize )
								->caller( __METHOD__ )
								->fetchField();

							if ( $lastEditTimestamp ) {
								$checkUserCentralIndexManager->recordActionInCentralIndexes(
									$performer, $ip, $dbr->getDomainID(), $lastEditTimestamp, true
								);
							}
						}
					}

					// After processing a batch of IPs for a user, wait for replica DBs to catch up.
					$this->waitForReplication();
					$lastIp = end( $batchOfIPs );
				} while ( count( $batchOfIPs ) );
			}
		} while ( count( $batchOfActorIds ) );

		$this->output( "Finished importing data from $table to the central index tables\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateCentralCheckUserIndexTables::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
