<?php

namespace MediaWiki\Extension\ConfirmEdit;

use MediaWiki\Config\Config;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomActionsHook;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;

class AbuseFilterHooks implements AbuseFilterCustomActionsHook {

	public function __construct(
		private readonly Config $config,
	) {
	}

	/** @inheritDoc */
	public function onAbuseFilterCustomActions( array &$actions ): void {
		$enabledActions = $this->config->get( 'ConfirmEditEnabledAbuseFilterCustomActions' );
		if ( in_array( 'showcaptcha', $enabledActions ) ) {
			// Messages used: abusefilter-edit-action-showcaptcha, abusefilter-edit-action-showcaptcha-help
			$actions['showcaptcha'] = static function ( Parameters $params ): CaptchaConsequence {
				return new CaptchaConsequence( $params );
			};
		}
	}

}
