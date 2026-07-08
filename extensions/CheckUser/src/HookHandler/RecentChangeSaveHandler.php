<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\RecentChanges\Hook\RecentChange_saveHook;
use Wikimedia\Rdbms\IConnectionProvider;

class RecentChangeSaveHandler implements RecentChange_saveHook {

	public function __construct(
		private readonly CheckUserInsert $checkUserInsert,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	/**
	 * Add CheckUser events (edits, log entries, etc.) to the database by listening for entries being added to
	 * Special:RecentChanges. This gets most of the entries stored by CheckUser into the CheckUser result tables.
	 *
	 * @inheritDoc
	 */
	public function onRecentChange_save( $recentChange ) {
		// We silence replica warnings here because the save of a RecentChanges entry
		// will cause writes to the DB. This is not the fault of CheckUser because it
		// is only listening for these events (see for an example T340898).
		// Therefore having more warnings that may imply the issue is from CheckUser only
		// adds log spam.
		$this->checkUserInsert->updateCheckUserData( $recentChange, true );
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
