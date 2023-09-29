<?php
/**
 * DiscussionTools mobile hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

class MobileHooks {
	/**
	 * Decide whether mobile frontend should be allowed to activate
	 *
	 * @param \Title $title
	 * @param \OutputPage $output
	 * @return bool|void This hook can return false to abort, causing the talk overlay to not be shown
	 */
	public static function onMinervaNeueTalkPageOverlay( $title, $output ) {
		if ( HookUtils::isFeatureEnabledForOutput( $output ) ) {
			return false;
		}
		return true;
	}
}
