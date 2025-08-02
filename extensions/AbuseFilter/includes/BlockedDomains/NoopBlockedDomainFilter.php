<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class NoopBlockedDomainFilter implements IBlockedDomainFilter {

	/** @inheritDoc */
	public function filter( VariableHolder $vars, User $user, Title $title ): Status {
		return Status::newGood();
	}
}
