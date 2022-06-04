<?php

use MediaWiki\Extension\AbuseFilter\Hooks\Handlers\SchemaChangesHandler;
use MediaWiki\User\UserGroupManager;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Hooks\Handlers\SchemaChangesHandler
 * @todo Make this a unit test once User::newSystemUser is moved to a service
 */
class SchemaChangesHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			SchemaChangesHandler::class,
			new SchemaChangesHandler(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( UserGroupManager::class )
			)
		);
	}

	public function provideAbuseFilterUser(): Generator {
		$noRowUpdater = $this->createMock( DatabaseUpdater::class );
		$noRowUpdater->method( 'updateRowExists' )->willReturn( false );
		$invalidML = $this->createMock( MessageLocalizer::class );
		$invalidML->method( 'msg' )->with( 'abusefilter-blocker' )->willReturn( $this->getMockMessage( '' ) );
		$handler = new SchemaChangesHandler( $invalidML, $this->createMock( UserGroupManager::class ) );
		yield 'invalid username' => [ $handler, $noRowUpdater, false ];

		$rowExistsUpdater = $this->createMock( DatabaseUpdater::class );
		$rowExistsUpdater->method( 'updateRowExists' )->willReturn( true );
		$validML = $this->createMock( MessageLocalizer::class );
		$validML->method( 'msg' )->with( 'abusefilter-blocker' )->willReturn( $this->getMockMessage( 'Foo' ) );
		$handler = new SchemaChangesHandler( $validML, $this->createMock( UserGroupManager::class ) );
		yield 'already created' => [ $handler, $rowExistsUpdater, false ];

		$ugm = $this->createMock( UserGroupManager::class );
		$ugm->expects( $this->once() )->method( 'addUserToGroup' );
		$okHandler = new SchemaChangesHandler( $validML, $ugm );
		yield 'success' => [ $okHandler, $noRowUpdater, true ];
	}

	/**
	 * @param SchemaChangesHandler $handler
	 * @param DatabaseUpdater $updater
	 * @param bool $expected
	 * @covers ::createAbuseFilterUser
	 * @dataProvider provideAbuseFilterUser
	 */
	public function testCreateAbuseFilterUser(
		SchemaChangesHandler $handler,
		DatabaseUpdater $updater,
		bool $expected
	) {
		$this->assertSame( $expected, $handler->createAbuseFilterUser( $updater ) );
	}
}
