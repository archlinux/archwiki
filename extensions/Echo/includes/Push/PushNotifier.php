<?php

namespace MediaWiki\Extension\Notifications\Push;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

class PushNotifier {

	/**
	 * Submits a notification derived from an Echo event to each push notifications service
	 * subscription found for a user, via a configured service handler implementation
	 * @param UserIdentity $user
	 * @param Event $event
	 */
	public static function notifyWithPush( UserIdentity $user, Event $event ): void {
		$attributeManager = Services::getInstance()->getAttributeManager();
		$userEnabledEvents = $attributeManager->getUserEnabledEvents( $user, 'push' );
		if ( in_array( $event->getType(), $userEnabledEvents ) ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->push( self::createJob( $user, $event ) );
		}
	}

	/**
	 * @param UserIdentity $user
	 * @param Event|null $event
	 * @return NotificationRequestJob
	 */
	private static function createJob( UserIdentity $user, ?Event $event = null ): NotificationRequestJob {
		$centralId = Utils::getPushUserId( $user );
		$params = [ 'centralId' => $centralId ];
		// below params are only needed for debug logging (T255068)
		if ( $event !== null ) {
			$params['eventId'] = $event->getId();
			$params['eventType'] = $event->getType();
			if ( $event->getAgent() !== null ) {
				$params['agent'] = $event->getAgent()->getId();
			}
		}
		return new NotificationRequestJob( 'EchoPushNotificationRequest', $params );
	}

}

class_alias( PushNotifier::class, 'EchoPush\\PushNotifier' );
