<?php

namespace MediaWiki\Extension\Renameuser\Hook;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "RenameUserPreRename" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface RenameUserPreRenameHook {

	/**
	 * Called before a user is renamed.
	 *
	 * @param int $uid The user ID
	 * @param string $old The old username
	 * @param string $new The new username
	 */
	public function onRenameUserPreRename( int $uid, string $old, string $new ): void;

}

class_alias( RenameUserPreRenameHook::class, 'RenameUserPreRenameHook' );
