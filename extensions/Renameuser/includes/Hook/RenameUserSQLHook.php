<?php

namespace MediaWiki\Extension\Renameuser\Hook;

use MediaWiki\Extension\Renameuser\RenameuserSQL;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "RenameUserSQL" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @since 1.36
 */
interface RenameUserSQLHook {

	/**
	 * Called in the constructer of RenameuserSQL (which performs the actual renaming of users).
	 *
	 * @param RenameuserSQL $renameUserSql
	 */
	public function onRenameUserSQL( RenameuserSQL $renameUserSql ): void;

}

class_alias( RenameUserSQLHook::class, 'RenameUserSQLHook' );
