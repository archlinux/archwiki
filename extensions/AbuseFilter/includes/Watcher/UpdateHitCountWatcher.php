<?php

namespace MediaWiki\Extension\AbuseFilter\Watcher;

use DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Watcher that updates hit counts of filters
 */
class UpdateHitCountWatcher implements Watcher {
	public const SERVICE_NAME = 'AbuseFilterUpdateHitCountWatcher';

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var CentralDBManager */
	private $centralDBManager;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param CentralDBManager $centralDBManager
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		CentralDBManager $centralDBManager
	) {
		$this->loadBalancer = $loadBalancer;
		$this->centralDBManager = $centralDBManager;
	}

	/**
	 * @inheritDoc
	 */
	public function run( array $localFilters, array $globalFilters, string $group ): void {
		// Run in a DeferredUpdate to avoid primary database queries on raw/view requests (T274455)
		DeferredUpdates::addCallableUpdate( function () use ( $localFilters, $globalFilters ) {
			if ( $localFilters ) {
				$this->updateHitCounts( $this->loadBalancer->getConnectionRef( DB_PRIMARY ), $localFilters );
			}

			if ( $globalFilters ) {
				$fdb = $this->centralDBManager->getConnection( DB_PRIMARY );
				$this->updateHitCounts( $fdb, $globalFilters );
			}
		} );
	}

	/**
	 * @param IDatabase $dbw
	 * @param array $loggedFilters
	 */
	private function updateHitCounts( IDatabase $dbw, array $loggedFilters ): void {
		$dbw->update(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $loggedFilters ],
			__METHOD__
		);
	}
}
