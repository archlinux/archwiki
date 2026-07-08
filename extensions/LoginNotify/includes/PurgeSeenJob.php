<?php

namespace LoginNotify;

use MediaWiki\JobQueue\Job;

class PurgeSeenJob extends Job {
	public function __construct(
		array $params,
		private readonly LoginNotify $loginNotify,
	) {
		parent::__construct( 'LoginNotifyPurgeSeen', $params );
	}

	/** @inheritDoc */
	public function run() {
		$minId = $this->getParams()['minId'];
		$this->loginNotify->purgeSeen( $minId );
		return true;
	}
}
