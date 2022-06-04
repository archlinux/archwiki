<?php

namespace MediaWiki\Extension\Renameuser\Hook;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "RenameUserAbort" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface RenameUserAbortHook {

	/**
	 * Allows the renaming to be aborted.
	 *
	 * @param int $uid The user ID
	 * @param string $old The old username
	 * @param string $new The new username
	 *
	 * @return bool|void
	 */
	public function onRenameUserAbort( int $uid, string $old, string $new );

}

class_alias( RenameUserAbortHook::class, 'RenameUserAbortHook' );
