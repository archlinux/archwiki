<?php

namespace MediaWiki\Extension\Notifications\Test;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\Model\Notification;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

/**
 * @group Echo
 * @group Database
 */
class ThankYouEditTest extends MediaWikiIntegrationTestCase {

	private function deleteEchoData() {
		$db = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_event' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_notification' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
	}

	public static function provideFirstEditRequestModes() {
		return [
			[ 'web' ],
			[ 'cli' ]
		];
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\Hooks::onPageSaveComplete
	 * @dataProvider provideFirstEditRequestModes
	 * @param string $mode
	 */
	public function testFirstEdit( $mode ) {
		// TODO: re-renable once I50aa9fe9387c9b7b7ff97dfd39a2830bce647db8 is merged.
		// That is, after, endAtomic() in PageUpdater::doCreate() is tweaked
		if ( $mode === 'cli' ) {
			$this->markTestSkipped();
		}

		// setup
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'Help:MWEchoThankYouEditTest_testFirstEdit' );

		// action
		$db = $this->getDb();
		// Web requests wrap the edit in a broader transaction via DBO_TRX and commit it in
		// MediaWikiEntryPoint::commitMainTransaction(). We can largely simulate that by just
		// using atomic sections.
		$useAtomicSection = ( $mode === 'web' );
		if ( $useAtomicSection ) {
			$db->startAtomic( __METHOD__ );
		}
		$this->editPage( $title, 'this is my first edit', '', NS_MAIN, $user );
		if ( $useAtomicSection ) {
			$db->endAtomic( __METHOD__ );
		}
		DeferredUpdates::tryOpportunisticExecute();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'thank-you-edit' ] );
		$this->assertCount( 1, $notifications );

		/** @var Notification $notification */
		$notification = reset( $notifications );
		$this->assertSame( 1, $notification->getEvent()->getExtraParam( 'editCount', 'not found' ) );
	}

	public static function provideTenthEditRequestModes() {
		return [
			[ 'web' ],
			[ 'cli' ]
		];
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\Hooks::onPageSaveComplete
	 * @dataProvider provideTenthEditRequestModes
	 * @param string $mode
	 */
	public function testTenthEdit( $mode ) {
		// TODO: re-renable once endAtomic() in PageUpdater::doCreate() is tweaked
		if ( $mode === 'cli' ) {
			$this->markTestSkipped();
		}

		// setup
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'Help:MWEchoThankYouEditTest_testTenthEdit' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		// action
		// we could fast-forward the edit-count to speed things up
		// but this is the only way to make sure duplicate notifications
		// are not generated
		$db = $this->getDb();
		// Web requests wrap the edit in a broader transaction via DBO_TRX and commit it in
		// MediaWikiEntryPoint::commitMainTransaction(). We can largely simulate that by just
		// using atomic sections.
		$useAtomicSection = ( $mode === 'web' );
		for ( $i = 0; $i < 12; $i++ ) {
			if ( $useAtomicSection ) {
				$db->startAtomic( __METHOD__ );
			}
			$this->editPage( $page, "this is edit #$i", '', NS_MAIN, $user );
			if ( $useAtomicSection ) {
				$db->endAtomic( __METHOD__ );
			}
			DeferredUpdates::tryOpportunisticExecute();
		}
		$user->clearInstanceCache();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'thank-you-edit' ] );
		$this->assertCount( 2, $notifications );

		/** @var Notification $notification */
		$notification = reset( $notifications );
		$this->assertSame( 10, $notification->getEvent()->getExtraParam( 'editCount', 'not found' ) );
	}
}
