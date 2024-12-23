<?php

namespace MediaWiki\Extension\Notifications;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\UserMerge\Hooks\AccountDeleteTablesHook;
use MediaWiki\Extension\UserMerge\Hooks\AccountFieldsHook;
use MediaWiki\Extension\UserMerge\Hooks\MergeAccountFromToHook;
use MediaWiki\User\User;

class UserMergeHooks implements
	AccountFieldsHook,
	MergeAccountFromToHook,
	AccountDeleteTablesHook
{

	/**
	 * For integration with the UserMerge extension.
	 *
	 * @param array &$updateFields
	 */
	public function onUserMergeAccountFields( array &$updateFields ) {
		// [ tableName, idField, textField ]
		$dbw = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$updateFields[] = [ 'echo_event', 'event_agent_id', 'db' => $dbw ];
		$updateFields[] = [ 'echo_notification', 'notification_user', 'db' => $dbw, 'options' => [ 'IGNORE' ] ];
		$updateFields[] = [ 'echo_email_batch', 'eeb_user_id', 'db' => $dbw, 'options' => [ 'IGNORE' ] ];
	}

	public function onMergeAccountFromTo( User &$oldUser, User &$newUser ) {
		$method = __METHOD__;
		DeferredUpdates::addCallableUpdate( static function () use ( $oldUser, $newUser, $method ) {
			if ( $newUser->isRegistered() ) {
				// Select notifications that are now sent to the same user
				$dbw = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
				$attributeManager = Services::getInstance()->getAttributeManager();
				$selfIds = $dbw->newSelectQueryBuilder()
					->select( 'event_id' )
					->from( 'echo_notification' )
					->join( 'echo_event', null, 'notification_event = event_id' )
					->where( [
						'notification_user' => $newUser->getId(),
						'notification_user = event_agent_id',
						$dbw->expr( 'event_type', '!=', $attributeManager->getNotifyAgentEvents() ),
					] )
					->caller( $method )
					->fetchFieldValues();

				// Select newer welcome notification(s)
				$welcomeIds = $dbw->newSelectQueryBuilder()
					->select( 'event_id' )
					->from( 'echo_event' )
					->join( 'echo_notification', null, 'notification_event = event_id' )
					->where( [
						'notification_user' => $newUser->getId(),
						'event_type' => 'welcome',
					] )
					->orderBy( 'notification_timestamp' )
					->offset( 1 )
					->caller( $method )
					->fetchFieldValues();

				// Select newer milestone notifications (per milestone level)
				$counts = [];
				$thankYouIds = [];
				$thankYouRows = $dbw->newSelectQueryBuilder()
					->select( Event::selectFields() )
					->from( 'echo_event' )
					->join( 'echo_notification', null, 'notification_event = event_id' )
					->where( [
						'notification_user' => $newUser->getId(),
						'event_type' => 'thank-you-edit',
					] )
					->orderBy( 'notification_timestamp' )
					->caller( $method )
					->fetchResultSet();
				foreach ( $thankYouRows as $row ) {
					$event = Event::newFromRow( $row );
					$editCount = $event ? $event->getExtraParam( 'editCount' ) : null;
					if ( $editCount ) {
						if ( isset( $counts[$editCount] ) ) {
							$thankYouIds[] = $row->event_id;
						} else {
							$counts[$editCount] = true;
						}
					}
				}

				// Delete notifications
				$ids = array_merge( $selfIds, $welcomeIds, $thankYouIds );
				if ( $ids !== [] ) {
					$dbw->newDeleteQueryBuilder()
						->deleteFrom( 'echo_notification' )
						->where( [
							'notification_user' => $newUser->getId(),
							'notification_event' => $ids
						] )
						->caller( $method )
						->execute();
				}
			}

			NotifUser::newFromUser( $oldUser )->resetNotificationCount();
			if ( $newUser->isRegistered() ) {
				NotifUser::newFromUser( $newUser )->resetNotificationCount();
			}
		} );
	}

	public function onUserMergeAccountDeleteTables( array &$tables ) {
		$dbw = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$tables['echo_notification'] = [ 'notification_user', 'db' => $dbw ];
		$tables['echo_email_batch'] = [ 'eeb_user_id', 'db' => $dbw ];
	}
}
