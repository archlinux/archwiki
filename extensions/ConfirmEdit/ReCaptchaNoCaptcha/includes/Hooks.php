<?php

namespace MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha;

class Hooks {
	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @return bool Always true
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgReCaptchaSiteKey;
		global $wgCaptchaClass;

		if ( $wgCaptchaClass === ReCaptchaNoCaptcha::class ) {
			$vars['wgConfirmEditConfig'] = [
				'reCaptchaSiteKey' => $wgReCaptchaSiteKey,
				'reCaptchaScriptURL' => 'https://www.recaptcha.net/recaptcha/api.js'
			];
		}

		return true;
	}
}
