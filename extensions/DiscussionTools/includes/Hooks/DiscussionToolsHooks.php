<?php
/**
 * DiscussionTools hooks for listening to our own hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use IContextSource;
use MediaWiki\Extension\DiscussionTools\OverflowMenuItem;

class DiscussionToolsHooks implements
	DiscussionToolsAddOverflowMenuItemsHook
{

	/**
	 * @param OverflowMenuItem[] &$overflowMenuItems
	 * @param string[] &$resourceLoaderModules
	 * @param bool $isSectionEditable
	 * @param array $threadItemData
	 * @param IContextSource $contextSource
	 * @return bool|void
	 */
	public function onDiscussionToolsAddOverflowMenuItems(
		array &$overflowMenuItems,
		array &$resourceLoaderModules,
		bool $isSectionEditable,
		array $threadItemData,
		IContextSource $contextSource
	) {
		if ( ( $threadItemData['type'] ?? null ) === 'heading' && $isSectionEditable ) {
			$overflowMenuItems[] = new OverflowMenuItem(
				'edit',
				'edit',
				$contextSource->msg( 'skin-view-edit' ),
				2
			);
		}
	}
}
