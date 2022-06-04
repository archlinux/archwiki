<?php

namespace MediaWiki\Extension\Renameuser\Hook;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "RenameUserComplete" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface RenameUserCompleteHook {

	/**
	 * Called after a user was renamed.
	 *
	 * @param int $uid The user ID
	 * @param string $old The old username
	 * @param string $new The new username
	 */
	public function onRenameUserComplete( int $uid, string $old, string $new ): void;

}

class_alias( RenameUserCompleteHook::class, 'RenameUserCompleteHook' );
