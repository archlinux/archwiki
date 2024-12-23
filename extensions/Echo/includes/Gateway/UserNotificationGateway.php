<?php

namespace MediaWiki\Extension\Notifications\Gateway;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\NotifUser;
use MediaWiki\User\UserIdentity;

/**
 * Database gateway which handles direct database interaction with the
 * echo_notification & echo_event for a user, that wouldn't require
 * loading data into models
 */
class UserNotificationGateway {

	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * @var UserIdentity
	 */
	protected $user;

	/**
	 * The tables for this gateway.
	 *
	 * @var string
	 */
	protected static $eventTable = 'echo_event';

	/**
	 * The tables for this gateway.
	 *
	 * @var string
	 */
	protected static $notificationTable = 'echo_notification';

	/**
	 * @var Config
	 */
	private $config;

	public function __construct( UserIdentity $user, DbFactory $dbFactory, Config $config ) {
		$this->user = $user;
		$this->dbFactory = $dbFactory;
		$this->config = $config;
	}

	public function getDB( $dbSource ) {
		return $this->dbFactory->getEchoDb( $dbSource );
	}

	/**
	 * Mark notifications as read
	 * @param array $eventIDs
	 * @return bool Returns true when data has been updated in DB, false on
	 *   failure, or when there was nothing to update
	 */
	public function markRead( array $eventIDs ) {
		if ( !$eventIDs ) {
			return false;
		}

		$dbw = $this->getDB( DB_PRIMARY );
		if ( $dbw->isReadOnly() ) {
			return false;
		}

		$success = true;
		foreach (
			array_chunk( $eventIDs, $this->config->get( 'UpdateRowsPerQuery' ) ) as $batch
		) {
			$dbw->newUpdateQueryBuilder()
				->update( self::$notificationTable )
				->set( [ 'notification_read_timestamp' => $dbw->timestamp( wfTimestampNow() ) ] )
				->where( [
					'notification_user' => $this->user->getId(),
					'notification_event' => $batch,
					'notification_read_timestamp' => null,
				] )
				->caller( __METHOD__ )
				->execute();
			$success = $dbw->affectedRows() && $success;
		}

		return $success;
	}

	/**
	 * Mark notifications as unread
	 * @param array $eventIDs
	 * @return bool Returns true when data has been updated in DB, false on
	 *   failure, or when there was nothing to update
	 */
	public function markUnRead( array $eventIDs ) {
		if ( !$eventIDs ) {
			return false;
		}

		$dbw = $this->getDB( DB_PRIMARY );
		if ( $dbw->isReadOnly() ) {
			return false;
		}

		$success = true;
		foreach (
			array_chunk( $eventIDs, $this->config->get( 'UpdateRowsPerQuery' ) ) as $batch
		) {
			$dbw->newUpdateQueryBuilder()
				->update( self::$notificationTable )
				->set( [ 'notification_read_timestamp' => null ] )
				->where( [
					'notification_user' => $this->user->getId(),
					'notification_event' => $batch,
					$dbw->expr( 'notification_read_timestamp', '!=', null ),
				] )
				->caller( __METHOD__ )
				->execute();
			$success = $dbw->affectedRows() && $success;
		}
		return $success;
	}

	/**
	 * Mark all notification as read, use NotifUser::markAllRead() instead
	 * @deprecated may need this when running in a job or revive this when we
	 * have updateJoin()
	 */
	public function markAllRead() {
		$dbw = $this->getDB( DB_PRIMARY );
		if ( $dbw->isReadOnly() ) {
			return false;
		}

		$dbw->newUpdateQueryBuilder()
			->update( self::$notificationTable )
			->set( [ 'notification_read_timestamp' => $dbw->timestamp( wfTimestampNow() ) ] )
			->where( [
				'notification_user' => $this->user->getId(),
				'notification_read_timestamp' => null,
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}

	/**
	 * Get notification count for the types specified
	 * @param int $dbSource use primary database or replica storage to pull count
	 * @param array $eventTypesToLoad event types to retrieve
	 * @param int $cap Max count
	 * @return int
	 */
	public function getCappedNotificationCount(
		$dbSource,
		array $eventTypesToLoad = [],
		$cap = NotifUser::MAX_BADGE_COUNT
	) {
		// double check
		if ( !in_array( $dbSource, [ DB_REPLICA, DB_PRIMARY ] ) ) {
			$dbSource = DB_REPLICA;
		}

		if ( !$eventTypesToLoad ) {
			return 0;
		}

		$db = $this->getDB( $dbSource );
		return $db->newSelectQueryBuilder()
			->select( '1' )
			->from( self::$notificationTable )
			->leftJoin( self::$eventTable, null, 'notification_event=event_id' )
			->where( [
				'notification_user' => $this->user->getId(),
				'notification_read_timestamp' => null,
				'event_deleted' => 0,
				'event_type' => $eventTypesToLoad,
			] )
			->limit( $cap )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * IMPORTANT: should only call this function if the number of unread notification
	 * is reasonable, for example, unread notification count is less than the max
	 * display defined in oNotifUser::MAX_BADGE_COUNT
	 * @param string $type
	 * @return int[]
	 */
	public function getUnreadNotifications( $type ) {
		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( 'notification_event' )
			->from( self::$notificationTable )
			->join( self::$eventTable, null, 'notification_event = event_id' )
			->where( [
				'notification_user' => $this->user->getId(),
				'notification_read_timestamp' => null,
				'event_deleted' => 0,
				'event_type' => $type,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$eventIds = [];
		foreach ( $res as $row ) {
			$eventIds[$row->notification_event] = $row->notification_event;
		}

		return $eventIds;
	}

}
