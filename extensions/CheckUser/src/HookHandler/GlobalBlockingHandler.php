<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGetRetroactiveAutoblockIPsHook;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GlobalBlockingHandler implements GlobalBlockingGetRetroactiveAutoblockIPsHook, CheckUserQueryInterface {

	private IConnectionProvider $dbProvider;
	private CentralIdLookup $centralIdLookup;
	private ActorStoreFactory $actorStoreFactory;

	public function __construct(
		IConnectionProvider $dbProvider,
		CentralIdLookup $centralIdLookup,
		ActorStoreFactory $actorStoreFactory
	) {
		$this->dbProvider = $dbProvider;
		$this->centralIdLookup = $centralIdLookup;
		$this->actorStoreFactory = $actorStoreFactory;
	}

	/** @inheritDoc */
	public function onGlobalBlockingGetRetroactiveAutoblockIPs( GlobalBlock $globalBlock, int $limit, array &$ips ) {
		$centralIndexDbr = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );

		// Get the target of the block, and if this is not set then return as we cannot then get IP addresses for it.
		// Fetch the name and not UserIdentity, as ::getTargetName still returns a string if the user does not exist
		// on the local wiki where the global block was performed.
		$target = $globalBlock->getTargetName();
		if ( !$target ) {
			return true;
		}

		// Get the central ID for the target, and if this fails then return because we need a central ID for lookups.
		$centralIdForTarget = $this->centralIdLookup->centralIdFromName( $target, CentralIdLookup::AUDIENCE_RAW );
		if ( !$centralIdForTarget ) {
			return true;
		}

		$foundIps = [];

		$lowerBound = null;
		$oldestTimestampSeenSoFar = null;
		$wikisToQuery = [];
		do {
			// Get a batch of wikis from cuci_user for the $target.
			$wikisQueryBuilder = $centralIndexDbr->newSelectQueryBuilder()
				->select( [ 'ciwm_wiki', 'ciu_timestamp' ] )
				->from( 'cuci_user' )
				->join( 'cuci_wiki_map', null, 'ciu_ciwm_id = ciwm_id' )
				->where( [ 'ciu_central_id' => $centralIdForTarget ] );
			if ( $oldestTimestampSeenSoFar ) {
				// Don't attempt to fetch wikis that we already have in our $wikisToQuery array.
				$wikisQueryBuilder->andWhere(
					$centralIndexDbr->expr( 'ciu_timestamp', '<=', $oldestTimestampSeenSoFar )
				);
			}
			if ( count( $wikisToQuery ) ) {
				// Exclude the wikis that we have already selected. This is necessary if two (or more) wikis share the
				// same ciu_timestamp value that is used as the $oldestTimestampSeenSoFar value.
				$wikisQueryBuilder->andWhere( $centralIndexDbr->expr( 'ciwm_wiki', '!=', $wikisToQuery ) );
			}
			$wikisQueryResult = $wikisQueryBuilder
				->orderBy( 'ciu_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( $limit )
				->caller( __METHOD__ )
				->fetchResultSet();

			// Break the loop if we have no more wikis to process and the wikis we have processed have been fully
			// checked for IPs (i.e. have no lower bound).
			if ( !$wikisQueryResult->numRows() && $lowerBound === null ) {
				break;
			}

			// Set the upper bound as the previous value for $oldestTimestampSeenSoFar, so we don't select IP
			// addresses we have already selected as part of previous local queries.
			$upperBound = $oldestTimestampSeenSoFar;

			// Add the new batch of wikis to the array of wikis to query.
			foreach ( $wikisQueryResult as $row ) {
				$wikisToQuery[] = $row->ciwm_wiki;
				$oldestTimestampSeenSoFar = $row->ciu_timestamp;
			}

			// Set the lower bound as $oldestTimestampSeenSoFar so that we don't select IPs used last before IPs on
			// wikis which are not in $wikisToQuery. We will look at these IPs on a future loop if we need to.
			// The exception to this is if there are no more wikis to fetch, which happens when the last query
			// to cuci_user had less than $limit rows.
			$lowerBound = $oldestTimestampSeenSoFar;
			if ( $wikisQueryResult->numRows() < $limit ) {
				$lowerBound = null;
			}

			foreach ( $wikisToQuery as $wikiID ) {
				$this->fetchIPAddressesFromLocalWiki( $wikiID, $target, $limit, $upperBound, $lowerBound, $foundIps );
			}
		} while ( count( $foundIps ) < $limit );

		// Sort the IPs by the last usage timestamp, and then use the first $limit IPs as the $ips to autoblock.
		arsort( $foundIps );
		$ips = array_slice( array_keys( $foundIps ), 0, $limit );

		// Return false if we provided IPs to autoblock, otherwise true to allow other handlers to provide IPs.
		return !count( $foundIps );
	}

	/**
	 * Fetch IP addresses used by the $target on the $wikiID and were last used between $upperBound and $lowerBound.
	 *
	 * @param string $wikiID The wiki ID for the local wiki
	 * @param string $target The target of the global block that is autoblocking
	 * @param int $limit The number of IPs which are requested
	 * @param string|null $upperBound IP addresses selected should not have their last usage timestamp as after this
	 *   timestamp.
	 * @param string|null $lowerBound IP addresses selected should not have their last usage timestamp as before this
	 *    timestamp.
	 * @param array &$foundIps The array of IP addresses which could be autoblocked, with the IP addresses as keys
	 *   and the timestamp that they were last used as the value.
	 * @return void
	 */
	private function fetchIPAddressesFromLocalWiki(
		string $wikiID, string $target, int $limit, ?string $upperBound, ?string $lowerBound, array &$foundIps
	) {
		$localDbr = $this->dbProvider->getReplicaDatabase( $wikiID );

		// Try to get the local UserIdentity for the global block target. If it does not exist or is not attached,
		// then return as we should not fetch IPs for this wiki.
		$localUserIdentityLookup = $this->actorStoreFactory->getUserIdentityLookup( $wikiID );
		$localUser = $localUserIdentityLookup->getUserIdentityByName( $target );
		if (
			!$localUser ||
			!$localUser->isRegistered() ||
			!$this->centralIdLookup->isAttached( $localUser, $wikiID )
		) {
			return;
		}

		foreach ( self::RESULT_TABLES as $table ) {
			$columnPrefix = self::RESULT_TABLE_TO_PREFIX[$table];
			// Get $limit IP addresses in descending order of their last use by this target from each CheckUser
			// result table.
			$wikiIDForGetIdCall = $this->dbProvider->getReplicaDatabase()->getDomainID() === $wikiID ?
				UserIdentityValue::LOCAL : $wikiID;
			$ipsSubQuery = $localDbr->newSelectQueryBuilder()
				->select( [ 'ip' => $columnPrefix . 'ip', 'timestamp' => "MAX({$columnPrefix}timestamp)" ] )
				->from( $table )
				->join( 'actor', null, "actor_id = {$columnPrefix}actor" )
				->where( [ 'actor_user' => $localUser->getId( $wikiIDForGetIdCall ) ] )
				->groupBy( $columnPrefix . 'ip' )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC );

			$ipsQuery = $localDbr->newSelectQueryBuilder()
				->select( [ 'ip', 'timestamp' ] )
				->from( $ipsSubQuery );
			if ( $lowerBound ) {
				$ipsQuery->where( $localDbr->expr( 'timestamp', '>=', $lowerBound ) );
			}
			if ( $upperBound ) {
				$ipsQuery->where( $localDbr->expr( 'timestamp', '<', $upperBound ) );
			}
			// We can add a LIMIT here, because if we get $limit IPs then we will not need to do another batch
			// for this table and wiki (because we have all the IPs needed).
			$ipsFromTableResult = $ipsQuery
				->limit( $limit )
				->caller( __METHOD__ )
				->fetchResultSet();

			// Store the found IPs with the most recent use of the IP address in an array to process later.
			foreach ( $ipsFromTableResult as $row ) {
				$ip = IPUtils::sanitizeIP( $row->ip );
				$timestamp = ConvertibleTimestamp::convert( TS_MW, $row->timestamp );

				$foundIps[$ip] = max( $foundIps[$ip] ?? false, $timestamp );
			}
		}
	}
}
