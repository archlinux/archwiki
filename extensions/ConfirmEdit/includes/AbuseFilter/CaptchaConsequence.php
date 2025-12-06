<?php

namespace MediaWiki\Extension\ConfirmEdit\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Consequence;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Logger\LoggerFactory;

/**
 * Show a CAPTCHA to the user before they can proceed with an action.
 */
class CaptchaConsequence extends Consequence {

	public function execute(): bool {
		$action = $this->parameters->getAction();
		if ( !in_array( $action, CaptchaTriggers::CAPTCHA_TRIGGERS ) ) {
			LoggerFactory::getInstance( 'ConfirmEdit' )->error(
				'Filter {filter}: {action} is not defined in the list of triggers known to ConfirmEdit',
				[ 'action' => $action, 'filter' => $this->parameters->getFilter()->getID() ]
			);
			return true;
		}
		// This consequence was triggered, so we need to set a flag
		// on the SimpleCaptcha instance to force showing the CAPTCHA.
		Hooks::getInstance( $action )->setForceShowCaptcha( true );
		return true;
	}
}
