<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

class UserMergeHandler {

	/**
	 * Tables that Extension:UserMerge needs to update
	 * @todo Use new hook system once UserMerge is updated
	 *
	 * @param array &$updateFields
	 */
	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [ 'abuse_filter', 'af_user', 'af_user_text' ];
		$updateFields[] = [ 'abuse_filter_log', 'afl_user', 'afl_user_text' ];
		$updateFields[] = [ 'abuse_filter_history', 'afh_user', 'afh_user_text' ];
	}

}
