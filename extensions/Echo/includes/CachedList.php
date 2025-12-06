<?php

namespace MediaWiki\Extension\Notifications;

use UnexpectedValueException;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Caches an ContainmentList within WANObjectCache to prevent needing
 * to load the nested list from a potentially slow source (mysql, etc).
 */
class CachedList implements ContainmentList {
	private const ONE_WEEK = 4233600;

	/** @var string[]|null */
	private $result;

	/**
	 * @param WANObjectCache $cache Bag to stored cached data in.
	 * @param string $partialCacheKey Partial cache key, $nestedList->getCacheKey() will be appended
	 *   to this to construct the cache key used.
	 * @param ContainmentList $nestedList The nested EchoContainmentList to cache the result of.
	 * @param int $timeout How long in seconds to cache the nested list, defaults to 1 week.
	 */
	public function __construct(
		protected WANObjectCache $cache,
		protected string $partialCacheKey,
		protected ContainmentList $nestedList,
		protected int $timeout = self::ONE_WEEK,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getValues() {
		if ( $this->result ) {
			return $this->result;
		}
		$this->result = $this->cache->getWithSetCallback(
			$this->getCacheKey(),
			$this->timeout,
			function () {
				$result = $this->nestedList->getValues();
				if ( !is_array( $result ) ) {
					throw new UnexpectedValueException( sprintf(
						"Expected array but received '%s' from '%s::getValues'",
						get_debug_type( $result ),
						get_class( $this->nestedList )
					) );
				}
				return $result;
			}
		);
		return $this->result;
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey() {
		return $this->cache->makeGlobalKey(
			'echo-containment-list',
			$this->partialCacheKey,
			$this->nestedList->getCacheKey()
		);
	}
}
