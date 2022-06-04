<?php

use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\MediaWikiServices;
use Psr\Log\NullLogger;

/**
 * @group Test
 * @group AbuseFilter
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\FilterUser
 * @covers ::__construct()
 * @todo Make a unit test once DI is possible for User::newSystemUser
 */
class AbuseFilterFilterUserTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers ::getUser
	 * @covers ::getUserIdentity
	 */
	public function testGetUser() {
		$name = 'AbuseFilter blocker user';
		$ml = $this->createMock( MessageLocalizer::class );
		$ml->method( 'msg' )->willReturn( $this->getMockMessage( $name ) );
		$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
		$filterUser = new FilterUser( $ml, $ugm, new NullLogger() );

		$actual = $filterUser->getUserIdentity();
		$this->assertSame( $name, $actual->getName(), 'name' );
		$this->assertContains( 'sysop', $ugm->getUserGroups( $actual ), 'sysop' );
	}

	/**
	 * @covers ::getUser
	 * @covers ::getUserIdentity
	 */
	public function testGetUser_invalidName() {
		$name = 'Foobar filter user';
		$ml = $this->createMock( MessageLocalizer::class );
		$msg = $this->createMock( Message::class );
		$msg->method( 'inContentLanguage' )->willReturn( $this->getMockMessage( '' ) );
		$msg->method( 'inLanguage' )->willReturn( $this->getMockMessage( $name ) );
		$ml->method( 'msg' )->willReturn( $msg );
		$ugm = MediaWikiServices::getInstance()->getUserGroupManager();
		$logger = new TestLogger();
		$logger->setCollect( true );
		$filterUser = new FilterUser( $ml, $ugm, $logger );

		$actual = $filterUser->getUserIdentity();
		$this->assertSame( $name, $actual->getName(), 'name' );
		$found = false;
		foreach ( $logger->getBuffer() as $msg ) {
			if ( strpos( $msg[1], 'MediaWiki:abusefilter-blocker' ) !== false ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Invalid name not logged' );
	}
}
