<?php

namespace MediaWiki\Diff\Hook;

use DifferenceEngine;
use RecentChange;
use User;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "DifferenceEngineMarkPatrolledRCID" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface DifferenceEngineMarkPatrolledRCIDHook {
	/**
	 * Use this hook to possibly change the rcid parameter. For example the rcid
	 * might be set to zero due to the user being the same as the performer of
	 * the change but an extension might still want to show it under certain conditions.
	 *
	 * @since 1.35
	 *
	 * @param int &$rcid rc_id of the change or 0
	 * @param DifferenceEngine $differenceEngine
	 * @param RecentChange|null $change
	 * @param User $user Current user
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onDifferenceEngineMarkPatrolledRCID( &$rcid, $differenceEngine,
		$change, $user
	);
}
