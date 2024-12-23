<?php

namespace MediaWiki\Extension\ConfirmEdit\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Consequence;
use MediaWiki\Extension\ConfirmEdit\Hooks;

/**
 * Show a CAPTCHA to the user before they can proceed with an action.
 */
class CaptchaConsequence extends Consequence {

	public function execute(): bool {
		// This consequence was triggered, so we need to set a flag
		// on the SimpleCaptcha instance to force showing the CAPTCHA.
		Hooks::getInstance()->setForceShowCaptcha( true );
		return true;
	}
}
