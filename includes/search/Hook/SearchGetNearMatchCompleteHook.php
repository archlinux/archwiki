<?php

namespace MediaWiki\Search\Hook;

use Title;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "SearchGetNearMatchComplete" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface SearchGetNearMatchCompleteHook {
	/**
	 * Use this hook to modify exact-title-matches in "go" searches.
	 *
	 * @since 1.35
	 *
	 * @param string $term Search term
	 * @param Title|null &$title Current Title object that is being returned (null if none found)
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSearchGetNearMatchComplete( $term, &$title );
}
