<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

interface IBlockedDomainFilter {

	public const SERVICE_NAME = 'AbuseFilterBlockedDomainFilter';

	/**
	 * Check for any disallowed domains
	 *
	 * This function logs any hits under Special:Log.
	 *
	 * @param VariableHolder $vars variables by the action
	 * @param User $user User that tried to add the domain, used for logging
	 * @param Title $title Title of the page that was attempted on, used for logging
	 * @return Status Error status if it's a match, good status if not
	 */
	public function filter( VariableHolder $vars, User $user, Title $title ): Status;
}
