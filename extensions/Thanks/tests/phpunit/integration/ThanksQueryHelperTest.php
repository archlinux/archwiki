<?php

namespace MediaWiki\Extension\Thanks\Tests\Integration;

use MediaWiki\Extension\Thanks\Storage\LogStore;
use MediaWiki\Extension\Thanks\ThanksQueryHelper;

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
}
