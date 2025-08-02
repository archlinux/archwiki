<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use PurgeRecentChanges;
use Wikimedia\Timestamp\ConvertibleTimestamp;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeOldData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge expired rows in CheckUser and RecentChanges' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		$config = $this->getConfig();
		$cudMaxAge = $config->get( 'CUDMaxAge' );
		$cutoff = $this->getPrimaryDB()->timestamp( ConvertibleTimestamp::time() - $cudMaxAge );

		// Get an exclusive lock to purge the expired CheckUser data, so that no job attempts to do this while
		// we are doing it here.
		$domainId = $this->getPrimaryDB()->getDomainID();
		$key = CheckUserDataPurger::getPurgeLockKey( $domainId );
		// Set the timeout at 60s, in case any job that has the lock is slow to run.
		$scopedLock = $this->getPrimaryDB()->getScopedLockAndFlush( $key, __METHOD__, 60 );
		if ( $scopedLock ) {
			// Purge expired rows from each local CheckUser result table
			foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
				$this->output( "Purging data from $table..." );
				[ $count, $mappingRowsCount ] = $this->prune( $table, $cutoff );
				$this->output( "Purged $count rows and $mappingRowsCount client hint mapping rows.\n" );
			}

			if ( $this->getConfig()->get( 'CheckUserWriteToCentralIndex' ) ) {
				// Purge expired rows from the central index tables where the rows are associated with this wiki
				/** @var CheckUserCentralIndexManager $checkUserCentralIndexManager */
				$checkUserCentralIndexManager = $this->getServiceContainer()->get( 'CheckUserCentralIndexManager' );
				$centralRowsPurged = 0;
				do {
					$rowsPurgedInThisBatch = $checkUserCentralIndexManager->purgeExpiredRows(
						$cutoff, $domainId, $this->mBatchSize
					);
					$centralRowsPurged += $rowsPurgedInThisBatch;
					$this->waitForReplication();
				} while ( $rowsPurgedInThisBatch !== 0 );
				$this->output( "Purged $centralRowsPurged central index rows.\n" );
			}
		} else {
			$this->error( "Unable to acquire a lock to do the purging of CheckUser data. Skipping this." );
		}

		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$orphanedMappingRowsDeleted = $userAgentClientHintsManager->deleteOrphanedMapRows();
		$this->output( "Purged $orphanedMappingRowsDeleted orphaned client hint mapping rows.\n" );

		if ( $config->get( MainConfigNames::PutIPinRC ) ) {
			$this->output( "Purging data from recentchanges..." );
			$purgeRecentChanges = $this->runChild( PurgeRecentChanges::class );
			$purgeRecentChanges->execute();
		}

		$this->output( "Done.\n" );
	}

	/**
	 * Prunes data from the given CheckUser result table
	 *
	 * @param string $table
	 * @param string $cutoff
	 * @return int[] An array of two integers: The first being the rows deleted in $table and
	 *  the second in cu_useragent_clienthints_map.
	 */
	protected function prune( string $table, string $cutoff ) {
		/** @var CheckUserDataPurger $checkUserDataPurger */
		$checkUserDataPurger = $this->getServiceContainer()->get( 'CheckUserDataPurger' );
		$clientHintReferenceIds = new ClientHintsReferenceIds();

		$deletedCount = 0;
		do {
			$rowsPurgedInThisBatch = $checkUserDataPurger->purgeDataFromLocalTable(
				$this->getPrimaryDB(), $table, $cutoff, $clientHintReferenceIds, __METHOD__, $this->mBatchSize
			);
			$deletedCount += $rowsPurgedInThisBatch;
			$this->waitForReplication();
		} while ( $rowsPurgedInThisBatch !== 0 );

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$mappingRowsDeleted = $userAgentClientHintsManager->deleteMappingRows( $clientHintReferenceIds );

		return [ $deletedCount, $mappingRowsDeleted ];
	}
}

$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
