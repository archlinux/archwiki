<?php

namespace MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha;

use MediaWiki\Config\Config;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

class Hooks implements ResourceLoaderGetConfigVarsHook {
	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		if ( $config->get( 'CaptchaClass' ) === ReCaptchaNoCaptcha::class ) {
			$vars['wgConfirmEditConfig'] = [
				'reCaptchaSiteKey' => $config->get( 'ReCaptchaSiteKey' ),
				'reCaptchaScriptURL' => 'https://www.recaptcha.net/recaptcha/api.js'
			];
		}
	}
}
