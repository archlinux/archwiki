<?php

/**
 * GadgetRepo implementation where each gadget has a page in
 * the Gadget definition namespace, and scripts and styles are
 * located in the Gadget namespace.
 */
class GadgetDefinitionNamespaceRepo extends GadgetRepo {

	/**
	 * How long in seconds the list of gadget ids and
	 * individual gadgets should be cached for (1 day)
	 */
	const CACHE_TTL = 86400;

	/**
	 * @var WANObjectCache
	 */
	private $wanCache;

	/**
	 * @var string
	 */
	private $idsKey;

	public function __construct () {
		$this->idsKey = wfMemcKey( 'gadgets', 'namespace', 'ids' );
		$this->wanCache = ObjectCache::getMainWANInstance();
	}

	/**
	 * Purge the list of gadget ids when a page is deleted
	 * or if a new page is created
	 */
	public function purgeGadgetIdsList() {
		$this->wanCache->touchCheckKey( $this->idsKey );
	}

	/**
	 * Get a list of gadget ids from cache/database
	 *
	 * @return string[]
	 */
	public function getGadgetIds() {
		return $this->wanCache->getWithSetCallback(
			$this->idsKey,
			self::CACHE_TTL,
			function( $oldValue, &$ttl, array &$setOpts ) {
				$dbr = wfGetDB( DB_SLAVE );
				$setOpts += Database::getCacheSetOptions( $dbr );
				return $dbr->selectFieldValues(
					'page',
					'page_title',
					array(
						'page_namespace' => NS_GADGET_DEFINITION
					),
					__METHOD__
				);
			},
			array(
				'checkKeys' => array( $this->idsKey ),
				'pcTTL' => 5,
				'lockTSE' => '30',
			)
		);
	}

	/**
	 * Update the cache for a specific Gadget whenever it is updated
	 *
	 * @param string $id
	 */
	public function updateGadgetObjectCache( $id ) {
		$this->wanCache->touchCheckKey( $this->getGadgetCacheKey( $id ) );
	}

	private function getGadgetCacheKey( $id ) {
		return wfMemcKey( 'gadgets', 'object', md5( $id ), Gadget::GADGET_CLASS_VERSION );
	}

	/**
	 * @param string $id
	 * @throws InvalidArgumentException
	 * @return Gadget
	 */
	public function getGadget( $id ) {
		$key = $this->getGadgetCacheKey( $id );
		$gadget = $this->wanCache->getWithSetCallback(
			$key,
			self::CACHE_TTL,
			function( $old, &$ttl, array &$setOpts ) use ( $id ) {
				$setOpts += Database::getCacheSetOptions( wfGetDB( DB_SLAVE ) );
				$title = Title::makeTitleSafe( NS_GADGET_DEFINITION, $id );
				if ( !$title ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}
				$rev = Revision::newFromTitle( $title );
				if ( !$rev ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}

				$content = $rev->getContent();
				if ( !$content instanceof GadgetDefinitionContent ) {
					// Uhm...
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}

				return Gadget::newFromDefinitionContent( $id, $content );
			},
			array(
				'checkKeys' => array( $key ),
				'pcTTL' => 5,
				'lockTSE' => '30',
			)
		);

		if ( $gadget === null ) {
			throw new InvalidArgumentException( "No gadget registered for '$id'" );
		}

		return $gadget;
	}
}
