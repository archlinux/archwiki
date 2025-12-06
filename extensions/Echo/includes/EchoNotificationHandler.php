<?php

namespace MediaWiki\Extension\Notifications;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Notification\AgentAware;
use MediaWiki\Notification\Notification;
use MediaWiki\Notification\NotificationHandler;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Notification\TitleAware;
use MediaWiki\Notification\Types\SimpleNotification;
use Wikimedia\Message\MessageValue;

class EchoNotificationHandler implements NotificationHandler {

	public function notify( Notification $notification, RecipientSet $recipients ): void {
		global $wgEchoNotifications;

		// If the type matches one of our event types, handle this event like an Echo event.
		if ( isset( $wgEchoNotifications[$notification->getType()] ) ) {
			$info = [];
			$info['type'] = $notification->getType();
			if ( $notification instanceof AgentAware ) {
				$info['agent'] = $notification->getAgent();
			}
			if ( $notification instanceof TitleAware ) {
				$info['title'] = $notification->getTitle();
			}

			// Pass all other custom props from core Notification in the extra array of Event
			$info['extra'] = $notification->getProperties();

			// Handle generic events not registered with Echo (T385839)
			if ( $notification instanceof SimpleNotification ) {
				$info['extra']['message'] = MessageValue::newFromSpecifier( $notification->getMessage() );
			}
			Event::create( $info, $recipients );
		}
	}

}
