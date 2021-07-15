<?php

namespace MediaWiki\Hook;

use Title;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "GetCanonicalURL" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface GetCanonicalURLHook {
	/**
	 * Use this hook to modify fully-qualified URLs used for IRC and email notifications.
	 *
	 * @since 1.35
	 *
	 * @param Title $title Title object of page
	 * @param string &$url String value as output (out parameter, can modify)
	 * @param string $query Query options as string passed to Title::getCanonicalURL()
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetCanonicalURL( $title, &$url, $query );
}
