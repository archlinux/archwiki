<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks;

use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\User\UserIdentity;

interface AbuseFilterGenerateAccountCreationVarsHook {
	/**
	 * Hook runner for the `AbuseFilterGenerateAccountCreationVars` hook
	 *
	 * Allows altering the variables generated when in the context of account creation.
	 *
	 * @param VariableHolder $vars
	 * @param UserIdentity $creator The user who created the account (may be an IP address)
	 * @param UserIdentity $createdUser The account being created or autocreated
	 * @param bool $autocreate Whether the account creation is an autocreation.
	 * @param ?RecentChange $rc If the variables should be generated for an RC entry,
	 *     this is the entry. Null if it's for the current action being filtered.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onAbuseFilterGenerateAccountCreationVars(
		VariableHolder $vars,
		UserIdentity $creator,
		UserIdentity $createdUser,
		bool $autocreate,
		?RecentChange $rc
	);
}
