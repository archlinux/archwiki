<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\User\UserIdentity;

interface CheckUserInsertLogEventRowHook {
	/**
	 * Use this hook to modify the IP, XFF or other values
	 * in the row to be inserted into cu_log_event.
	 *
	 * If changing the request IP or XFF stored in the database, you are
	 * required to modify $ip and $xff (instead of
	 * modifying $row) as CheckUser will calculate other
	 * values based on those parameters and not the values
	 * in $row.
	 *
	 * @since 1.40
	 *
	 * @param string &$ip The user's IP
	 * @param string|false &$xff The XFF for the request; false if no defined XFF
	 * @param array &$row The row to be inserted (before defaults are applied)
	 * @param UserIdentity $user The user who performed the action associated with this row
	 * @param int $id The ID of the associated log event
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @codeCoverageIgnore Cannot be annotated as covered.
	 */
	public function onCheckUserInsertLogEventRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user,
		int $id,
		?RecentChange $rc
	);
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( CheckUserInsertLogEventRowHook::class, 'MediaWiki\CheckUser\Hook\CheckUserInsertLogEventRow' );
