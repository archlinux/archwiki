<?php

namespace MediaWiki\Extension\Notifications\Mapper;

use InvalidArgumentException;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\User\UserIdentity;

/**
 * Database mapper for Event model, which is an immutable class, there should
 * not be any update to it
 */
class EventMapper extends AbstractMapper {

	/**
	 * Insert an event record
	 *
	 * @param Event $event
	 * @return int
	 */
	public function insert( Event $event ) {
		$dbw = $this->dbFactory->getEchoDb( DB_PRIMARY );

		$row = $event->toDbArray();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'echo_event' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();

		$id = $dbw->insertId();

		$listeners = $this->getMethodListeners( __FUNCTION__ );
		foreach ( $listeners as $listener ) {
			$dbw->onTransactionCommitOrIdle( $listener, __METHOD__ );
		}

		return $id;
	}

	/**
	 * Create an Event by id
	 *
	 * @param int $id
	 * @param bool $fromPrimary
	 * @return Event|false False if it wouldn't load/unserialize
	 */
	public function fetchById( $id, $fromPrimary = false ) {
		$db = $fromPrimary ? $this->dbFactory->getEchoDb( DB_PRIMARY ) : $this->dbFactory->getEchoDb( DB_REPLICA );

		$row = $db->newSelectQueryBuilder()
			->select( Event::selectFields() )
			->from( 'echo_event' )
			->where( [ 'event_id' => $id ] )
			->caller( __METHOD__ )
			->fetchRow();

		// If the row was not found, fall back on the primary database if it makes sense to do so
		if ( !$row && !$fromPrimary && $this->dbFactory->canRetryPrimary() ) {
			return $this->fetchById( $id, true );
		} elseif ( !$row ) {
			throw new InvalidArgumentException( "No Event found with ID: $id" );
		}

		return Event::newFromRow( $row );
	}

	/**
	 * @param int[] $eventIds
	 * @param bool $deleted
	 */
	public function toggleDeleted( array $eventIds, $deleted ) {
		$dbw = $this->dbFactory->getEchoDb( DB_PRIMARY );

		$selectDeleted = $deleted ? 0 : 1;
		$setDeleted = $deleted ? 1 : 0;
		$dbw->newUpdateQueryBuilder()
			->update( 'echo_event' )
			->set( [
				'event_deleted' => $setDeleted,
			] )
			->where( [
				'event_deleted' => $selectDeleted,
				'event_id' => $eventIds,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Fetch events associated with a page
	 *
	 * @param int $pageId
	 * @return Event[] Events
	 */
	public function fetchByPage( $pageId ) {
		$events = [];
		$seenEventIds = [];
		$dbr = $this->dbFactory->getEchoDb( DB_REPLICA );

		// From echo_event
		$res = $dbr->newSelectQueryBuilder()
			->select( Event::selectFields() )
			->from( 'echo_event' )
			->where( [ 'event_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$event = Event::newFromRow( $row );
			$events[] = $event;
			$seenEventIds[] = $event->getId();
		}

		// From echo_target_page
		$conds = [ 'etp_page' => $pageId ];
		if ( $seenEventIds ) {
			// Some events have both a title and target page(s).
			// Skip the events that were already found in the echo_event table (the query above).
			$conds[] = $dbr->expr( 'event_id', '!=', $seenEventIds );
		}
		$res = $dbr->newSelectQueryBuilder()
			->select( Event::selectFields() )
			->distinct()
			->from( 'echo_event' )
			->join( 'echo_target_page', null, 'event_id=etp_event' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$events[] = Event::newFromRow( $row );
		}

		return $events;
	}

	/**
	 * Fetch event IDs associated with a page
	 *
	 * @param int $pageId
	 * @return int[] Event IDs
	 */
	public function fetchIdsByPage( $pageId ) {
		$events = $this->fetchByPage( $pageId );
		$eventIds = array_map(
			static function ( Event $event ) {
				return $event->getId();
			},
			$events
		);
		return $eventIds;
	}

	/**
	 * Fetch events unread by a user and associated with a page
	 *
	 * @param UserIdentity $user
	 * @param int $pageId
	 * @return Event[]
	 */
	public function fetchUnreadByUserAndPage( UserIdentity $user, $pageId ) {
		$dbr = $this->dbFactory->getEchoDb( DB_REPLICA );
		$fields = array_merge( Event::selectFields(), [ 'notification_timestamp' ] );

		$res = $dbr->newSelectQueryBuilder()
			->select( $fields )
			->from( 'echo_event' )
			->join( 'echo_notification', null, 'notification_event=event_id' )
			->join( 'echo_target_page', null, 'etp_event=event_id' )
			->where( [
				'event_deleted' => 0,
				'notification_user' => $user->getId(),
				'notification_read_timestamp' => null,
				'etp_page' => $pageId,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$data = [];
		foreach ( $res as $row ) {
			$data[] = Event::newFromRow( $row );
		}

		return $data;
	}

	/**
	 * Find out which of the given event IDs are orphaned, and delete them.
	 *
	 * An event is orphaned if it is not referred to by any rows in the echo_notification or
	 * echo_email_batch tables. If $ignoreUserId is set, rows for that user are not considered when
	 * determining orphanhood; if $ignoreUserTable is set, this only applies to that table.
	 * Use this when you've just recently deleted rows related to this user on the primary database, so that
	 * this function won't refuse to delete recently-orphaned events because it still sees the
	 * recently-deleted rows on the replica.
	 *
	 * @param array $eventIds Event IDs to check to see if they have become orphaned
	 * @param int|null $ignoreUserId Allow events to be deleted if the only referring rows
	 *  have this user ID
	 * @param string|null $ignoreUserTable Restrict $ignoreUserId to this table only
	 *  ('echo_notification' or 'echo_email_batch')
	 */
	public function deleteOrphanedEvents( array $eventIds, $ignoreUserId = null, $ignoreUserTable = null ) {
		$dbw = $this->dbFactory->getEchoDb( DB_PRIMARY );
		$dbr = $this->dbFactory->getEchoDb( DB_REPLICA );

		$notifJoinConds = [];
		$emailJoinConds = [];
		if ( $ignoreUserId !== null ) {
			if ( $ignoreUserTable === null || $ignoreUserTable === 'echo_notification' ) {
				$notifJoinConds[] = $dbr->expr( 'notification_user', '!=', $ignoreUserId );
			}
			if ( $ignoreUserTable === null || $ignoreUserTable === 'echo_email_batch' ) {
				$emailJoinConds[] = $dbr->expr( 'eeb_user_id', '!=', $ignoreUserId );
			}
		}
		$orphanedEventIds = $dbr->newSelectQueryBuilder()
			->select( 'event_id' )
			->from( 'echo_event' )
			->leftJoin( 'echo_notification', null, array_merge(
				[ 'notification_event=event_id' ],
				$notifJoinConds
			) )
			->leftJoin( 'echo_email_batch', null, array_merge(
				[ 'eeb_event_id=event_id' ],
				$emailJoinConds
			) )
			->where( [
				'event_id' => $eventIds,
				'notification_timestamp' => null,
				'eeb_user_id' => null
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( $orphanedEventIds ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'echo_event' )
				->where( [ 'event_id' => $orphanedEventIds ] )
				->caller( __METHOD__ )
				->execute();
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'echo_target_page' )
				->where( [ 'etp_event' => $orphanedEventIds ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

}
