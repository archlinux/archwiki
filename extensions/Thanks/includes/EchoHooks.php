<?php

/**
 * Hooks for Thanks extension
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\Thanks;

use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\Hooks\EchoGetBundleRulesHook;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Registration\ExtensionRegistry;

class EchoHooks implements BeforeCreateEchoEventHook, EchoGetBundleRulesHook {

	/**
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		// Only allow Flow thanks notifications when enabled
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			unset( $notifications['flow-thank'] );
		}
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification.
	 *
	 * @param Event $event The event being notified.
	 * @param string &$bundleString Determines how the notification should be bundled.
	 */
	public function onEchoGetBundleRules( Event $event, string &$bundleString ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
				$bundleString = 'edit-thank';
				// Try to get either the revid or logid parameter.
				$revOrLogId = $event->getExtraParam( 'logid' );
				if ( $revOrLogId ) {
					// avoid collision with revision ids
					$revOrLogId = 'log' . $revOrLogId;
				} else {
					$revOrLogId = $event->getExtraParam( 'revid' );
				}
				if ( $revOrLogId ) {
					$bundleString .= $revOrLogId;
				}
				break;
			case 'flow-thank':
				$bundleString = 'flow-thank';
				$postId = $event->getExtraParam( 'post-id' );
				if ( $postId ) {
					$bundleString .= $postId;
				}
				break;
		}
	}

}
