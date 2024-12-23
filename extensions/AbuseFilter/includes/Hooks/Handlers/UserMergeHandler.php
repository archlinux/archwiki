<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Extension\UserMerge\Hooks\AccountFieldsHook;

class UserMergeHandler implements AccountFieldsHook {

	/**
	 * Tables that Extension:UserMerge needs to update
	 *
	 * @param array[] &$updateFields
	 */
	public function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [
			'abuse_filter',
			'batchKey' => 'af_id',
			'actorId' => 'af_actor',
			'actorStage' => SCHEMA_COMPAT_NEW,
		];
		$updateFields[] = [
			'abuse_filter_log',
			'afl_user',
			'afl_user_text',
			'batchKey' => 'afl_id',
		];
		$updateFields[] = [
			'abuse_filter_history',
			'batchKey' => 'afh_id',
			'actorId' => 'afh_actor',
			'actorStage' => SCHEMA_COMPAT_NEW,
		];
	}

}
