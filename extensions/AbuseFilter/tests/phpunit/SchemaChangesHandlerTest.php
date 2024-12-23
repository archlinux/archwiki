<?php

use MediaWiki\Extension\AbuseFilter\Hooks\Handlers\SchemaChangesHandler;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Hooks\Handlers\SchemaChangesHandler
 * @todo Make this a unit test once User::newSystemUser is moved to a service
 */
class SchemaChangesHandlerTest extends MediaWikiIntegrationTestCase {

	public function testConstruct() {
		$this->assertInstanceOf(
			SchemaChangesHandler::class,
			new SchemaChangesHandler(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( UserGroupManager::class ),
				$this->createMock( UserFactory::class )
			)
		);
	}

	public function testCreateAbuseFilterUser_invalidUserName() {
		$noRowUpdater = $this->createMock( DatabaseUpdater::class );
		$noRowUpdater->method( 'updateRowExists' )->willReturn( false );
		$invalidML = $this->createMock( MessageLocalizer::class );
		$invalidML->method( 'msg' )->with( 'abusefilter-blocker' )->willReturn( $this->getMockMessage( '' ) );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( null );
		$handler = new SchemaChangesHandler(
			$invalidML,
			$this->createNoOpMock( UserGroupManager::class ),
			$userFactory
		);
		$this->assertFalse( $handler->createAbuseFilterUser( $noRowUpdater ) );
	}

	public function testCreateAbuseFilterUser_alreadyCreated() {
		$rowExistsUpdater = $this->createMock( DatabaseUpdater::class );
		$rowExistsUpdater->method( 'updateRowExists' )->willReturn( true );
		$validML = $this->createMock( MessageLocalizer::class );
		$validML->method( 'msg' )->with( 'abusefilter-blocker' )->willReturn( $this->getMockMessage( 'Foo' ) );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( $this->createMock( User::class ) );
		$handler = new SchemaChangesHandler(
			$validML,
			$this->createNoOpMock( UserGroupManager::class ),
			$userFactory
		);
		$this->assertFalse( $handler->createAbuseFilterUser( $rowExistsUpdater ) );
	}

	public function testCreateAbuseFilterUser_success() {
		$noRowUpdater = $this->createMock( DatabaseUpdater::class );
		$noRowUpdater->method( 'updateRowExists' )->willReturn( false );
		$validML = $this->createMock( MessageLocalizer::class );
		$validML->method( 'msg' )->with( 'abusefilter-blocker' )->willReturn( $this->getMockMessage( 'Foo' ) );
		$ugm = $this->createMock( UserGroupManager::class );
		$ugm->expects( $this->once() )->method( 'addUserToGroup' );
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturn( $this->createMock( User::class ) );
		$okHandler = new SchemaChangesHandler( $validML, $ugm, $userFactory );
		$this->assertTrue( $okHandler->createAbuseFilterUser( $noRowUpdater ) );
	}

}
