<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklistAuthenticationRequest;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklistPreAuthenticationProvider;
use MediaWiki\Tests\Unit\Auth\AuthenticationProviderTestTrait;

/**
 * @group Database
 * @covers \MediaWiki\Extension\TitleBlacklist\TitleBlacklistPreAuthenticationProvider
 */
class TitleBlacklistPreAuthenticationProviderTest extends MediaWikiIntegrationTestCase {
	use AuthenticationProviderTestTrait;

	/**
	 * @dataProvider provideGetAuthenticationRequests
	 */
	public function testGetAuthenticationRequests( $action, $username, $expectedReqs ) {
		$provider = new TitleBlacklistPreAuthenticationProvider();
		$this->initProvider( $provider, null, null, $this->getServiceContainer()->getAuthManager() );
		$reqs = $provider->getAuthenticationRequests( $action, [ 'username' => $username ] );
		$this->assertEquals( $expectedReqs, $reqs );
	}

	public static function provideGetAuthenticationRequests() {
		return [
			[ AuthManager::ACTION_LOGIN, null, [] ],
			[ AuthManager::ACTION_CREATE, null, [] ],
			[ AuthManager::ACTION_CREATE, 'UTSysop', [ new TitleBlacklistAuthenticationRequest() ] ],
			[ AuthManager::ACTION_CHANGE, null, [] ],
			[ AuthManager::ACTION_REMOVE, null, [] ],
		];
	}
}
