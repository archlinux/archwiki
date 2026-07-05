<?php

namespace MediaWiki\Extension\ConfirmEdit\AbuseFilter;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Consequence;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Logger\LoggerFactory;

/**
 * Show a CAPTCHA to the user before they can proceed with an action.
 */
class CaptchaConsequence extends Consequence {
	/**
	 * The session key that stores the ID of the last Abuse Filter that
	 * triggered the captcha consequence.
	 *
	 * This is used in CaptchaScoreHooks to check which abuse filter triggered
	 * a captcha challenge after an edit, so that the filter ID can be properly
	 * logged as part of the error.
	 */
	public const FILTER_ID_SESSION_KEY = 'captchaConsequence-filterId';

	public function execute(): bool {
		$action = $this->parameters->getAction();
		$filterId = $this->parameters->getFilter()->getID();

		if ( !in_array( $action, CaptchaTriggers::CAPTCHA_TRIGGERS ) ) {
			LoggerFactory::getInstance( 'ConfirmEdit' )->error(
				'Filter {filter}: {action} is not defined in the list of triggers known to ConfirmEdit',
				[ 'action' => $action, 'filter' => $filterId ]
			);

			return true;
		}

		RequestContext::getMain()->getRequest()->getSession()->set(
			self::FILTER_ID_SESSION_KEY,
			$filterId
		);

		// This consequence was triggered, so we need to set a flag
		// on the SimpleCaptcha instance to force showing the CAPTCHA.
		$captcha = Hooks::getInstance( $action );
		$captcha->setAction( $action );
		$captcha->setForceShowCaptcha( true );

		return true;
	}
}
