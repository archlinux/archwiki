<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\RenameUser\Hook\RenameUserSQLHook;
use MediaWiki\RenameUser\RenameuserSQL;

class RenameUserSQLHandler implements RenameUserSQLHook {
	/** @inheritDoc */
	public function onRenameUserSQL( RenameuserSQL $renameUserSql ): void {
		$renameUserSql->tables['cu_log'] = [ 'cul_target_text', 'cul_target_id' ];
	}
}
