<?php
/**
 * DiscussionTools echo hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use EchoEvent;
use MediaWiki\Extension\DiscussionTools\Notifications\EventDispatcher;
use MediaWiki\Revision\RevisionRecord;

class EchoHooks {
	/**
	 * Add notification events to Echo
	 *
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$notificationCategories['dt-subscription'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-dt-subscription',
		];
		$notifications['dt-subscribed-new-comment'] = [
			'category' => 'dt-subscription',
			'group' => 'interactive',
			'section' => 'message',
			'user-locators' => [
				'MediaWiki\\Extension\\DiscussionTools\\Notifications\\EventDispatcher::locateSubscribedUsers'
			],
			// Exclude mentioned users and talk page owner from our notification, to avoid
			// duplicate notifications for a single comment
			'user-filters' => [
				[
					"EchoUserLocator::locateFromEventExtra",
					[ "mentioned-users" ]
				],
				"EchoUserLocator::locateTalkPageOwner"
			],
			'presentation-model' =>
				'MediaWiki\\Extension\\DiscussionTools\\Notifications\\SubscribedNewCommentPresentationModel',
			'bundle' => [
				'web' => true,
				'email' => true,
				'expandable' => true,
			],
		];

		$notificationCategories['dt-subscription-archiving'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-dt-subscription-archiving',
		];
		$notifications['dt-removed-topic'] = [
			'category' => 'dt-subscription-archiving',
			'group' => 'interactive',
			'section' => 'message',
			'user-locators' => [
				'MediaWiki\\Extension\\DiscussionTools\\Notifications\\EventDispatcher::locateSubscribedUsers'
			],
			'presentation-model' =>
				'MediaWiki\\Extension\\DiscussionTools\\Notifications\\RemovedTopicPresentationModel',
			'bundle' => [
				'web' => true,
				'email' => true,
				'expandable' => true,
			],
		];

		// Override default handlers
		$notifications['edit-user-talk']['presentation-model'] =
			'MediaWiki\\Extension\\DiscussionTools\\Notifications\\EnhancedEchoEditUserTalkPresentationModel';
		$notifications['mention']['presentation-model'] =
			'MediaWiki\\Extension\\DiscussionTools\\Notifications\\EnhancedEchoMentionPresentationModel';
	}

	/**
	 * @param EchoEvent $event
	 * @param string &$bundleString
	 * @return bool
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, string &$bundleString ): bool {
		switch ( $event->getType() ) {
			case 'dt-subscribed-new-comment':
				$bundleString = $event->getType() . '-' . $event->getExtraParam( 'subscribed-comment-name' );
				break;
			case 'dt-removed-topic':
				$bundleString = $event->getType() . '-' . $event->getTitle()->getNamespace()
					. '-' . $event->getTitle()->getDBkey();
				break;
		}
		return true;
	}

	/**
	 * @param array &$events
	 * @param RevisionRecord $revision
	 * @param bool $isRevert
	 */
	public static function onEchoGetEventsForRevision( array &$events, RevisionRecord $revision, bool $isRevert ) {
		if ( $isRevert ) {
			return;
		}
		EventDispatcher::generateEventsForRevision( $events, $revision );
	}
}
