<?php

namespace MediaWiki\Extension\Notifications\Test;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

/**
 * @group Echo
 * @group Database
 */
class MentionFunctionalTest extends MediaWikiIntegrationTestCase {

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

	/**
	 * @covers \MediaWiki\Extension\Notifications\MediaWikiEventIngress\PageEventIngress
	 */
	public function testMentionInText() {
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$targetUser = $this->getMutableTestUser()->getUser();
		$title = Title::makeTitle( NS_TALK, __METHOD__ );
		$editText = "Thank you [[User:{$targetUser->getName()}]]! -- ~~~~";

		$db = $this->getDb();
		$this->editPage( $title, $editText, '', NS_MAIN, $user );
		DeferredUpdates::tryOpportunisticExecute();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $targetUser, 10, null, [ 'mention' ] );
		$this->assertCount( 1, $notifications );

		// null edit
		$this->editPage( $title, $editText, '', NS_MAIN, $user );
		DeferredUpdates::tryOpportunisticExecute();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $targetUser, 10, null, [ 'mention' ] );
		$this->assertCount( 1, $notifications );
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\MediaWikiEventIngress\PageEventIngress
	 */
	public function testMentionInSummary() {
		$this->overrideConfigValues( [
			// enable pings from summary
			'EchoMaxMentionsInEditSummary' => 1,
		] );

		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$targetUser = $this->getMutableTestUser()->getUser();
		$title = Title::makeTitle( NS_TALK, __METHOD__ );
		$editSummary = "Thank you [[User:{$targetUser->getName()}]]!";

		$db = $this->getDb();
		$this->editPage( $title, 'Test Content', $editSummary, NS_MAIN, $user );
		DeferredUpdates::tryOpportunisticExecute();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $targetUser, 10, null, [ 'mention-summary' ] );
		$this->assertCount( 1, $notifications );

		// null edit
		$this->editPage( $title, 'Test Content', $editSummary, NS_MAIN, $user );
		DeferredUpdates::tryOpportunisticExecute();

		// assertions
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $targetUser, 10, null, [ 'mention-summary' ] );
		$this->assertCount( 1, $notifications );
	}
}
