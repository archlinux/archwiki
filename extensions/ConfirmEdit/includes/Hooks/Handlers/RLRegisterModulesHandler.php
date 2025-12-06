<?php

namespace MediaWiki\Extension\ConfirmEdit\Hooks\Handlers;

use MediaWiki\Extension\ConfirmEdit\Services\LoadedCaptchasProvider;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {

	public function __construct(
		private readonly LoadedCaptchasProvider $loadedCaptchasProvider,
	) {
	}

	/**
	 * Conditionally registers captcha-specific resource loader modules, such that they are only
	 * loaded if the captcha itself is loaded as determined by {@link LoadedCaptchasProvider}.
	 *
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		$dir = dirname( __DIR__, 3 ) . '/resources/';
		$modules = [];

		// Only load the captcha-specific resource loader modules if that captcha is loaded
		$loadedCaptchas = $this->loadedCaptchasProvider->getLoadedCaptchas();

		if ( in_array( 'HCaptcha', $loadedCaptchas, true ) ) {
			$modules['ext.confirmEdit.hCaptcha'] = [
				'localBasePath' => $dir,
				'remoteExtPath' => 'ConfirmEdit/resources',
				'packageFiles' => [
					'ext.confirmEdit.hCaptcha/init.js',
					'ext.confirmEdit.hCaptcha/secureEnclave.js',
					'ext.confirmEdit.hCaptcha/utils.js',
					'ext.confirmEdit.hCaptcha/ProgressIndicatorWidget.js',
					'ext.confirmEdit.hCaptcha/ErrorWidget.js',
					'ext.confirmEdit.hCaptcha/ve/initPlugins.js',
					'ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaSaveErrorHandler.js',
					'ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptchaOnLoadHandler.js',
					'ext.confirmEdit.hCaptcha/ve/ve.init.mw.HCaptcha.js',
					[
						'name' => 'ext.confirmEdit.hCaptcha/config.json',
						'config' => [
							'HCaptchaApiUrl',
							'HCaptchaSiteKey',
							'HCaptchaEnterprise',
							'HCaptchaSecureEnclave',
							'HCaptchaApiUrlIntegrityHash',
							'HCaptchaInvisibleMode',
						]
					],
				],
				'styles' => [
					'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha.less',
				],
				'messages' => [
					'hcaptcha-challenge-closed',
					'hcaptcha-challenge-expired',
					'hcaptcha-generic-error',
					'hcaptcha-loading-indicator-label',
					'hcaptcha-privacy-policy',
				],
				'dependencies' => [
					'web2017-polyfills',
					'codex-styles',
				],
			];
			$modules['ext.confirmEdit.hCaptcha.styles'] = [
				'localBasePath' => $dir,
				'remoteExtPath' => 'ConfirmEdit/resources',
				'styles' => [
					'ext.confirmEdit.hCaptcha.styles/ext.confirmEdit.hCaptcha.styles.less',
				],
			];
		}

		if ( count( $modules ) ) {
			$rl->register( $modules );
		}
	}

}
