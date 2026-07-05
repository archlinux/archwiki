<?php

namespace MediaWiki\DB;

use MediaWiki\Deferred\AtomicSectionUpdate;
use MediaWiki\Deferred\DeferredUpdates;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IWriteQueryBuilder;

/**
 * @since 1.46
 * @unstable
 */
class WriteDuplicator {

	public function __construct(
		private IConnectionProvider $dbProvider,
		private readonly string $virtualDomain,
		private readonly bool $enabled = false
	) {
	}

	public function duplicate( IWriteQueryBuilder $queryBuilder ): void {
		if ( !$this->enabled ) {
			return;
		}
		$dbProvider = $this->dbProvider;
		$virtualDomain = $this->virtualDomain;

		// Avoid duplicating the write on the same database
		if (
			$dbProvider->getPrimaryDatabase()->getServer() ===
			$dbProvider->getPrimaryDatabase( $this->virtualDomain )->getServer()
		) {
			return;
		}

		DeferredUpdates::addUpdate(
			new AtomicSectionUpdate(
				$dbProvider->getPrimaryDatabase(),
				__METHOD__,
				static function () use ( $dbProvider, $queryBuilder, $virtualDomain ) {
					$dbw = $dbProvider->getPrimaryDatabase( $virtualDomain );
					$queryBuilder->connection( $dbw );
					$queryBuilder->execute();
				}
			)
		);
	}
}
