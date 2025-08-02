<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use LogicException;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\TorBlock\TorExitNodes;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class CheckUserGetIPsPager extends AbstractCheckUserPager {

	public function __construct(
		FormOptions $opts,
		UserIdentity $target,
		string $logType,
		TokenQueryManager $tokenQueryManager,
		UserGroupManager $userGroupManager,
		CentralIdLookup $centralIdLookup,
		IConnectionProvider $dbProvider,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		CheckUserLogService $checkUserLogService,
		UserFactory $userFactory,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserOptionsLookup $userOptionsLookup,
		DatabaseBlockStore $blockStore,
		?IContextSource $context = null,
		?LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager, $userGroupManager, $centralIdLookup,
			$dbProvider, $specialPageFactory, $userIdentityLookup, $checkUserLogService, $userFactory,
			$checkUserLookupUtils, $userOptionsLookup, $blockStore, $context, $linkRenderer, $limit );
		$this->checkType = SpecialCheckUser::SUBTYPE_GET_IPS;
	}

	/** @inheritDoc */
	public function formatRow( $row ): string {
		$lang = $this->getLanguage();
		$ip = IPUtils::prettifyIP( $row->ip );
		$templateParams = [];
		$templateParams['ipLink'] = $this->getSelfLink( $ip,
			[
				'user' => $ip,
				'reason' => $this->opts->getValue( 'reason' ),
			]
		);

		// If we get some results, it helps to know if the IP in general
		// has a lot more edits, e.g. "tip of the iceberg"...
		$ipEdits = $this->getCountForIPActions( $ip );
		if ( $ipEdits ) {
			$templateParams['ipEditCount'] =
				$this->msg( 'checkuser-ipeditcount' )->numParams( $ipEdits )->escaped();
			$templateParams['showIpCounts'] = true;
		}

		if ( IPUtils::isValidIPv6( $ip ) ) {
			$templateParams['ip64Link'] = $this->getSelfLink( '/64',
				[
					'user' => $ip . '/64',
					'reason' => $this->opts->getValue( 'reason' ),
				]
			);
			$ipEdits64 = $this->getCountForIPActions( $ip . '/64' );
			if ( $ipEdits64 && ( !$ipEdits || $ipEdits64 > $ipEdits ) ) {
				$templateParams['ip64EditCount'] =
					$this->msg( 'checkuser-ipeditcount-64' )->numParams( $ipEdits64 )->escaped();
				$templateParams['showIpCounts'] = true;
			}
		}
		$templateParams['blockLink'] = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'Block', $ip ),
			$this->msg( 'blocklink' )->text()
		);
		$templateParams['timeRange'] = $this->getTimeRangeString( $row->first, $row->last );
		$templateParams['editCount'] = $lang->formatNum( $row->count );

		// If this IP is blocked, give a link to the block log
		$templateParams['blockInfo'] = $this->getIPBlockInfo( $ip );
		$templateParams['toolLinks'] = $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse();
		return $this->templateParser->processTemplate( 'GetIPsLine', $templateParams );
	}

	/**
	 * Get information about any active blocks on a IP.
	 *
	 * @param string $ip the IP to get block info on.
	 * @return string
	 */
	protected function getIPBlockInfo( string $ip ): string {
		$block = $this->blockStore->newFromTarget( null, $ip );
		if ( $block instanceof DatabaseBlock ) {
			return $this->getBlockFlag( $block );
		} elseif (
			ExtensionRegistry::getInstance()->isLoaded( 'TorBlock' ) &&
			TorExitNodes::isExitNode( $ip )
		) {
			return Html::rawElement( 'strong', [], '(' . $this->msg( 'checkuser-torexitnode' )->escaped() . ')' );
		}
		return '';
	}

	/**
	 * Return "checkuser-ipeditcount" number or false
	 * if the number is the same as the number of edits
	 * made by the user on the IP.
	 *
	 * @param string $ip_or_range
	 * @return int|false
	 */
	protected function getCountForIPActions( string $ip_or_range ) {
		$count = false;
		$countsPerTable = [];
		// Get the total count and counts by this user.
		foreach ( self::RESULT_TABLES as $table ) {
			$countsPerTable[$table] = $this->getCountForIPActionsPerTable( $ip_or_range, $table );
		}
		// Display the count if at least one of the counts for a table has more actions
		// performed by all users than the current target user.
		$shouldDisplayCount = count( array_filter( $countsPerTable, static function ( $countsForTable ) {
			return $countsForTable !== null && $countsForTable['total'] > $countsForTable['by_this_target'];
		} ) );
		if ( $shouldDisplayCount ) {
			// If displaying the count, then sum the
			// 'total' count for all three tables.
			foreach ( $countsPerTable as $countsForTable ) {
				if ( $countsForTable !== null ) {
					$count += $countsForTable['total'];
				}
			}
		}
		return $count;
	}

	/**
	 * Return the number of actions performed by all users
	 * and the current target on a given IP or IP range.
	 *
	 * @param string $ipOrRange The IP or IP range to get the counts from.
	 * @param string $table The table to get these results from (valid tables in self::RESULT_TABLES).
	 * @return array<string, integer>|null
	 */
	protected function getCountForIPActionsPerTable( string $ipOrRange, string $table ): ?array {
		// Get the IExpression which allows selecting results for the IP or IP range.
		$expr = $this->checkUserLookupUtils->getIPTargetExpr( $ipOrRange, false, $table );
		if ( $expr === null ) {
			// Return null if no target conditions could be generated.
			return null;
		}
		// We are only using startOffset for the period feature.
		if ( $this->startOffset ) {
			$expr = $this->mDb->expr( $this->getTimestampField( $table ), '>=', $this->startOffset )
				->andExpr( $expr );
		}

		// Get counts for this IP / IP range
		$query = $this->mDb->newSelectQueryBuilder()
			->table( $table )
			->conds( $expr )
			->caller( __METHOD__ );
		$ipEdits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $ipEdits <= 1000 ) {
			$ipEdits = $query->fetchRowCount();
		}

		// Get counts for the target on this IP / IP range
		$expr = $this->mDb->expr( 'actor_user', '=', $this->target->getId() )
			->andExpr( $expr );
		$query = $this->mDb->newSelectQueryBuilder()
			->table( $table )
			->join(
				'actor',
				"{$table}_actor",
				"{$table}_actor.actor_id = {$this::RESULT_TABLE_TO_PREFIX[$table]}actor"
			)
			->conds( $expr )
			->caller( __METHOD__ );
		$userOnIpEdits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $userOnIpEdits <= 1000 ) {
			$userOnIpEdits = $query->fetchRowCount();
		}

		return [ 'total' => $ipEdits, 'by_this_target' => $userOnIpEdits ];
	}

	/** @inheritDoc */
	protected function groupResultsByIndexField( array $results ): array {
		// Group rows that have the same 'ip' and 'ip_hex' value.
		$resultsGroupedByIPAndIPHex = [];
		foreach ( $results as $row ) {
			if ( !array_key_exists( $row->ip, $resultsGroupedByIPAndIPHex ) ) {
				$resultsGroupedByIPAndIPHex[$row->ip] = [];
			}
			if ( !array_key_exists( $row->ip_hex, $resultsGroupedByIPAndIPHex[$row->ip] ) ) {
				$resultsGroupedByIPAndIPHex[$row->ip][$row->ip_hex] = [];
			}
			$resultsGroupedByIPAndIPHex[$row->ip][$row->ip_hex][] = $row;
		}
		// Combine the rows that have the same 'ip' and 'ip_hex' value.
		$groupedResults = [];
		$indexField = $this->getIndexField();
		foreach ( $resultsGroupedByIPAndIPHex as $ip => $ipHexArray ) {
			foreach ( $ipHexArray as $ipHex => $rows ) {
				$combinedRow = [
					'ip' => $ip,
					'ip_hex' => $ipHex,
					'count' => 0,
					'first' => '',
					'last' => '',
				];
				foreach ( $rows as $row ) {
					$combinedRow['count'] += $row->count;
					if ( $row->first && ( $combinedRow['first'] > $row->first || !$combinedRow['first'] ) ) {
						$combinedRow['first'] = $row->first;
					}
					if ( $row->last && ( $combinedRow['last'] < $row->last || !$combinedRow['last'] ) ) {
						$combinedRow['last'] = $row->last;
					}
				}
				$combinedRow = (object)$combinedRow;
				if ( array_key_exists( $combinedRow->$indexField, $groupedResults ) ) {
					$groupedResults[$combinedRow->$indexField][] = $combinedRow;
				} else {
					$groupedResults[$combinedRow->$indexField] = [ $combinedRow ];
				}
			}
		}
		return $groupedResults;
	}

	/** @inheritDoc */
	public function getQueryInfo( ?string $table = null ): array {
		if ( $table === null ) {
			throw new LogicException(
				"This ::getQueryInfo method must be provided with the table to generate " .
				"the correct query info"
			);
		}

		if ( $table === self::CHANGES_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuChanges();
		} elseif ( $table === self::LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuLogEvent();
		} elseif ( $table === self::PRIVATE_LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuPrivateEvent();
		}

		// Apply index, group by IP / IP hex, and filter results to just the target user.
		$queryInfo['options']['USE INDEX'] = [
			$table => $this->checkUserLookupUtils->getIndexName( $this->xfor, $table )
		];
		$queryInfo['options']['GROUP BY'] = [ 'ip', 'ip_hex' ];
		$queryInfo['conds']['actor_user'] = $this->target->getId();

		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuChanges(): array {
		$queryInfo = [
			'fields' => [
				'ip' => 'cuc_ip',
				'ip_hex' => 'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			'tables' => [ 'cu_changes', 'actor_cuc_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cuc_actor' => [ 'JOIN', 'actor_cuc_actor.actor_id=cuc_actor' ] ],
			'options' => [],
		];
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuLogEvent(): array {
		return [
			'fields' => [
				'ip' => 'cule_ip',
				'ip_hex' => 'cule_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cule_timestamp)',
				'last' => 'MAX(cule_timestamp)',
			],
			'tables' => [ 'cu_log_event', 'actor_cule_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cule_actor' => [ 'JOIN', 'actor_cule_actor.actor_id=cule_actor' ] ],
			'options' => [],
		];
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuPrivateEvent(): array {
		return [
			'fields' => [
				'ip' => 'cupe_ip',
				'ip_hex' => 'cupe_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cupe_timestamp)',
				'last' => 'MAX(cupe_timestamp)',
			],
			'tables' => [ 'cu_private_event', 'actor_cupe_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cupe_actor' => [ 'JOIN', 'actor_cupe_actor.actor_id=cupe_actor' ] ],
			'options' => [],
		];
	}

	/** @inheritDoc */
	public function getIndexField(): string {
		return 'last';
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getNavigationBar()
			. '<div id="checkuserresults" class="mw-checkuser-get-ips-results"><ul>';
	}

	/**
	 * Temporary measure until Get IPs query is fixed for pagination (T315612).
	 *
	 * @return bool
	 */
	protected function isNavigationBarShown() {
		return false;
	}
}
