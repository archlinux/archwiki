<?php

use MediaWiki\Extension\TitleBlacklist\TitleBlacklistAuthenticationRequest;
use MediaWiki\Tests\Auth\AuthenticationRequestTestCase;

/**
 * @covers \MediaWiki\Extension\TitleBlacklist\TitleBlacklistAuthenticationRequest
 */
class TitleBlacklistAuthenticationRequestTest extends AuthenticationRequestTestCase {
	protected function getInstance( array $args = [] ) {
		return new TitleBlacklistAuthenticationRequest();
	}

	public static function provideLoadFromSubmission() {
		return [
			'empty' => [ [], [], [ 'ignoreTitleBlacklist' => false ] ],
			'true' => [ [], [ 'ignoreTitleBlacklist' => '1' ], [ 'ignoreTitleBlacklist' => true ] ],
		];
	}
}
