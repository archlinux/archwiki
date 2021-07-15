<?php

namespace MediaWiki\Hook;

use OutputPage;
use Skin;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "BeforePageDisplay" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface BeforePageDisplayHook {
	/**
	 * This hook is called prior to outputting a page.
	 *
	 * @since 1.35
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void This hook must not abort, it must return no value
	 */
	public function onBeforePageDisplay( $out, $skin ) : void;
}
