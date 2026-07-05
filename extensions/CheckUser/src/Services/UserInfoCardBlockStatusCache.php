<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Centralizes cache logic for the user info card's "indefinitely blocked" status.
 *
 * Uses two cache keys per user:
 * - A local key (makeKey) for local blocks, scoped to the current wiki.
 * - A global key (makeGlobalKey) for global blocks and CentralAuth locks,
 *   shared across all wikis in the same memcached cluster.
 *
 * Hook handlers that react to block/unblock events call invalidateLocal();
 * hook handlers for global blocks/locks call invalidateGlobal();
 * the render handler calls isIndefinitelyBlockedOrLocked().
 */
class UserInfoCardBlockStatusCache {

	public function __construct(
		private readonly WANObjectCache $wanCache,
		private readonly CompositeIndefiniteBlockChecker $localBlockChecker,
		private readonly CompositeIndefiniteBlockChecker $globalBlockChecker,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly StatsFactory $statsFactory,
	) {
	}

	/**
	 * Invalidate the cached local blocked status for a user so it will be
	 * re-queried on next access.
	 */
	public function invalidateLocal( string $username ): void {
		$this->wanCache->delete( $this->makeLocalCacheKey( $username ) );
	}

	/**
	 * Invalidate the cached global blocked/locked status for a user so it will be
	 * re-queried on next access. Because this uses a global cache key, the
	 * invalidation is visible to all wikis sharing the same memcached cluster.
	 */
	public function invalidateGlobal( string $username ): void {
		$this->wanCache->delete( $this->makeGlobalCacheKey( $username ) );
	}

	/**
	 * Check whether a user is indefinitely blocked (locally or globally)
	 * or locked, using both a process-level cache and WANObjectCache
	 * with lazy population.
	 *
	 * Note: the global cache key assumes the local user is attached to a
	 * global account. For unattached accounts, global blocks and CentralAuth
	 * locks would not actually apply, but this edge case is rare on WMF
	 * wikis and is deliberately ignored.
	 */
	public function isIndefinitelyBlockedOrLocked( string $username ): bool {
		$localValue = $this->checkCache(
			$this->makeLocalCacheKey( $username ),
			$this->localBlockChecker,
			$username,
			'local'
		);

		if ( $localValue ) {
			return true;
		}

		return $this->checkCache(
			$this->makeGlobalCacheKey( $username ),
			$this->globalBlockChecker,
			$username,
			'global'
		);
	}

	/**
	 * Check a single cache key, populating it on miss by resolving the
	 * username to a local user ID and running the given block checker.
	 */
	private function checkCache(
		string $cacheKey,
		CompositeIndefiniteBlockChecker $checker,
		string $username,
		string $type
	): bool {
		// Use 1/0 instead of true/false because WANObjectCache::getWithSetCallback()
		// skips caching when the callback returns false.
		$isBlocked = $this->wanCache->getWithSetCallback(
			$cacheKey,
			$this->wanCache::TTL_INDEFINITE,
			function () use ( $username, $checker, $type ) {
				$this->statsFactory->withComponent( 'CheckUser' )
					->getCounter( 'userinfocard_block_status_cache_miss' )
					->setLabel( 'type', $type )
					->increment();
				$user = $this->userIdentityLookup->getUserIdentityByName( $username );
				if ( $user === null || !$user->isRegistered() ) {
					return 0;
				}
				$userId = $user->getId();
				$unblockedIds = $checker
					->getUserIdsNotIndefinitelyBlocked( [ $userId ] );
				return in_array( $userId, $unblockedIds, true ) ? 0 : 1;
			},
			[
				// Stampede protection: after delete() places a tombstone,
				// only one thread regenerates; others serve the interim
				// value or busyValue.
				'lockTSE' => 30,
				// Fallback when no stale value exists and another thread
				// holds the regeneration lock (fail-open: assume not blocked).
				'busyValue' => 0,
				// Process-level cache avoids repeated memcached round-trips
				// for the same user within a single request.
				'pcTTL' => $this->wanCache::TTL_PROC_LONG,
			]
		);
		return (bool)$isBlocked;
	}

	private function makeLocalCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-local-blocked', $username );
	}

	private function makeGlobalCacheKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-global-blocked', $username );
	}
}
