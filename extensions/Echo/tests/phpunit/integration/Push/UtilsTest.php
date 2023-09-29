<?php

use MediaWiki\Extension\Notifications\Push\Utils;

/** @covers \MediaWiki\Extension\Notifications\Push\Utils */
class UtilsTest extends MediaWikiIntegrationTestCase {

	public function testGetLoggedInPushId(): void {
		$user = $this->getTestUser()->getUser();
		$this->assertGreaterThan( 0, Utils::getPushUserId( $user ) );
	}

	public function testGetLoggedOutPushId(): void {
		$user = $this->getTestUser()->getUser();
		$user->doLogout();
		$this->assertSame( 0, Utils::getPushUserId( $user ) );
	}

}
