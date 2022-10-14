<?php

namespace MediaWiki\ChangeTags\Hook;

use User;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "ChangeTagsAllowedAdd" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ChangeTagsAllowedAddHook {
	/**
	 * This hook is called when checking if a user can add tags to a change.
	 *
	 * @since 1.35
	 *
	 * @param string[] &$allowedTags List of all the tags the user is allowed to add. Any tags the
	 *   user wants to add ($addTags) that are not in this array will cause it to fail.
	 *   You may add or remove tags to this array as required.
	 * @param string[] $addTags List of tags user intends to add
	 * @param User|null $user User who is adding the tags
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onChangeTagsAllowedAdd( &$allowedTags, $addTags, $user );
}
