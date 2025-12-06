<?php

namespace MediaWiki\Extension\Thanks\Tests\Integration;

use MediaWiki\Extension\Thanks\Storage\LogStore;
use MediaWiki\Extension\Thanks\ThanksQueryHelper;
use MediaWiki\Request\FauxRequest;

/**
 * @covers \MediaWiki\Extension\Thanks\ThanksQueryHelper
 * @group Database
 */
class ThanksQueryHelperTest extends \MediaWikiIntegrationTestCase {

	public function testGetThanksGivenAndReceived() {
		/** @var ThanksQueryHelper $thanksQueryHelper */
		$thanksQueryHelper = $this->getServiceContainer()->get( 'ThanksQueryHelper' );
		$thanksReceivingUser = $this->getTestUser()->getUser();
		/** @var LogStore $logStore */
		$logStore = $this->getServiceContainer()->get( 'ThanksLogStore' );
		// Pick a user group for the test user, so we use a different user for giver/receiver
		$thanksGiverUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$logStore->thank( $thanksGiverUser, $thanksReceivingUser, 'foo' );
		$this->assertSame( 0, $thanksQueryHelper->getThanksReceivedCount( $thanksGiverUser ) );
		$this->assertSame( 1, $thanksQueryHelper->getThanksReceivedCount( $thanksReceivingUser ) );
		$this->assertSame( 1, $thanksQueryHelper->getThanksGivenCount( $thanksGiverUser ) );
		$this->assertSame( 0, $thanksQueryHelper->getThanksGivenCount( $thanksReceivingUser ) );
	}

	public function testGetThanksGivenTempAccount() {
		/** @var ThanksQueryHelper $thanksQueryHelper */
		$thanksQueryHelper = $this->getServiceContainer()->get( 'ThanksQueryHelper' );
		$thanksReceivingUser = $this->getTestUser()->getUser();
		/** @var LogStore $logStore */
		$logStore = $this->getServiceContainer()->get( 'ThanksLogStore' );
		// Temporary accounts cannot give thanks (T345679)
		$thanksGiverUser = $this->getServiceContainer()->getTempUserCreator()
			->create( '~2025-01', new FauxRequest() )
			->getUser();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Temporary accounts may not thank other users.' );
		$logStore->thank( $thanksGiverUser, $thanksReceivingUser, 'foo' );
	}

	public function testGetThanksGivenAndReceivedDeletedLogEntry() {
		/** @var ThanksQueryHelper $thanksQueryHelper */
		$thanksQueryHelper = $this->getServiceContainer()->get( 'ThanksQueryHelper' );
		$thanksReceivingUser = $this->getTestUser()->getUser();
		/** @var LogStore $logStore */
		$logStore = $this->getServiceContainer()->get( 'ThanksLogStore' );
		// Pick a user group for the test user, so we use a different user for giver/receiver
		$thanksGiverUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$logStore->thank( $thanksGiverUser, $thanksReceivingUser, 'foo' );

		// Mark the log entry as deleted
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'logging' )
			->set( [ 'log_deleted' => 15 ] )
			->where( [ 'log_type' => 'thanks' ] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( 0, $thanksQueryHelper->getThanksReceivedCount( $thanksGiverUser ) );
		$this->assertSame( 0, $thanksQueryHelper->getThanksReceivedCount( $thanksReceivingUser ) );
		$this->assertSame( 0, $thanksQueryHelper->getThanksGivenCount( $thanksGiverUser ) );
		$this->assertSame( 0, $thanksQueryHelper->getThanksGivenCount( $thanksReceivingUser ) );
	}
}
