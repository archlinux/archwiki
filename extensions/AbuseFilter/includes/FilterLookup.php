<?php

namespace MediaWiki\Extension\AbuseFilter;

use DBAccessObjectUtils;
use IDBAccessObject;
use MediaWiki\Extension\AbuseFilter\Filter\ClosestFilterVersionNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\Filter\FilterNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\FilterVersionNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\HistoryFilter;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use stdClass;
use WANObjectCache;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * This class provides read access to the filters stored in the database.
 * @todo Cache exceptions
 */
class FilterLookup implements IDBAccessObject {
	public const SERVICE_NAME = 'AbuseFilterFilterLookup';

	// Used in getClosestVersion
	public const DIR_PREV = 'prev';
	public const DIR_NEXT = 'next';

	/**
	 * @var string[] The FULL list of fields in the abuse_filter table
	 */
	private const ALL_ABUSE_FILTER_FIELDS = [
		'af_id',
		'af_pattern',
		'af_user',
		'af_user_text',
		'af_timestamp',
		'af_enabled',
		'af_comments',
		'af_public_comments',
		'af_hidden',
		'af_hit_count',
		'af_throttled',
		'af_deleted',
		'af_actions',
		'af_global',
		'af_group'
	];

	/**
	 * @var ExistingFilter[] Individual filters cache. Keys can be integer IDs, or global names
	 */
	private $cache = [];

	/**
	 * @var ExistingFilter[][][] Cache of all active filters in each group. This is not related to
	 * the individual cache, and is replicated in WAN cache. The structure is
	 * [ local|global => [ group => [ ID => filter ] ] ]
	 * where the cache for each group has the same format as $this->cache
	 * Note that the keys are also in the form 'global-ID' for filters in 'global', although redundant.
	 */
	private $groupCache = [ 'local' => [], 'global' => [] ];

	/** @var HistoryFilter[] */
	private $historyCache = [];

	/** @var int[] */
	private $firstVersionCache = [];

	/** @var int[] */
	private $lastVersionCache = [];

	/**
	 * @var int[][] [ filter => [ historyID => [ prev, next ] ] ]
	 * @phan-var array<int,array<int,array{prev?:int,next?:int}>>
	 */
	private $closestVersionsCache = [];

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var WANObjectCache */
	private $wanCache;

	/** @var CentralDBManager */
	private $centralDBManager;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param WANObjectCache $cache
	 * @param CentralDBManager $centralDBManager
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		WANObjectCache $cache,
		CentralDBManager $centralDBManager
	) {
		$this->loadBalancer = $loadBalancer;
		$this->wanCache = $cache;
		$this->centralDBManager = $centralDBManager;
	}

	/**
	 * @param int $filterID
	 * @param bool $global
	 * @param int $flags One of the self::READ_* constants
	 * @return ExistingFilter
	 * @throws FilterNotFoundException if the filter doesn't exist
	 * @throws CentralDBNotAvailableException
	 */
	public function getFilter( int $filterID, bool $global, int $flags = self::READ_NORMAL ): ExistingFilter {
		$cacheKey = $this->getCacheKey( $filterID, $global );
		if ( $flags !== self::READ_NORMAL || !isset( $this->cache[$cacheKey] ) ) {
			[ $dbIndex, $dbOptions ] = DBAccessObjectUtils::getDBOptions( $flags );
			$dbr = $this->getDBConnection( $dbIndex, $global );

			$row = $dbr->selectRow(
				'abuse_filter',
				self::ALL_ABUSE_FILTER_FIELDS,
				[ 'af_id' => $filterID ],
				__METHOD__,
				$dbOptions
			);
			if ( !$row ) {
				throw new FilterNotFoundException( $filterID, $global );
			}
			$fname = __METHOD__;
			$getActionsCB = function () use ( $dbr, $fname, $row ): array {
				return $this->getActionsFromDB( $dbr, $fname, $row->af_id );
			};
			$this->cache[$cacheKey] = $this->filterFromRow( $row, $getActionsCB );
		}

		return $this->cache[$cacheKey];
	}

	/**
	 * Get all filters that are active (and not deleted) and in the given group
	 * @param string $group
	 * @param bool $global
	 * @param int $flags
	 * @return ExistingFilter[]
	 * @throws CentralDBNotAvailableException
	 */
	public function getAllActiveFiltersInGroup( string $group, bool $global, int $flags = self::READ_NORMAL ): array {
		$domainKey = $global ? 'global' : 'local';
		if ( $flags !== self::READ_NORMAL || !isset( $this->groupCache[$domainKey][$group] ) ) {
			if ( $global ) {
				$globalRulesKey = $this->getGlobalRulesKey( $group );
				$ret = $this->wanCache->getWithSetCallback(
					$globalRulesKey,
					WANObjectCache::TTL_WEEK,
					function () use ( $group, $global, $flags ) {
						return $this->getAllActiveFiltersInGroupFromDB( $group, $global, $flags );
					},
					[
						'checkKeys' => [ $globalRulesKey ],
						'lockTSE' => 300,
						'version' => 3
					]
				);
			} else {
				$ret = $this->getAllActiveFiltersInGroupFromDB( $group, $global, $flags );
			}

			$this->groupCache[$domainKey][$group] = [];
			foreach ( $ret as $key => $filter ) {
				$this->groupCache[$domainKey][$group][$key] = $filter;
				$this->cache[$key] = $filter;
			}
		}
		return $this->groupCache[$domainKey][$group];
	}

	/**
	 * @param string $group
	 * @param bool $global
	 * @param int $flags
	 * @return ExistingFilter[]
	 */
	private function getAllActiveFiltersInGroupFromDB( string $group, bool $global, int $flags ): array {
		[ $dbIndex, $dbOptions ] = DBAccessObjectUtils::getDBOptions( $flags );
		$dbr = $this->getDBConnection( $dbIndex, $global );

		$where = [
			'af_enabled' => 1,
			'af_deleted' => 0,
			'af_group' => $group,
		];
		if ( $global ) {
			$where['af_global'] = 1;
		}

		// Note, excluding individually cached filter now wouldn't help much, so take it as
		// an occasion to refresh the cache later
		$rows = $dbr->select(
			'abuse_filter',
			self::ALL_ABUSE_FILTER_FIELDS,
			$where,
			__METHOD__,
			$dbOptions
		);

		$fname = __METHOD__;
		$ret = [];
		foreach ( $rows as $row ) {
			$filterKey = $this->getCacheKey( $row->af_id, $global );
			$getActionsCB = function () use ( $dbr, $fname, $row ): array {
				return $this->getActionsFromDB( $dbr, $fname, $row->af_id );
			};
			$ret[$filterKey] = $this->filterFromRow(
				$row,
				// Don't pass a closure if global, as this is going to be serialized when caching
				$global ? $getActionsCB() : $getActionsCB
			);
		}
		return $ret;
	}

	/**
	 * @param int $dbIndex
	 * @param bool $global
	 * @return IDatabase
	 * @throws CentralDBNotAvailableException
	 */
	private function getDBConnection( int $dbIndex, bool $global ): IDatabase {
		if ( $global ) {
			return $this->centralDBManager->getConnection( $dbIndex );
		} else {
			return $this->loadBalancer->getConnectionRef( $dbIndex );
		}
	}

	/**
	 * @param IDatabase $db
	 * @param string $fname
	 * @param int $id
	 * @return array
	 */
	private function getActionsFromDB( IDatabase $db, string $fname, int $id ): array {
		$res = $db->select(
			'abuse_filter_action',
			[ 'afa_consequence', 'afa_parameters' ],
			[ 'afa_filter' => $id ],
			$fname
		);

		$actions = [];
		foreach ( $res as $actionRow ) {
			$actions[$actionRow->afa_consequence] =
				array_filter( explode( "\n", $actionRow->afa_parameters ) );
		}
		return $actions;
	}

	/**
	 * Get an old version of the given (local) filter, with its actions
	 *
	 * @param int $version Unique identifier of the version
	 * @param int $flags
	 * @return HistoryFilter
	 * @throws FilterVersionNotFoundException if the version doesn't exist
	 */
	public function getFilterVersion(
		int $version,
		int $flags = self::READ_NORMAL
	): HistoryFilter {
		if ( $flags !== self::READ_NORMAL || !isset( $this->historyCache[$version] ) ) {
			[ $dbIndex, $dbOptions ] = DBAccessObjectUtils::getDBOptions( $flags );
			$dbr = $this->loadBalancer->getConnectionRef( $dbIndex );

			$row = $dbr->selectRow(
				'abuse_filter_history',
				'*',
				[ 'afh_id' => $version ],
				__METHOD__,
				$dbOptions
			);
			if ( !$row ) {
				throw new FilterVersionNotFoundException( $version );
			}
			$this->historyCache[$version] = $this->filterFromHistoryRow( $row );
		}

		return $this->historyCache[$version];
	}

	/**
	 * @param int $filterID
	 * @return HistoryFilter
	 * @throws FilterNotFoundException If the filter doesn't exist
	 */
	public function getLastHistoryVersion( int $filterID ): HistoryFilter {
		if ( !isset( $this->lastVersionCache[$filterID] ) ) {
			$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
			$row = $dbr->selectRow(
				'abuse_filter_history',
				'*',
				[ 'afh_filter' => $filterID ],
				__METHOD__,
				[ 'ORDER BY' => 'afh_id DESC' ]
			);
			if ( !$row ) {
				throw new FilterNotFoundException( $filterID, false );
			}
			$filterObj = $this->filterFromHistoryRow( $row );
			$this->lastVersionCache[$filterID] = $filterObj->getHistoryID();
			$this->historyCache[$filterObj->getHistoryID()] = $filterObj;
		}
		return $this->historyCache[ $this->lastVersionCache[$filterID] ];
	}

	/**
	 * @param int $historyID
	 * @param int $filterID
	 * @param string $direction self::DIR_PREV or self::DIR_NEXT
	 * @return HistoryFilter
	 * @throws ClosestFilterVersionNotFoundException
	 */
	public function getClosestVersion( int $historyID, int $filterID, string $direction ): HistoryFilter {
		if ( !isset( $this->closestVersionsCache[$filterID][$historyID][$direction] ) ) {
			$comparison = $direction === self::DIR_PREV ? '<' : '>';
			$order = $direction === self::DIR_PREV ? 'DESC' : 'ASC';
			$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
			$row = $dbr->selectRow(
				'abuse_filter_history',
				'*',
				[
					'afh_filter' => $filterID,
					"afh_id $comparison" . $dbr->addQuotes( $historyID ),
				],
				__METHOD__,
				[ 'ORDER BY' => "afh_timestamp $order" ]
			);
			if ( !$row ) {
				throw new ClosestFilterVersionNotFoundException( $filterID, $historyID );
			}
			$filterObj = $this->filterFromHistoryRow( $row );
			$this->closestVersionsCache[$filterID][$historyID][$direction] = $filterObj->getHistoryID();
			$this->historyCache[$filterObj->getHistoryID()] = $filterObj;
		}
		$histID = $this->closestVersionsCache[$filterID][$historyID][$direction];
		return $this->historyCache[$histID];
	}

	/**
	 * Get the history ID of the first change to a given filter
	 *
	 * @param int $filterID
	 * @return int
	 * @throws FilterNotFoundException
	 */
	public function getFirstFilterVersionID( int $filterID ): int {
		if ( !isset( $this->firstVersionCache[$filterID] ) ) {
			$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
			$historyID = $dbr->selectField(
				'abuse_filter_history',
				'MIN(afh_id)',
				[ 'afh_filter' => $filterID ],
				__METHOD__
			);
			if ( $historyID === false ) {
				throw new FilterNotFoundException( $filterID, false );
			}
			$this->firstVersionCache[$filterID] = (int)$historyID;
		}

		return $this->firstVersionCache[$filterID];
	}

	/**
	 * Resets the internal cache of Filter objects
	 */
	public function clearLocalCache(): void {
		$this->cache = [];
		$this->groupCache = [ 'local' => [], 'global' => [] ];
		$this->historyCache = [];
		$this->firstVersionCache = [];
		$this->lastVersionCache = [];
		$this->closestVersionsCache = [];
	}

	/**
	 * Purge the shared cache of global filters in the given group.
	 * @note This doesn't purge the local cache
	 * @param string $group
	 */
	public function purgeGroupWANCache( string $group ): void {
		$this->wanCache->touchCheckKey( $this->getGlobalRulesKey( $group ) );
	}

	/**
	 * @param string $group The filter's group (as defined in $wgAbuseFilterValidGroups)
	 * @return string
	 */
	private function getGlobalRulesKey( string $group ): string {
		if ( !$this->centralDBManager->filterIsCentral() ) {
			return $this->wanCache->makeGlobalKey(
				'abusefilter',
				'rules',
				$this->centralDBManager->getCentralDBName(),
				$group
			);
		}

		return $this->wanCache->makeKey( 'abusefilter', 'rules', $group );
	}

	/**
	 * Note: this is private because no external caller should access DB rows directly.
	 * @param stdClass $row
	 * @return HistoryFilter
	 */
	private function filterFromHistoryRow( stdClass $row ): HistoryFilter {
		$actionsRaw = unserialize( $row->afh_actions );
		$actions = is_array( $actionsRaw ) ? $actionsRaw : [];
		$flags = $row->afh_flags ? explode( ',', $row->afh_flags ) : [];
		return new HistoryFilter(
			new Specs(
				trim( $row->afh_pattern ),
				$row->afh_comments,
				// FIXME: Make the DB field NOT NULL (T263324)
				(string)$row->afh_public_comments,
				array_keys( $actions ),
				// FIXME Make the field NOT NULL and add default (T263324)
				$row->afh_group ?? 'default'
			),
			new Flags(
				in_array( 'enabled', $flags, true ),
				in_array( 'deleted', $flags, true ),
				in_array( 'hidden', $flags, true ),
				in_array( 'global', $flags, true )
			),
			$actions,
			new LastEditInfo(
				(int)$row->afh_user,
				$row->afh_user_text,
				$row->afh_timestamp
			),
			(int)$row->afh_filter,
			$row->afh_id
		);
	}

	/**
	 * Note: this is private because no external caller should access DB rows directly.
	 * @param stdClass $row
	 * @param array[]|callable $actions
	 * @return ExistingFilter
	 */
	private function filterFromRow( stdClass $row, $actions ): ExistingFilter {
		return new ExistingFilter(
			new Specs(
				trim( $row->af_pattern ),
				// FIXME: Make the DB fields for these NOT NULL (T263324)
				(string)$row->af_comments,
				(string)$row->af_public_comments,
				$row->af_actions !== '' ? explode( ',', $row->af_actions ) : [],
				$row->af_group
			),
			new Flags(
				(bool)$row->af_enabled,
				(bool)$row->af_deleted,
				(bool)$row->af_hidden,
				(bool)$row->af_global
			),
			$actions,
			new LastEditInfo(
				(int)$row->af_user,
				$row->af_user_text,
				$row->af_timestamp
			),
			(int)$row->af_id,
			isset( $row->af_hit_count ) ? (int)$row->af_hit_count : null,
			isset( $row->af_throttled ) ? (bool)$row->af_throttled : null
		);
	}

	/**
	 * @param int $filterID
	 * @param bool $global
	 * @return string
	 */
	private function getCacheKey( int $filterID, bool $global ): string {
		return GlobalNameUtils::buildGlobalName( $filterID, $global );
	}
}
