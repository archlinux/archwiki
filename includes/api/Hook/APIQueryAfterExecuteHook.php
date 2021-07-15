<?php

namespace MediaWiki\Api\Hook;

use ApiBase;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "APIQueryAfterExecute" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface APIQueryAfterExecuteHook {
	/**
	 * This hook is called after calling the execute() method of an
	 * action=query submodule. Use this hook to extend core API modules.
	 *
	 * @since 1.35
	 *
	 * @param ApiBase $module
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onAPIQueryAfterExecute( $module );
}
