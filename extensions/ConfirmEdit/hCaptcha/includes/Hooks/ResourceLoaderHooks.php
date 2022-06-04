<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha\Hooks;

use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

class ResourceLoaderHooks implements ResourceLoaderGetConfigVarsHook {
	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @param string $skin
	 * @param Config $config
	 * @return void
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$hCaptchaConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'hcaptcha' );
		if ( $hCaptchaConfig->get( 'CaptchaClass' ) === 'MediaWiki\\Extensions\\ConfirmEdit\\hCaptcha\\HCaptcha' ) {
			$vars['wgConfirmEditConfig'] = [
				'hCaptchaSiteKey' => $hCaptchaConfig->get( 'HCaptchaSiteKey' ),
				'hCaptchaScriptURL' => 'https://hcaptcha.com/1/api.js',
			];
		}
	}
}
