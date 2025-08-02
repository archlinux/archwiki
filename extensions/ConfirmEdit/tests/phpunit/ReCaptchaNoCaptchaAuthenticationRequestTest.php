<?php

use MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha\ReCaptchaNoCaptchaAuthenticationRequest;
use MediaWiki\Tests\Auth\AuthenticationRequestTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha\ReCaptchaNoCaptchaAuthenticationRequest
 */
class ReCaptchaNoCaptchaAuthenticationRequestTest extends AuthenticationRequestTestCase {

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
