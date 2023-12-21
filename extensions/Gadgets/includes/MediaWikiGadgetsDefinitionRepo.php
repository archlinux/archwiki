<?php

namespace MediaWiki\Extension\Gadgets;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ObjectCache;
use TextContent;
use WANObjectCache;
use Wikimedia\Rdbms\Database;

/**
 * Gadgets repo powered by MediaWiki:Gadgets-definition
 */
class MediaWikiGadgetsDefinitionRepo extends GadgetRepo {
	private const CACHE_VERSION = 4;

	/** @var array|null */
	private $definitions;

	/** @var string */
	protected $titlePrefix = 'MediaWiki:Gadget-';

	/**
	 * @param string $id
	 * @throws InvalidArgumentException
	 * @return Gadget
	 */
	public function getGadget( string $id ): Gadget {
		$gadgets = $this->loadGadgets();
		if ( !isset( $gadgets[$id] ) ) {
			throw new InvalidArgumentException( "No gadget registered for '$id'" );
		}

		return new Gadget( $gadgets[$id] );
	}

	public function getGadgetIds(): array {
		$gadgets = $this->loadGadgets();
		if ( $gadgets ) {
			return array_keys( $gadgets );
		}

		return [];
	}

	public function handlePageUpdate( LinkTarget $target ): void {
		if ( $target->getNamespace() === NS_MEDIAWIKI && $target->getText() === 'Gadgets-definition' ) {
			$this->purgeDefinitionCache();
		}
	}

	/**
	 * Purge the definitions cache, for example when MediaWiki:Gadgets-definition is edited.
	 */
	private function purgeDefinitionCache(): void {
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
		$key = $this->makeDefinitionCacheKey( $wanCache );

		$wanCache->delete( $key );
		$srvCache->delete( $key );
		$this->definitions = null;
	}

	/**
	 * @param WANObjectCache $cache
	 * @return string
	 */
	private function makeDefinitionCacheKey( WANObjectCache $cache ) {
		return $cache->makeKey(
			'gadgets-definition',
			Gadget::GADGET_CLASS_VERSION,
			self::CACHE_VERSION
		);
	}

	/**
	 * Get list of gadgets.
	 *
	 * @return array[] List of Gadget objects
	 */
	protected function loadGadgets(): array {
		if ( defined( 'MW_PHPUNIT_TEST' ) && MediaWikiServices::getInstance()->isStorageDisabled() ) {
			// Bail out immediately if storage is disabled. This should never happen in normal operations, but can
			// happen a lot in tests: this method is called from the UserGetDefaultOptions hook handler, so any test
			// that uses UserOptionsLookup will end up reaching this code, which is problematic if the test is not
			// in the Database group (T155147).
			return [];
		}
		// From back to front:
		//
		// 3. wan cache (e.g. memcached)
		//    This improves end-user latency and reduces database load.
		//    It is purged when the data changes.
		//
		// 2. server cache (e.g. APCu).
		//    Very short blind TTL, mainly to avoid high memcached I/O.
		//
		// 1. process cache. Faster repeat calls.
		if ( $this->definitions === null ) {
			$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
			$key = $this->makeDefinitionCacheKey( $wanCache );
			$this->definitions = $srvCache->getWithSetCallback(
				$key,
				// between 7 and 15 seconds to avoid memcached/lockTSE stampede (T203786)
				mt_rand( 7, 15 ),
				function () use ( $wanCache, $key ) {
					return $wanCache->getWithSetCallback(
						$key,
						// 1 day
						Gadget::CACHE_TTL,
						function ( $old, &$ttl, &$setOpts ) {
							// Reduce caching of known-stale data (T157210)
							$setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );

							return $this->fetchStructuredList();
						},
						[
							'version' => 2,
							// Avoid database stampede
							'lockTSE' => 300,
						 ]
					);
				}
			);
		}
		return $this->definitions;
	}

	/**
	 * Fetch list of gadgets and returns it as associative array of sections with gadgets
	 * e.g. [ $name => $gadget1, etc. ]
	 * @param string|null $forceNewText Injected text of MediaWiki:gadgets-definition [optional]
	 * @return array[]
	 */
	public function fetchStructuredList( $forceNewText = null ) {
		if ( $forceNewText === null ) {
			// T157210: avoid using wfMessage() to avoid staleness due to cache layering
			$title = Title::makeTitle( NS_MEDIAWIKI, 'Gadgets-definition' );
			$revRecord = MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionByTitle( $title );
			if ( !$revRecord
				|| !$revRecord->getContent( SlotRecord::MAIN )
				|| $revRecord->getContent( SlotRecord::MAIN )->isEmpty()
			) {
				return [];
			}

			$content = $revRecord->getContent( SlotRecord::MAIN );
			$g = ( $content instanceof TextContent ) ? $content->getText() : '';
		} else {
			$g = $forceNewText;
		}

		$gadgets = $this->listFromDefinition( $g );

		$source = $forceNewText !== null ? 'input text' : 'MediaWiki:Gadgets-definition';
		wfDebug( __METHOD__ . ": $source parsed, cache entry should be updated\n" );

		return $gadgets;
	}

	/**
	 * Generates a structured list of Gadget objects from a definition
	 *
	 * @param string $definition
	 * @return array[] List of Gadget objects indexed by the gadget's name.
	 */
	private function listFromDefinition( $definition ): array {
		$definition = preg_replace( '/<!--.*?-->/s', '', $definition );
		$lines = preg_split( '/(\r\n|\r|\n)+/', $definition );

		$gadgets = [];
		$section = '';

		foreach ( $lines as $line ) {
			$m = [];
			if ( preg_match( '/^==+ *([^*:\s|]+?)\s*==+\s*$/', $line, $m ) ) {
				$section = $m[1];
			} else {
				$gadget = $this->newFromDefinition( $line, $section );
				if ( $gadget ) {
					$gadgets[$gadget->getName()] = $gadget->toArray();
				}
			}
		}

		return $gadgets;
	}

	/**
	 * Creates an instance of this class from definition in MediaWiki:Gadgets-definition
	 * @param string $definition Gadget definition
	 * @param string $category
	 * @return Gadget|false Instance of Gadget class or false if $definition is invalid
	 */
	public function newFromDefinition( $definition, $category ) {
		$m = [];
		if ( !preg_match(
			'/^\*+ *([a-zA-Z](?:[-_:.\w ]*[a-zA-Z0-9])?)(\s*\[.*?\])?\s*((\|[^|]*)+)\s*$/',
			$definition,
			$m
		) ) {
			return false;
		}
		// NOTE: the gadget name is used as part of the name of a form field,
		// and must follow the rules defined in https://www.w3.org/TR/html4/types.html#type-cdata
		// Also, title-normalization applies.
		$info = [ 'category' => $category ];
		$info['name'] = trim( str_replace( ' ', '_', $m[1] ) );
		// If the name is too long, then RL will throw an exception when
		// we try to register the module
		if ( !Gadget::isValidGadgetID( $info['name'] ) ) {
			return false;
		}
		$info['definition'] = $definition;
		$options = trim( $m[2], ' []' );

		foreach ( preg_split( '/\s*\|\s*/', $options, -1, PREG_SPLIT_NO_EMPTY ) as $option ) {
			$arr = preg_split( '/\s*=\s*/', $option, 2 );
			$option = $arr[0];
			if ( isset( $arr[1] ) ) {
				$params = explode( ',', $arr[1] );
				$params = array_map( 'trim', $params );
			} else {
				$params = [];
			}

			switch ( $option ) {
				case 'ResourceLoader':
					$info['resourceLoaded'] = true;
					break;
				case 'requiresES6':
					$info['requiresES6'] = true;
					break;
				case 'dependencies':
					$info['dependencies'] = $params;
					break;
				case 'peers':
					$info['peers'] = $params;
					break;
				case 'rights':
					$info['requiredRights'] = $params;
					break;
				case 'hidden':
					$info['hidden'] = true;
					break;
				case 'actions':
					$info['requiredActions'] = $params;
					break;
				case 'skins':
					$info['requiredSkins'] = $params;
					break;
				case 'namespaces':
					$info['requiredNamespaces'] = $params;
					break;
				case 'contentModels':
					$info['requiredContentModels'] = $params;
					break;
				case 'default':
					$info['onByDefault'] = true;
					break;
				case 'package':
					$info['package'] = true;
					break;
				case 'targets':
					$info['targets'] = $params;
					break;
				case 'type':
					// Single value, not a list
					$info['type'] = $params[0] ?? '';
					break;
				case 'supportsUrlLoad':
					$val = $params[0] ?? '';
					$info['supportsUrlLoad'] = $val !== 'false';
					break;
			}
		}

		foreach ( preg_split( '/\s*\|\s*/', $m[3], -1, PREG_SPLIT_NO_EMPTY ) as $page ) {
			$page = $this->titlePrefix . $page;

			if ( preg_match( '/\.json$/', $page ) ) {
				$info['datas'][] = $page;
			} elseif ( preg_match( '/\.js/', $page ) ) {
				$info['scripts'][] = $page;
			} elseif ( preg_match( '/\.css/', $page ) ) {
				$info['styles'][] = $page;
			}
		}

		return new Gadget( $info );
	}
}
