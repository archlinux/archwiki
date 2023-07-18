<?php

namespace MediaWiki\Page\Hook;

use MediaWiki\Category\Category;
use WikiPage;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CategoryAfterPageAdded" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CategoryAfterPageAddedHook {
	/**
	 * This hook is called after a page is added to a category.
	 *
	 * @since 1.35
	 *
	 * @param Category $category Category that page was added to
	 * @param WikiPage $wikiPage WikiPage that was added
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onCategoryAfterPageAdded( $category, $wikiPage );
}
