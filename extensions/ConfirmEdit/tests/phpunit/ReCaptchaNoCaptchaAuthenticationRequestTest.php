<?php

use MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha\ReCaptchaNoCaptchaAuthenticationRequest;
use MediaWiki\Tests\Auth\AuthenticationRequestTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha\ReCaptchaNoCaptchaAuthenticationRequest
 */
class ReCaptchaNoCaptchaAuthenticationRequestTest extends AuthenticationRequestTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->mergeMwGlobalArrayValue(
			'wgAutoloadClasses',
			[
				'MediaWiki\\Extension\\ConfirmEdit\\ReCaptchaNoCaptcha\\ReCaptchaNoCaptchaAuthenticationRequest'
					=> __DIR__ . '/../../ReCaptchaNoCaptcha/includes/ReCaptchaNoCaptchaAuthenticationRequest.php'
			]
		);
	}

	protected function getInstance( array $args = [] ) {
		return new ReCaptchaNoCaptchaAuthenticationRequest();
	}

	public static function provideLoadFromSubmission() {
		return [
			'no proof' => [ [], [], false ],
			'normal' => [ [], [ 'captchaWord' => 'abc' ], [ 'captchaWord' => 'abc' ] ],
		];
	}
}
