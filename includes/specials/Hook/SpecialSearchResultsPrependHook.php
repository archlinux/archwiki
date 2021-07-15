<?php

namespace MediaWiki\Hook;

use OutputPage;
use SpecialSearch;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "SpecialSearchResultsPrepend" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface SpecialSearchResultsPrependHook {
	/**
	 * This hook is called immediately before returning HTML on the search results page.
	 *
	 * Useful for including an external search provider.
	 * To disable the output of MediaWiki search output, return false.
	 *
	 * @since 1.35
	 *
	 * @param SpecialSearch $specialSearch SpecialSearch object ($this)
	 * @param OutputPage $output $wgOut
	 * @param string $term Search term specified by the user
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSpecialSearchResultsPrepend( $specialSearch, $output, $term );
}
