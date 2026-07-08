<?php

namespace MediaWiki\Extension\AbuseFilter\Watcher;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

/**
 * Watcher that updates hit counts of filters
 */
class UpdateHitCountWatcher implements Watcher {
	public const SERVICE_NAME = ServiceNames::UpdateHitCountWatcher;

	public function __construct(
		private readonly LBFactory $lbFactory,
		private readonly CentralDBManager $centralDBManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function run( array $localFilters, array $globalFilters, string $group ): void {
		// Run in a DeferredUpdate to avoid primary database queries on raw/view requests (T274455)
		DeferredUpdates::addCallableUpdate( function () use ( $localFilters, $globalFilters ) {
			if ( $localFilters ) {
				$this->updateHitCounts( $this->lbFactory->getPrimaryDatabase(), $localFilters );
			}

			if ( $globalFilters ) {
				$fdb = $this->centralDBManager->getConnection( DB_PRIMARY );
				$this->updateHitCounts( $fdb, $globalFilters );
			}
		} );
	}

	private function updateHitCounts( IDatabase $dbw, array $loggedFilters ): void {
		$dbw->newUpdateQueryBuilder()
			->update( 'abuse_filter' )
			->set( [ 'af_hit_count=af_hit_count+1' ] )
			->where( [ 'af_id' => $loggedFilters ] )
			->caller( __METHOD__ )
			->execute();
	}
}
