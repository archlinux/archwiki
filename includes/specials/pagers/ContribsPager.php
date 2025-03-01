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
 * @ingroup Pager
 */

namespace MediaWiki\Pager;

use DateTime;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Pager for Special:Contributions
 *
 * Most of the work is done by the parent class. This class:
 * - handles using the ip_changes table in case of an IP range target
 * - provides static utility functions (kept here for backwards compatibility)
 *
 * @ingroup Pager
 */
class ContribsPager extends ContributionsPager {

	/**
	 * FIXME List services first T266484 / T290405
	 * @param IContextSource $context
	 * @param array $options
	 * @param LinkRenderer|null $linkRenderer
	 * @param LinkBatchFactory|null $linkBatchFactory
	 * @param HookContainer|null $hookContainer
	 * @param IConnectionProvider|null $dbProvider
	 * @param RevisionStore|null $revisionStore
	 * @param NamespaceInfo|null $namespaceInfo
	 * @param UserIdentity|null $targetUser
	 * @param CommentFormatter|null $commentFormatter
	 */
	public function __construct(
		IContextSource $context,
		array $options,
		?LinkRenderer $linkRenderer = null,
		?LinkBatchFactory $linkBatchFactory = null,
		?HookContainer $hookContainer = null,
		?IConnectionProvider $dbProvider = null,
		?RevisionStore $revisionStore = null,
		?NamespaceInfo $namespaceInfo = null,
		?UserIdentity $targetUser = null,
		?CommentFormatter $commentFormatter = null
	) {
		// Class is used directly in extensions - T266484
		$services = MediaWikiServices::getInstance();
		$dbProvider ??= $services->getConnectionProvider();

		parent::__construct(
			$linkRenderer ?? $services->getLinkRenderer(),
			$linkBatchFactory ?? $services->getLinkBatchFactory(),
			$hookContainer ?? $services->getHookContainer(),
			$revisionStore ?? $services->getRevisionStore(),
			$namespaceInfo ?? $services->getNamespaceInfo(),
			$commentFormatter ?? $services->getCommentFormatter(),
			$services->getUserFactory(),
			$context,
			$options,
			$targetUser
		);
	}

	/**
	 * Return the table targeted for ordering and continuation
	 *
	 * See T200259 and T221380.
	 *
	 * @warning Keep this in sync with self::getQueryInfo()!
	 *
	 * @return string
	 */
	private function getTargetTable() {
		$dbr = $this->getDatabase();
		$ipRangeConds = $this->targetUser->isRegistered()
			? null : $this->getIpRangeConds( $dbr, $this->target );
		if ( $ipRangeConds ) {
			return 'ip_changes';
		}

		return 'revision';
	}

	protected function getRevisionQuery() {
		$revQuery = $this->revisionStore->getQueryInfo( [ 'page', 'user' ] );
		$queryInfo = [
			'tables' => $revQuery['tables'],
			'fields' => array_merge( $revQuery['fields'], [ 'page_is_new' ] ),
			'conds' => [],
			'options' => [],
			'join_conds' => $revQuery['joins'],
		];

		// WARNING: Keep this in sync with getTargetTable()!
		$ipRangeConds = !$this->targetUser->isRegistered() ?
			$this->getIpRangeConds( $this->getDatabase(), $this->target ) :
			null;
		if ( $ipRangeConds ) {
			// Put ip_changes first (T284419)
			array_unshift( $queryInfo['tables'], 'ip_changes' );
			$queryInfo['join_conds']['revision'] = [
				'JOIN', [ 'rev_id = ipc_rev_id' ]
			];
			$queryInfo['conds'][] = $ipRangeConds;
		} else {
			$queryInfo['conds']['actor_name'] = $this->targetUser->getName();
			// Force the appropriate index to avoid bad query plans (T307295)
			$queryInfo['options']['USE INDEX']['revision'] = 'rev_actor_timestamp';
		}

		return $queryInfo;
	}

	/**
	 * Get SQL conditions for an IP range, if applicable
	 * @param IReadableDatabase $db
	 * @param string $ip The IP address or CIDR
	 * @return IExpression|false SQL for valid IP ranges, false if invalid
	 */
	private function getIpRangeConds( $db, $ip ) {
		// First make sure it is a valid range and they are not outside the CIDR limit
		if ( !self::isQueryableRange( $ip, $this->getConfig() ) ) {
			return false;
		}

		[ $start, $end ] = IPUtils::parseRange( $ip );

		return $db->expr( 'ipc_hex', '>=', $start )->and( 'ipc_hex', '<=', $end );
	}

	/**
	 * Is the given IP a range and within the CIDR limit?
	 *
	 * @internal Public only for SpecialContributions
	 * @param string $ipRange
	 * @param Config $config
	 * @return bool True if it is valid
	 * @since 1.30
	 */
	public static function isQueryableRange( $ipRange, $config ) {
		$limits = $config->get( MainConfigNames::RangeContributionsCIDRLimit );

		$bits = IPUtils::parseCIDR( $ipRange )[1];
		if (
			( $bits === false ) ||
			( IPUtils::isIPv4( $ipRange ) && $bits < $limits['IPv4'] ) ||
			( IPUtils::isIPv6( $ipRange ) && $bits < $limits['IPv6'] )
		) {
			return false;
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function getIndexField() {
		// The returned column is used for sorting and continuation, so we need to
		// make sure to use the right denormalized column depending on which table is
		// being targeted by the query to avoid bad query plans.
		// See T200259, T204669, T220991, and T221380.
		$target = $this->getTargetTable();
		switch ( $target ) {
			case 'revision':
				return 'rev_timestamp';
			case 'ip_changes':
				return 'ipc_rev_timestamp';
			default:
				wfWarn(
					__METHOD__ . ": Unknown value '$target' from " . static::class . '::getTargetTable()', 0
				);
				return 'rev_timestamp';
		}
	}

	/**
	 * @return string[]
	 */
	protected function getExtraSortFields() {
		// The returned columns are used for sorting, so we need to make sure
		// to use the right denormalized column depending on which table is
		// being targeted by the query to avoid bad query plans.
		// See T200259, T204669, T220991, and T221380.
		$target = $this->getTargetTable();
		switch ( $target ) {
			case 'revision':
				return [ 'rev_id' ];
			case 'ip_changes':
				return [ 'ipc_rev_id' ];
			default:
				wfWarn(
					__METHOD__ . ": Unknown value '$target' from " . static::class . '::getTargetTable()', 0
				);
				return [ 'rev_id' ];
		}
	}

	/**
	 * Set up date filter options, given request data.
	 *
	 * @param array $opts Options array
	 * @return array Options array with processed start and end date filter options
	 */
	public static function processDateFilter( array $opts ) {
		$start = $opts['start'] ?? '';
		$end = $opts['end'] ?? '';
		$year = $opts['year'] ?? '';
		$month = $opts['month'] ?? '';

		if ( $start !== '' && $end !== '' && $start > $end ) {
			$temp = $start;
			$start = $end;
			$end = $temp;
		}

		// If year/month legacy filtering options are set, convert them to display the new stamp
		if ( $year !== '' || $month !== '' ) {
			// Reuse getDateCond logic, but subtract a day because
			// the endpoints of our date range appear inclusive
			// but the internal end offsets are always exclusive
			$legacyTimestamp = ReverseChronologicalPager::getOffsetDate( $year, $month );
			$legacyDateTime = new DateTime( $legacyTimestamp->getTimestamp( TS_ISO_8601 ) );
			$legacyDateTime = $legacyDateTime->modify( '-1 day' );

			// Clear the new timestamp range options if used and
			// replace with the converted legacy timestamp
			$start = '';
			$end = $legacyDateTime->format( 'Y-m-d' );
		}

		$opts['start'] = $start;
		$opts['end'] = $end;

		return $opts;
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( ContribsPager::class, 'ContribsPager' );
