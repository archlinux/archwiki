<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesAuthenticationRequest;
use MediaWiki\Tests\Auth\AuthenticationRequestTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesAuthenticationRequest
 */
class RecoveryCodesAuthenticationRequestTest extends AuthenticationRequestTestCase {

	protected function getInstance( array $args = [] ) {
		return new RecoveryCodesAuthenticationRequest();
	}

	public static function provideLoadFromSubmission() {
		return [
			[ [], [], false ],
			[ [], [ 'RecoveryCode' => '123456' ], [ 'RecoveryCode' => '123456' ] ],
		];
	}
}
