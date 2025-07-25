<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use Wikimedia\Rdbms\IConnectionProvider;

class RecentChangeSaveHandler implements RecentChange_saveHook {

	private CheckUserInsert $checkUserInsert;
	private JobQueueGroup $jobQueueGroup;
	private IConnectionProvider $dbProvider;

	public function __construct(
		CheckUserInsert $checkUserInsert,
		JobQueueGroup $jobQueueGroup,
		IConnectionProvider $dbProvider
	) {
		$this->checkUserInsert = $checkUserInsert;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->dbProvider = $dbProvider;
	}

	/**
	 * Add CheckUser events (edits, log entries, etc.) to the database by listening for entries being added to
	 * Special:RecentChanges. This gets most of the entries stored by CheckUser into the CheckUser result tables.
	 *
	 * @inheritDoc
	 */
	public function onRecentChange_save( $recentChange ) {
		$this->checkUserInsert->updateCheckUserData( $recentChange );
		$this->maybePruneIPData();
	}

	/**
	 * Calls mt_rand with the given parameters. This is a wrapper method to allow mocking in unit tests.
	 *
	 * @param int $min See docs for inbuilt mt_rand
	 * @param int $max See docs for inbuilt mt_rand
	 * @return int See docs for inbuilt mt_rand
	 */
	protected function mtRand( int $min, int $max ): int {
		return mt_rand( $min, $max );
	}

	/**
	 * Hook function to prune data from the cu_changes table
	 *
	 * The chance of actually pruning data is 1/10.
	 */
	private function maybePruneIPData() {
		if ( $this->mtRand( 0, 9 ) == 0 ) {
			$this->pruneIPData();
		}
	}

	/**
	 * Prunes at most 500 entries from the cu_changes,
	 * cu_private_event, and cu_log_event tables separately
	 * that have exceeded the maximum time that they can
	 * be stored.
	 */
	private function pruneIPData() {
		$this->jobQueueGroup->push(
			new JobSpecification(
				'checkuserPruneCheckUserDataJob',
				[ 'domainID' => $this->dbProvider->getReplicaDatabase()->getDomainID() ],
				[],
				null
			)
		);
	}
}
