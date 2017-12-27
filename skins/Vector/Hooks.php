<?php
/**
 * Hooks.php
 */

/**
 * Hook handlers for Vector skin.
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 */

class VectorHooks {
	/**
	 * BeforePageDisplayMobile hook handler
	 *
	 * Make Vector responsive when operating in mobile mode (useformat=mobile)
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:MobileFrontend/BeforePageDisplayMobile
	 * @param OutputPage $out
	 * @param SkinTemplate $sk
	 */
	public static function onBeforePageDisplayMobile( OutputPage $out, $sk ) {
		// This makes Vector behave in responsive mode when MobileFrontend is installed
		if ( $sk instanceof SkinVector ) {
			$sk->enableResponsiveMode();
		}
	}
}
