<?php

namespace MediaWiki\CheckUser\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Given an IP, return every known temporary account that has edited from it.
 *
 * Note that in WMF production, using this service outside of Extension:CheckUser
 * requires consultation with Trust & Safety Product.
 */
class CheckUserTemporaryAccountsByIPLookup implements CheckUserQueryInterface {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserMaximumRowCount',
	];
	private JobQueueGroup $jobQueueGroup;
	private IConnectionProvider $connectionProvider;
	private ServiceOptions $serviceOptions;
	private TempUserConfig $tempUserConfig;
	private UserFactory $userFactory;
	private UserOptionsLookup $userOptionsLookup;
	private PermissionManager $permissionManager;
	private CheckUserLookupUtils $checkUserLookupUtils;

	public function __construct(
		ServiceOptions $serviceOptions,
		IConnectionProvider $connectionProvider,
		JobQueueGroup $jobQueueGroup,
		TempUserConfig $tempUserConfig,
		UserFactory $userFactory,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		CheckUserLookupUtils $checkUserLookupUtils
	) {
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->serviceOptions = $serviceOptions;
		$this->connectionProvider = $connectionProvider;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->tempUserConfig = $tempUserConfig;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->permissionManager = $permissionManager;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
	}

	/**
	 * @param string $ip The IP address to use in the lookup
	 * @param Authority $authority The authority making the request
	 * @param bool $shouldLog Should a log entry be created to show that this data was accessed? By default,
	 *   create a log entry. Classes that extend AbstractTemporaryAccountHandler don't need to set this to true,
	 *   because AbstractTemporaryAccountHandler creates a log entry.
	 * @param int|null $limit The maximum number of rows to fetch.
	 * @return StatusValue A good status will have a list of account names or empty list if none were found;
	 *  a bad status will have the relevant permission error encountered
	 * @throws InvalidArgumentException If the $ip could not be parsed as a valid IP or range
	 */
	public function get( string $ip, Authority $authority, bool $shouldLog = true, ?int $limit = null ): StatusValue {
		// TODO: Use a trait for permissions, to avoid duplication with
		// AbstractTemporaryAccountHandler::checkPermissions
		$status = $this->checkPermissions( $authority );

		if ( !$status->isGood() ) {
			return $status;
		}

		if ( $shouldLog ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$authority->getUser(),
					$ip,
					TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP
				)
			);
		}

		$allAccounts = $this->getTempAccountsFromIPAddress( $ip, $limit );

		// If the user can see hidden accounts, return the result
		if ( $authority->isAllowed( 'hideuser' ) ) {
			return StatusValue::newGood( $allAccounts );
		}

		// Don't return hidden accounts to authorities who cannot view them
		$accounts = [];
		foreach ( $allAccounts as $account ) {
			if ( !$this->userFactory->newFromName( $account )->isHidden() ) {
				$accounts[] = $account;
			}
		}
		return StatusValue::newGood( $accounts );
	}

	/**
	 * Given an IP address or range, return all temporary accounts associated with
	 * it. This function should be called from a wrapper so that `checkPermissions()`
	 * can be run if necessary.
	 *
	 * @param string $ip The IP address or range to use in the lookup
	 * @param int|null $limit The maximum number of rows to fetch.
	 * @return string[]
	 * @throws InvalidArgumentException if the provided IP is invalid
	 */
	private function getTempAccountsFromIPAddress( string $ip, ?int $limit = null ): array {
		if ( !IPUtils::isIPAddress( $ip ) ) {
			throw new InvalidArgumentException( "Invalid IP $ip passed" );
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		// If no limit is supplied, set the default to CheckUserMaximumRowCount.
		$limit = $this->getQueryLimit( $limit );

		// Get accounts from cu_changes and cu_log_event, sorted by timestamp descending.
		// They'll be combined so that in case of duplicate entries, the more recent
		// timestamp can be prioritized. Save the account name as the key for de-duping.
		$ipConds = $this->checkUserLookupUtils->getIPTargetExpr(
			$ip,
			false,
			self::CHANGES_TABLE
		);
		if ( $ipConds === null ) {
			throw new InvalidArgumentException( "Unable to acquire subquery for $ip" );
		}
		$distinctCuChangesAccountRows = $dbr->newSelectQueryBuilder()
			->fields( [ 'actor_name', 'timestamp' => 'MAX(cuc_timestamp)' ] )
			->table( self::CHANGES_TABLE )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
			->where( $ipConds )
			->groupBy( 'actor_name' )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();
		$distinctCuChangesAccounts = [];
		foreach ( $distinctCuChangesAccountRows as $accountRow ) {
			$distinctCuChangesAccounts[$accountRow->actor_name] = $accountRow->timestamp;

		}

		$ipConds = $this->checkUserLookupUtils->getIPTargetExpr(
			$ip,
			false,
			self::LOG_EVENT_TABLE
		);
		if ( $ipConds === null ) {
			throw new InvalidArgumentException( "Unable to acquire subquery for $ip" );
		}
		$distinctCuLogEventAccountRows = $dbr->newSelectQueryBuilder()
			->fields( [ 'actor_name', 'timestamp' => 'MAX(cule_timestamp)' ] )
			->table( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
			->where( $ipConds )
			->groupBy( 'actor_name' )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();
		$distinctCuLogEventAccounts = [];
		foreach ( $distinctCuLogEventAccountRows as $accountRow ) {
			$distinctCuLogEventAccounts[$accountRow->actor_name] = $accountRow->timestamp;
		}

		return $this->sortEntitiesByTimestamp( $limit, $distinctCuChangesAccounts, $distinctCuLogEventAccounts );
	}

	/**
	 * Given a temporary account:
	 * 1. Find all IPs associated with the account
	 * 2. Find all temp accounts on all the IPs
	 * 3. Return the sum of them
	 * @param UserIdentity $user The temporary account to start lookup with
	 * @param int|null $limit The maximum number of rows to fetch
	 * @return int Final sum, up to the limit if one is passed
	 */
	public function getAggregateActiveTempAccountCount( UserIdentity $user, ?int $limit = null ) {
		if ( !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			throw new InvalidArgumentException( 'Invalid user passed; only temporary accounts are supported' );
		}

		$ipsFromTempAccount = $this->getDistinctIPsFromTempAccount( $user );

		// Store accounts found because each lookup isn't guaranteed to be a unique set
		// when compared against other lookups being performed
		$accounts = [];
		foreach ( $ipsFromTempAccount as $ip ) {
			if ( $limit && count( $accounts ) >= $limit ) {
				break;
			}

			if ( IPUtils::isIPv6( $ip ) ) {
				// If IPv6, we want to look up the entire /64 range
				// To de-dupe IPv6 lookups, the IP passed through is converted into the
				// beginning of the range before returning the CIDR reprsentation
				[ $ipHex ] = IPUtils::parseRange( $ip );
				$ip = IPUtils::formatHex( $ipHex ) . '/64';
			}
			$tempAccountsOnIPCount = $this->getTempAccountsFromIPAddress( $ip, $limit );
			foreach ( $tempAccountsOnIPCount as $account ) {
				// Store name as a key so that the set at the end is unique
				$accounts[ $account ] = true;
			}
		}
		return $limit ? min( $limit, count( $accounts ) ) : count( $accounts );
	}

	/**
	 * Instead of returning a precise number, return the bucket the number fits in.
	 * This has a default bucket range defined from work on T388718 but a different set
	 * of ranges can be used in the following format:
	 * [
	 *   'max' => maxCount // expected to be used like "maxCount+"
	 *   'ranges' => [
	 *     [ min1, max1 ], // expected to be used in a string like "min1-min2"
	 *     [ min2, max2 ]
	 *   ]
	 * ]
	 * Mins and maxes should be inclusive. See function for example.
	 * This function will return a bucketStart and a bucketEnd. If the count matches
	 * the min of 0 or the specified max, bucketStart and bucketEnd will be identical.
	 *
	 * @param int $count Count to be bucketed
	 * @param array|null $buckets Bucket structure, see comments and fallback example
	 * @return int[] Bucket the count belongs to
	 */
	public function getBucketedCount( int $count, ?array $buckets = null ): array {
		if ( $buckets === null ) {
			$buckets = [
				'max' => 11,
				'ranges' => [
					[ 1, 2 ],
					[ 3, 5 ],
					[ 6, 10 ],
				],
			];
		}
		if ( !$count ) {
			return [ 0, 0 ];
		}
		if ( $count >= $buckets['max'] ) {
			return [ $buckets['max'], $buckets['max'] ];
		}

		$bucketStart = 0;
		$bucketEnd = 0;
		foreach ( $buckets['ranges'] as $range ) {
			if ( $count >= $range[0] && $count <= $range[1] ) {
				$bucketStart = $range[0];
				$bucketEnd = $range[1];
				break;
			}
		}
		return [
			$bucketStart, $bucketEnd,
		];
	}

	/**
	 * For use by account <=> ip functions that need to sort through the results of
	 * multiple databases and return a final list. Queries are independent and may
	 * return duplicate identifiers when combined without additional processing.
	 * Given an arbitrary number of arrays which conform to the expected schema, return
	 * a single array of entity ids sorted by timestamp descending and sliced to limit
	 *
	 * @param int $limit
	 * @param string[] ...$entities [ ip/account => timestamp ]
	 * @return string[] [ ip/account ]
	 */
	private function sortEntitiesByTimestamp( $limit, ...$entities ) {
		$sorted = [];
		foreach ( $entities as $entitySet ) {
			foreach ( $entitySet as $entity => $timestamp ) {
				if ( !isset( $sorted[$entity] ) ) {
					$sorted[$entity] = $timestamp;
				} elseif ( $sorted[$entity] < $timestamp ) {
					$sorted[$entity] = $timestamp;
				}
			}
		}

		// Results may be out of order, re-order them by timestamp descending
		uasort( $sorted, static function ( $a, $b ) {
			return ( $a <=> $b ) * -1;
		} );

		// Drop the timestamp as we only care about the entity value which is now sorted in descending time order
		$sorted = array_keys( $sorted );

		// Slice to respect the limit and return the final result
		return array_slice( $sorted, 0, $limit );
	}

	/**
	 * Given a temporary account, return all IPs associated with it via public actions only.
	 * This function should be called from a wrapper so that `checkPermissions()` can
	 * be run if necessary. Functions like `getAggregateActiveTempAccountCount()`
	 * don't need to because they return an aggregate number, which is less restricted.
	 *
	 * @param UserIdentity $user The temporay account to look up
	 * @param int|null $limit The maximum number of rows to fetch
	 * @return string[] An array of all matching IPs, up to the limit
	 */
	private function getDistinctIPsFromTempAccount( UserIdentity $user, ?int $limit = null ) {
		if ( !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			throw new InvalidArgumentException( 'Invalid user passed; only temporary accounts are supported' );
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$ips = [];

		// If no limit is supplied, set the default to CheckUserMaximumRowCount.
		$limit = $this->getQueryLimit( $limit );

		// Get IPs from cu_changes and cu_log_event, sorted by timestamp descending.
		// They'll be combined so that in case of duplicate entries, the more recent
		// timestamp can be prioritized. Save the IP as the key for de-duping.
		$distinctCuChangesIPRows = $dbr->newSelectQueryBuilder()
			->select( [ 'cuc_ip_hex', 'timestamp' => 'MAX(cuc_timestamp)' ] )
			->groupBy( 'cuc_ip_hex' )
			->from( 'cu_changes' )
			// T338276
			->useIndex( 'cuc_actor_ip_time' )
			->join( 'actor', null, 'cuc_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName(),
			] )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$distinctCuChangesIPs = [];
		foreach ( $distinctCuChangesIPRows as $ipRow ) {
			$ip = IPUtils::formatHex( $ipRow->cuc_ip_hex );
			$distinctCuChangesIPs[$ip] = $ipRow->timestamp;
		}

		$distinctCuLogEventIPRows = $dbr->newSelectQueryBuilder()
			->select( [ 'cule_ip_hex', 'timestamp' => 'MAX(cule_timestamp)' ] )
			->groupBy( 'cule_ip_hex' )
			->from( 'cu_log_event' )
			// T338276
			->useIndex( 'cule_actor_ip_time' )
			->join( 'actor', null, 'cule_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName(),
			] )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$distinctCuLogEventIPs = [];
		foreach ( $distinctCuLogEventIPRows as $ipRow ) {
			$ip = IPUtils::formatHex( $ipRow->cule_ip_hex );
			$distinctCuLogEventIPs[$ip] = $ipRow->timestamp;
		}

		return $this->sortEntitiesByTimestamp( $limit, $distinctCuChangesIPs, $distinctCuLogEventIPs );
	}

	private function checkPermissions( Authority $authority ): StatusValue {
		if ( !$authority->isNamed() ) {
			// n.b. Here and for checkuser-rest-access-denied-blocked-user, the message
			// key specifies "REST", but the message is generic enough to reuse in this context.
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}
		if (
			!$this->permissionManager->userHasRight(
				$authority->getUser(),
				'checkuser-temporary-account-no-preference'
			) &&
			(
				!$this->permissionManager->userHasRight(
					$authority->getUser(),
					'checkuser-temporary-account'
				) ||
				!$this->userOptionsLookup->getOption(
					$authority->getUser(),
					'checkuser-temporary-account-enable'
				)
			)
		) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}

		if ( $authority->getBlock() ) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied-blocked-user' );
		}
		return StatusValue::newGood();
	}

	/**
	 * @param int|null $limit A limit if passed, otherwise the function will provide a fallback
	 * @return int
	 */
	public function getQueryLimit( ?int $limit = null ) {
		if ( !$limit ) {
			$limit = $this->serviceOptions->get( 'CheckUserMaximumRowCount' );
		} else {
			// The limit is the smaller of the user-provided limit parameter and the maximum row count.
			$limit = min( $limit, $this->serviceOptions->get( 'CheckUserMaximumRowCount' ) );
		}
		return $limit;
	}
}
