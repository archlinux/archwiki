<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Hook\SpecialLogResolveLogTypeHook;

class SpecialLogResolveIPRevealLogTypeHandler implements SpecialLogResolveLogTypeHook {
	/** @inheritDoc */
	public function onSpecialLogResolveLogType(
		array $params,
		string &$type
	): void {
		// 'ipreveal' is an alias for the checkuser-temporary-account log type used
		// to make it easier to type a URL. Replace with the canonical name to find
		// the logs the user is looking for (T381875)
		if ( $type === 'ipreveal' ) {
			$type = TemporaryAccountLogger::LOG_TYPE;
		}
	}
}
