<?php

namespace MediaWiki\Extension\Notifications\Notifications;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\ConfigNames;
use MediaWiki\Notification\Middleware\FilterMiddleware;
use MediaWiki\Notification\Middleware\FilterMiddlewareAction;
use MediaWiki\Notification\NotificationEnvelope;
use MediaWiki\RecentChanges\RecentChangeNotification;
use MediaWiki\User\UserFactory;

class RecentChangeNotificationMiddleware extends FilterMiddleware {

	public function __construct(
		private readonly Config $config,
		private readonly UserFactory $userFactory,
		private readonly AttributeManager $attributeManager,
	) {
	}

	private function shouldKeepUserTalkNotification(): bool {
		// Echo talk page email notification
		// Send legacy talk page email notification if
		// 1. echo is disabled for them or
		// 2. echo talk page notification is disabled
		if ( !isset( $this->config->get( ConfigNames::Notifications )['edit-user-talk'] ) ) {
			// Keep Core talk page email notification
			return true;
		}
		// Remove Core talk page email notification so Echo can inject its own
		return false;
	}

	/**
	 * This may change $envelope's recipients
	 * @param NotificationEnvelope<RecentChangeNotification> $envelope
	 */
	private function shouldKeepWatchlistNotification( NotificationEnvelope $envelope ): bool {
		if ( $this->config->get( ConfigNames::WatchlistNotifications ) &&
			isset( $this->config->get( ConfigNames::Notifications )["watchlist-change"] )
		) {
			// Let echo handle watchlist notifications entirely
			return false;
		}

		// Drop watchlist notifications that duplicate non-watchlist Echo notifications
		$title = $envelope->getNotification()->getTitle();
		$recipients = $envelope->getRecipientSet();
		foreach ( $recipients as $recipient ) {
			$user = $this->userFactory->newFromUserIdentity( $recipient );
			$eventName = false;
			// The edit-user-talk and edit-user-page events effectively duplicate watchlist notifications.
			// If we are sending Echo notification emails, suppress the watchlist notifications.
			if ( $title->isSamePageAs( $user->getTalkPage() ) ) {
				$eventName = 'edit-user-talk';
			} elseif ( $title->isSamePageAs( $user->getUserPage() ) ) {
				$eventName = 'edit-user-page';
			}

			if (
				$eventName !== false &&
				in_array( $eventName, $this->attributeManager->getUserEnabledEvents( $recipient, 'email' ) )
			) {
				// This user will receive another notification, so don't send this one
				$recipients->removeRecipient( $recipient );
			}
		}

		if ( $recipients->count() === 0 ) {
			// No recipients left, drop the whole notification
			return false;
		}
		return true;
	}

	protected function filter( NotificationEnvelope $envelope ): FilterMiddlewareAction {
		$notification = $envelope->getNotification();
		if ( $notification instanceof RecentChangeNotification ) {
			if ( $notification->isUserTalkNotification() ) {
				return $this->shouldKeepUserTalkNotification() ?
					FilterMiddlewareAction::KEEP : FilterMiddlewareAction::REMOVE;
			}
			if ( $notification->isWatchlistNotification() ) {
				// This may change $envelope's recipients
				return $this->shouldKeepWatchlistNotification( $envelope ) ?
					FilterMiddlewareAction::KEEP : FilterMiddlewareAction::REMOVE;
			}
		}

		return FilterMiddlewareAction::KEEP;
	}

}
