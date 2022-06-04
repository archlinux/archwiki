<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use BagOStuff;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\User\Hook\GetAutoPromoteGroupsHook;
use MediaWiki\User\UserIdentity;
use ObjectCache;

class AutoPromoteGroupsHandler implements GetAutoPromoteGroupsHook {

	/** @var BagOStuff */
	private $cache;

	/** @var ConsequencesRegistry */
	private $consequencesRegistry;

	/** @var BlockAutopromoteStore */
	private $blockAutopromoteStore;

	/**
	 * @param BagOStuff $cache
	 * @param ConsequencesRegistry $consequencesRegistry
	 * @param BlockAutopromoteStore $blockAutopromoteStore
	 */
	public function __construct(
		BagOStuff $cache,
		ConsequencesRegistry $consequencesRegistry,
		BlockAutopromoteStore $blockAutopromoteStore
	) {
		$this->cache = $cache;
		$this->consequencesRegistry = $consequencesRegistry;
		$this->blockAutopromoteStore = $blockAutopromoteStore;
	}

	/**
	 * @param ConsequencesRegistry $consequencesRegistry
	 * @param BlockAutopromoteStore $blockAutopromoteStore
	 * @return self
	 * @todo Can we avoid this factory method?
	 */
	public static function factory(
		ConsequencesRegistry $consequencesRegistry,
		BlockAutopromoteStore $blockAutopromoteStore
	): self {
		return new self(
			ObjectCache::getInstance( 'hash' ),
			$consequencesRegistry,
			$blockAutopromoteStore
		);
	}

	/**
	 * @param UserIdentity $user
	 * @param string[] &$promote
	 */
	public function onGetAutoPromoteGroups( $user, &$promote ): void {
		if (
			in_array( 'blockautopromote', $this->consequencesRegistry->getAllEnabledActionNames() )
			&& $promote
		) {
			// Proxy the blockautopromote data to a faster backend, using an appropriate key
			$quickCacheKey = $this->cache->makeKey(
				'abusefilter',
				'blockautopromote',
				'quick',
				$user->getId()
			);
			$blocked = (bool)$this->cache->getWithSetCallback(
				$quickCacheKey,
				BagOStuff::TTL_PROC_LONG,
				function () use ( $user ) {
					return $this->blockAutopromoteStore->getAutoPromoteBlockStatus( $user );
				}
			);

			if ( $blocked ) {
				$promote = [];
			}
		}
	}
}
