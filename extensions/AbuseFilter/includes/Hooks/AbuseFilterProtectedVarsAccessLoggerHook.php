<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks;

use MediaWiki\User\UserIdentity;

interface AbuseFilterProtectedVarsAccessLoggerHook {
	/**
	 * Allows other extensions to hook into the logging mechanism triggered when a
	 * protected variable is viewed. This allows them access to the parameters and
	 * the ability to override the logger by aborting additional logging.
	 *
	 * This is useful if an extension wants to divert a log to their own logger
	 * (eg. CheckUser wants to centralize its IP access logs). It's recommended
	 * that in such cases the extension removes its variables from $params['variables']
	 * and leaves the rest of them to be stored in their own log.
	 *
	 * @param UserIdentity $performer
	 * @param string $target
	 * @param string $action
	 * @param bool $shouldDebounce
	 * @param int $timestamp
	 * @param array &$params
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onAbuseFilterLogProtectedVariableValueAccess(
		UserIdentity $performer,
		string $target,
		string $action,
		bool $shouldDebounce,
		int $timestamp,
		array &$params
	);
}
