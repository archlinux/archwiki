<?php

namespace MediaWiki\Extension\CheckUser\Jobs;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Extension\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\JobQueue\Job;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Prune CheckUser data from the CheckUser result tables (cu_changes, cu_log_event and cu_private_event), as well
 * as the associated Client Hints data.
 *
 * This is done via a job to avoid expensive deletes on post-send.
 */
class PruneCheckUserDataJob extends Job implements CheckUserQueryInterface {
	/** @inheritDoc */
	public function __construct(
		$title,
		array $params,
		private readonly CheckUserCentralIndexManager $checkUserCentralIndexManager,
		private readonly CheckUserDataPurger $checkUserDataPurger,
		private readonly Config $config,
		private readonly IConnectionProvider $dbProvider,
		private readonly UserAgentClientHintsManager $userAgentClientHintsManager,
	) {
		parent::__construct( 'checkuserPruneCheckUserDataJob', $params );
	}

	/** @return bool */
	public function run() {
		$dbw = $this->dbProvider->getPrimaryDatabase( $this->params['domainID'] );

		// Exit early if the database is in read-only mode to avoid log spam
		if ( $dbw->isReadOnly() ) {
			return true;
		}

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
			ConvertibleTimestamp::time() - $this->config->get( 'CUDMaxAge' )
		);

		$deletedReferenceIds = new ClientHintsReferenceIds();

		// Purge rows from each local CheckUser table that have an associated timestamp before the cutoff.
		foreach ( self::RESULT_TABLES as $table ) {
			$this->checkUserDataPurger
				->purgeDataFromLocalTable( $dbw, $table, $cutoff, $deletedReferenceIds, __METHOD__ );
		}

		// Delete the Client Hints mapping rows associated with the rows purged in the above for loop.
		$this->userAgentClientHintsManager->deleteMappingRows( $deletedReferenceIds );

		if ( $this->config->get( 'CheckUserWriteToCentralIndex' ) ) {
			// Purge expired rows from the central index tables where the rows are associated with this wiki
			$this->checkUserCentralIndexManager->purgeExpiredRows( $cutoff, $this->params['domainID'] );
		}

		return true;
	}
}
