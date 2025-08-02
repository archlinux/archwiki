<?php

namespace MediaWiki\CheckUser\Jobs;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Prune CheckUser data from the CheckUser result tables (cu_changes, cu_log_event and cu_private_event), as well
 * as the associated Client Hints data.
 *
 * This is done via a job to avoid expensive deletes on post-send.
 */
class PruneCheckUserDataJob extends Job implements CheckUserQueryInterface {
	/** @inheritDoc */
	public function __construct( $title, $params ) {
		parent::__construct( 'checkuserPruneCheckUserDataJob', $params );
	}

	/** @return bool */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$dbw = $services->getConnectionProvider()->getPrimaryDatabase( $this->params['domainID'] );

		// Get an exclusive lock to purge data from the CheckUser tables. This is done to avoid multiple jobs and/or
		// the purgeOldData.php maintenance script attempting to purge at the same time.
		$key = CheckUserDataPurger::getPurgeLockKey( $this->params['domainID'] );
		$scopedLock = $dbw->getScopedLockAndFlush( $key, __METHOD__, 1 );
		if ( !$scopedLock ) {
			return true;
		}

		// Generate a cutoff timestamp from the wgCUDMaxAge configuration setting. Generating a fixed cutoff now
		// ensures that the cutoff remains the same throughout the job.
		$cutoff = $dbw->timestamp(
			ConvertibleTimestamp::time() - $services->getMainConfig()->get( 'CUDMaxAge' )
		);

		$deletedReferenceIds = new ClientHintsReferenceIds();

		/** @var CheckUserDataPurger $checkUserDataPurger */
		$checkUserDataPurger = $services->get( 'CheckUserDataPurger' );

		// Purge rows from each local CheckUser table that have an associated timestamp before the cutoff.
		foreach ( self::RESULT_TABLES as $table ) {
			$checkUserDataPurger->purgeDataFromLocalTable( $dbw, $table, $cutoff, $deletedReferenceIds, __METHOD__ );
		}

		// Delete the Client Hints mapping rows associated with the rows purged in the above for loop.
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $services->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->deleteMappingRows( $deletedReferenceIds );

		if ( $services->getMainConfig()->get( 'CheckUserWriteToCentralIndex' ) ) {
			// Purge expired rows from the central index tables where the rows are associated with this wiki
			/** @var CheckUserCentralIndexManager $checkUserCentralIndexManager */
			$checkUserCentralIndexManager = $services->get( 'CheckUserCentralIndexManager' );
			$checkUserCentralIndexManager->purgeExpiredRows( $cutoff, $this->params['domainID'] );
		}

		return true;
	}
}
