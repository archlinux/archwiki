<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Extension\Renameuser\Hook\RenameUserSQLHook;
use MediaWiki\Extension\Renameuser\RenameuserSQL;

class UserRenameHandler implements RenameUserSQLHook {

	/**
	 * @inheritDoc
	 */
	public function onRenameUserSQL( RenameuserSQL $renameUserSql ): void {
		$renameUserSql->tablesJob['abuse_filter'] = [
			RenameuserSQL::NAME_COL => 'af_user_text',
			RenameuserSQL::UID_COL => 'af_user',
			RenameuserSQL::TIME_COL => 'af_timestamp',
			'uniqueKey' => 'af_id'
		];
		$renameUserSql->tablesJob['abuse_filter_history'] = [
			RenameuserSQL::NAME_COL => 'afh_user_text',
			RenameuserSQL::UID_COL => 'afh_user',
			RenameuserSQL::TIME_COL => 'afh_timestamp',
			'uniqueKey' => 'afh_id'
		];
	}

}
