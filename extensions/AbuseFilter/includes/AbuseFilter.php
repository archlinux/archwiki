<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use RequestContext;
use Status;
use Title;
use User;

/**
 * This class contains most of the business logic of AbuseFilter. It consists of
 * static functions for generic use (mostly utility functions).
 */
class AbuseFilter {

	/**
	 * @deprecated
	 * @todo Phase out
	 */
	public const HISTORY_MAPPINGS = [
		'af_pattern' => 'afh_pattern',
		'af_user' => 'afh_user',
		'af_user_text' => 'afh_user_text',
		'af_timestamp' => 'afh_timestamp',
		'af_comments' => 'afh_comments',
		'af_public_comments' => 'afh_public_comments',
		'af_deleted' => 'afh_deleted',
		'af_id' => 'afh_filter',
		'af_group' => 'afh_group',
	];

	/**
	 * Returns an associative array of filters which were tripped
	 *
	 * @param VariableHolder $vars
	 * @param Title $title
	 * @return bool[] Map of (filter ID => bool)
	 * @deprecated Since 1.34 This was meant to be internal!
	 * @codeCoverageIgnore Deprecated method
	 */
	public static function checkAllFilters(
		VariableHolder $vars,
		Title $title
	) {
		$user = RequestContext::getMain()->getUser();
		$runnerFactory = AbuseFilterServices::getFilterRunnerFactory();
		$runner = $runnerFactory->newRunner( $user, $title, $vars, 'default' );
		return $runner->checkAllFilters();
	}

	/**
	 * @param VariableHolder $vars
	 * @param Title $title
	 * @param string $group The filter's group (as defined in $wgAbuseFilterValidGroups)
	 * @param User $user The user performing the action
	 * @return Status
	 * @deprecated Since 1.34 Get a FilterRunner instance and call run() on that, if you really need to.
	 *   Or consider resolving the problem at its root, because you shouldn't need to call this manually.
	 * @codeCoverageIgnore Deprecated method
	 */
	public static function filterAction(
		VariableHolder $vars, Title $title, $group, User $user
	) {
		$runnerFactory = AbuseFilterServices::getFilterRunnerFactory();
		$runner = $runnerFactory->newRunner( $user, $title, $vars, $group );
		return $runner->run();
	}
}
