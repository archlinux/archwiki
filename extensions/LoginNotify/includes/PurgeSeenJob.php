<?php

namespace LoginNotify;

use MediaWiki\JobQueue\Job;
use MediaWiki\Title\Title;

class PurgeSeenJob extends Job {
	public function __construct(
		Title $title,
		array $params,
		private readonly LoginNotify $loginNotify,
	) {
		parent::__construct( 'LoginNotifyPurgeSeen', $title, $params );
	}

	/** @inheritDoc */
	public function run() {
		$minId = $this->getParams()['minId'];
		$this->loginNotify->purgeSeen( $minId );
		return true;
	}
}
