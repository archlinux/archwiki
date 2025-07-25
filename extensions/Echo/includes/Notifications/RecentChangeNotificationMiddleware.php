<?php

namespace MediaWiki\Extension\Notifications\Notifications;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\ConfigNames;
use MediaWiki\Notification\Middleware\FilterMiddleware;
use MediaWiki\Notification\NotificationEnvelope;
use MediaWiki\Watchlist\RecentChangeNotification;

class RecentChangeNotificationMiddleware extends FilterMiddleware {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	private function shouldKeepUserTalkNotification() {
		// Echo talk page email notification
		// Send legacy talk page email notification if
		// 1. echo is disabled for them or
		// 2. echo talk page notification is disabled
		if ( !isset( $this->config->get( ConfigNames::Notifications )['edit-user-talk'] ) ) {
			// Keep Core talk page email notification
			return self::KEEP;
		}
		// Remove Core talk page email notification so Echo can inject it's own
		return self::REMOVE;
	}

	protected function filter( NotificationEnvelope $envelope ): bool {
		$notification = $envelope->getNotification();
		if ( !( $notification instanceof RecentChangeNotification ) ) {
			return self::KEEP;
		}
		if ( $notification->isUserTalkNotification() ) {
			return $this->shouldKeepUserTalkNotification();
		}
		if ( $notification->isWatchlistNotification() ) {
			// TODO - handle watchlist notifications
		}
		return self::KEEP;
	}

}
