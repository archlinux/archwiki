<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStoreFactory;
use MediaWiki\Block\Hook\PerformRetroactiveAutoblockHook;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Config\Config;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class PerformRetroactiveAutoblockHandler implements PerformRetroactiveAutoblockHook, CheckUserQueryInterface {

	private IConnectionProvider $dbProvider;
	private DatabaseBlockStoreFactory $databaseBlockStoreFactory;
	private Config $config;

	public function __construct(
		IConnectionProvider $dbProvider,
		DatabaseBlockStoreFactory $databaseBlockStoreFactory,
		Config $config
	) {
		$this->dbProvider = $dbProvider;
		$this->databaseBlockStoreFactory = $databaseBlockStoreFactory;
		$this->config = $config;
	}

	/**
	 * Retroactively autoblocks the last IP used by the user (if it is a user)
	 * blocked by this block.
	 *
	 * @param DatabaseBlock $block
	 * @param int[] &$blockIds
	 * @return bool
	 */
	public function onPerformRetroactiveAutoblock( $block, &$blockIds ) {
		// If the maximum number of IPs to autoblock is 0, then defer to core or another extension to perform the
		// autoblocks.
		$maximumIPsToAutoblock = $this->config->get( 'CheckUserMaximumIPsToAutoblock' );
		if ( !$maximumIPsToAutoblock ) {
			return true;
		}

		// Check that the user is registered. If the user is not registered, then an autoblock does not make sense
		// (because it would have the same target as the existing $block). In this case return true in case that core
		// or another extension can handle this situation.
		$user = $block->getTargetUserIdentity();
		if ( !$user->isRegistered() ) {
			return true;
		}

		// Get the last used IPs for the user that is the target of this block. These IPs will come from all three
		// result tables if event table migration is set to read new.
		$dbr = $this->dbProvider->getReplicaDatabase( $block->getWikiId() );

		$lastUsedIPs = [];
		foreach ( self::RESULT_TABLES as $table ) {
			$tablePrefix = self::RESULT_TABLE_TO_PREFIX[$table];
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'ip' => $tablePrefix . 'ip', 'timestamp' => 'MAX(' . $tablePrefix . 'timestamp)' ] )
				->from( $table )
				->useIndex( $tablePrefix . 'actor_ip_time' )
				->join( 'actor', null, 'actor_id=' . $tablePrefix . 'actor' )
				->where( [ 'actor_user' => $user->getId( $block->getWikiId() ) ] )
				->limit( $maximumIPsToAutoblock )
				->groupBy( 'ip' )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchResultSet();

			// Use the results of the query to fill an array of IPs and their last usage timestamp.
			// This will be truncated later to meet the specified limit.
			foreach ( $res as $row ) {
				$timestamp = ConvertibleTimestamp::convert( TS_MW, $row->timestamp );
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				if ( !array_key_exists( $row->ip, $lastUsedIPs ) ) {
					$lastUsedIPs[$row->ip] = $timestamp;
				} else {
					$lastUsedIPs[$row->ip] = max( $lastUsedIPs[$row->ip], $timestamp );
				}
			}
		}

		// Sort the IPs by their last usage timestamp, and then truncate the list to a length of $maximumIPsToAutoblock.
		arsort( $lastUsedIPs );
		$lastUsedIPs = array_slice( array_flip( $lastUsedIPs ), 0, $maximumIPsToAutoblock );

		// Iterate through the list of IPs to autoblock and actually perform the autoblocks.
		$databaseBlockStore = $this->databaseBlockStoreFactory->getDatabaseBlockStore( $block->getWikiId() );
		foreach ( $lastUsedIPs as $ip ) {
			$id = $databaseBlockStore->doAutoblock( $block, $ip );
			if ( $id ) {
				$blockIds[] = $id;
			}
		}

		// The autoblocking of the most recently used IP(s) has been handled by CheckUser, so return false.
		return false;
	}
}
