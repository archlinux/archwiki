<?php

namespace MediaWiki\Extension\ConfirmEdit\Services;

use MediaWiki\Config\ServiceOptions;

/**
 * Provides a list of captchas that should be loaded for the request.
 *
 * If a captcha is loaded, then the ResourceLoader modules and other code should be
 * defined such that the captcha could be accessed by a {@link Hooks::getInstance}
 * call and then rendered to the user.
 */
class LoadedCaptchasProvider {
	public const CONSTRUCTOR_OPTIONS = [
		'ConfirmEditLoadedCaptchas',
		'CaptchaClass',
		'CaptchaTriggers',
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Returns the list of loaded captchas for a request. These captchas should be available for use,
	 * including having all relevant i18n and ResourceLoader modules loaded.
	 *
	 * @return string[] The list of captchas that are loaded, identified by their class name without the namespace
	 */
	public function getLoadedCaptchas(): array {
		// If we are running in a test environment, we want to mark all captchas as loaded so that we can test
		// their JavaScript modules in QUnit and interact with them in Selenium tests
		if ( $this->runningInTestContext() ) {
			return [ 'SimpleCaptcha', 'FancyCaptcha', 'QuestyCaptcha', 'ReCaptchaNoCaptcha', 'HCaptcha', 'Turnstile' ];
		}

		$loadedCaptchas = $this->options->get( 'ConfirmEditLoadedCaptchas' );
		$loadedCaptchas[] = $this->options->get( 'CaptchaClass' );

		$captchaTriggers = $this->options->get( 'CaptchaTriggers' );
		foreach ( $captchaTriggers as $trigger ) {
			if ( isset( $trigger['class'] ) ) {
				$loadedCaptchas[] = $trigger['class'];
			}
		}

		return array_unique( $loadedCaptchas );
	}

	/**
	 * Returns true if we are running in a test context. Used to mark all captchas as unconditionally loaded
	 * in test environments, so that all tests are run.
	 */
	protected function runningInTestContext(): bool {
		return defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' );
	}
}
