<?php

use MediaWiki\Extension\Notifications\Iterator\CallbackIterator;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MediaWikiServices;

class EchoUserLocator {
	/**
	 * Return all users watching the event title.
	 *
	 * The echo job queue must be enabled to prevent timeouts submitting to
	 * heavily watched pages when this is used.
	 *
	 * @param Event $event
	 * @param int $batchSize
	 * @return User[]|Iterator<User>
	 */
	public static function locateUsersWatchingTitle( Event $event, $batchSize = 500 ) {
		$title = $event->getTitle();
		if ( !$title ) {
			return [];
		}

		$batchRowIt = new BatchRowIterator(
			wfGetDB( DB_REPLICA, 'watchlist' ),
			/* $table = */ 'watchlist',
			/* $primaryKeys = */ [ 'wl_user' ],
			$batchSize
		);
		$batchRowIt->addConditions( [
			'wl_namespace' => $title->getNamespace(),
			'wl_title' => $title->getDBkey(),
		] );
		$batchRowIt->setCaller( __METHOD__ );

		// flatten the result into a stream of rows
		$recursiveIt = new RecursiveIteratorIterator( $batchRowIt );

		// add callback to convert user id to user objects
		$echoCallbackIt = new CallbackIterator( $recursiveIt, static function ( $row ) {
			return User::newFromId( $row->wl_user );
		} );

		return $echoCallbackIt;
	}

	/**
	 * If the event occurred on the talk page of a registered
	 * user return that user.
	 *
	 * @param Event $event
	 * @return User[]
	 */
	public static function locateTalkPageOwner( Event $event ) {
		$title = $event->getTitle();
		if ( !$title || $title->getNamespace() !== NS_USER_TALK ) {
			return [];
		}

		$user = User::newFromName( $title->getDBkey() );
		if ( $user && $user->isRegistered() ) {
			return [ $user->getId() => $user ];
		}

		return [];
	}

	/**
	 * Return the event agent
	 *
	 * @param Event $event
	 * @return User[]
	 */
	public static function locateEventAgent( Event $event ) {
		$agent = $event->getAgent();
		if ( $agent && $agent->isRegistered() ) {
			return [ $agent->getId() => $agent ];
		}

		return [];
	}

	/**
	 * Return the user that created the first revision of the
	 * associated title.
	 *
	 * @param Event $event
	 * @return User[]
	 */
	public static function locateArticleCreator( Event $event ) {
		$title = $event->getTitle();

		if ( !$title || $title->getArticleID() <= 0 ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$revQuery = MediaWikiServices::getInstance()->getRevisionStore()->getQueryInfo();
		$res = $dbr->selectRow(
			$revQuery['tables'],
			[ 'rev_user' => $revQuery['fields']['rev_user'] ],
			[ 'rev_page' => $title->getArticleID() ],
			__METHOD__,
			[ 'LIMIT' => 1, 'ORDER BY' => 'rev_timestamp, rev_id' ],
			$revQuery['joins']
		);
		if ( !$res || !$res->rev_user ) {
			return [];
		}

		$user = User::newFromId( $res->rev_user );
		if ( $user ) {
			return [ $user->getId() => $user ];
		}

		return [];
	}

	/**
	 * Fetch user ids from the event extra data.  Requires additional
	 * parameter.  Example $wgEchoNotifications parameter:
	 *
	 *   'user-locator' => array( array( 'event-extra', 'mentions' ) ),
	 *
	 * The above will look in the 'mentions' parameter for a user id or
	 * array of user ids.  It will return all these users as notification
	 * targets.
	 *
	 * @param Event $event
	 * @param string[] $keys one or more keys to check for user ids
	 * @return User[]
	 */
	public static function locateFromEventExtra( Event $event, array $keys ) {
		$users = [];
		foreach ( $keys as $key ) {
			$userIds = $event->getExtraParam( $key );
			if ( !$userIds ) {
				continue;
			}
			if ( !is_array( $userIds ) ) {
				$userIds = [ $userIds ];
			}
			foreach ( $userIds as $userId ) {
				// we shouldn't receive User instances, but allow
				// it for backward compatability
				if ( $userId instanceof User ) {
					if ( !$userId->isRegistered() ) {
						continue;
					}
					$user = $userId;
				} else {
					$user = User::newFromId( $userId );
				}
				$users[$user->getId()] = $user;
			}
		}

		return $users;
	}
}
