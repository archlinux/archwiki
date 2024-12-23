<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Linter;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database as MWDatabase;
use Wikimedia\Stats\IBufferingStatsdDataFactory;

/**
 * Lookup to find and cache the total amount of
 * lint errors in each category
 */
class TotalsLookup {
	public const CONSTRUCTOR_OPTIONS = [
		'LinterStatsdSampleFactor',
	];

	private ServiceOptions $options;
	private WANObjectCache $cache;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private CategoryManager $categoryManager;
	private Database $database;

	/**
	 * @param ServiceOptions $options
	 * @param WANObjectCache $cache
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param CategoryManager $categoryManager
	 * @param Database $database
	 */
	public function __construct(
		ServiceOptions $options,
		WANObjectCache $cache,
		IBufferingStatsdDataFactory $statsdDataFactory,
		CategoryManager $categoryManager,
		Database $database
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->cache = $cache;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->categoryManager = $categoryManager;
		$this->database = $database;
	}

	/**
	 * @param string $cat
	 * @return string
	 */
	private function makeKey( $cat ) {
		return $this->cache->makeKey( 'linter', 'total', $cat );
	}

	/**
	 * Get the totals for every category in the database
	 *
	 * @return array
	 */
	public function getTotals(): array {
		$cats = $this->categoryManager->getVisibleCategories();
		$fetchedTotals = false;
		$totals = [];
		foreach ( $cats as $cat ) {
			$totals[$cat] = $this->cache->getWithSetCallback(
				$this->makeKey( $cat ),
				WANObjectCache::TTL_INDEFINITE,
				function ( $oldValue, &$ttl, &$setOpts, $oldAsOf ) use ( $cat, &$fetchedTotals ) {
					$setOpts += MWDatabase::getCacheSetOptions(
						$this->database->getDBConnectionRef( DB_REPLICA )
					);
					if ( $fetchedTotals === false ) {
						$fetchedTotals = $this->database->getTotals();
					}
					return $fetchedTotals[$cat];
				},
				[
					'checkKeys' => [
						$this->cache->makeKey( 'linter', 'totals' ),
						$this->makeKey( $cat ),
					],
					'lockTSE' => 30,
				]
			);
		}
		return $totals;
	}

	/**
	 * Send stats to statsd and update totals cache
	 *
	 * @param array $changes
	 */
	public function updateStats( array $changes ) {
		$linterStatsdSampleFactor = $this->options->get( 'LinterStatsdSampleFactor' );

		if ( $linterStatsdSampleFactor === false ) {
			// Don't send to statsd, but update cache with $changes
			$raw = $changes['added'];
			foreach ( $changes['deleted'] as $cat => $count ) {
				if ( isset( $raw[$cat] ) ) {
					$raw[$cat] -= $count;
				} else {
					// Negative value
					$raw[$cat] = 0 - $count;
				}
			}

			foreach ( $raw as $cat => $count ) {
				if ( $count != 0 ) {
					// There was a change in counts, invalidate the cache
					$this->touchCategoryCache( $cat );
				}
			}
			return;
		} elseif ( mt_rand( 1, $linterStatsdSampleFactor ) != 1 ) {
			return;
		}

		$totals = $this->database->getTotals();
		$wiki = WikiMap::getCurrentWikiId();
		$stats = $this->statsdDataFactory;
		foreach ( $totals as $name => $count ) {
			$stats->gauge( "linter.category.$name.$wiki", $count );
		}
		$stats->gauge( "linter.totals.$wiki", array_sum( $totals ) );

		$this->touchAllCategoriesCache();
	}

	/**
	 * Have a single category be recalculated
	 *
	 * @param string $cat category name
	 */
	public function touchCategoryCache( $cat ) {
		$this->cache->touchCheckKey( $this->makeKey( $cat ) );
	}

	/**
	 * Have all categories be recalculated
	 */
	public function touchAllCategoriesCache() {
		$this->cache->touchCheckKey( $this->cache->makeKey( 'linter', 'totals' ) );
	}
}
