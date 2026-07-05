<?php

namespace MediaWiki\Extension\Notifications\Test\Integration\Mapper;

use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Model\Notification;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Notifications\Mapper\NotificationMapper
 * @group Database
 */
class NotificationMapperDatabaseTest extends MediaWikiIntegrationTestCase {

	/** @var int Allows using a unique ID for each {@link Event} returned by {@link self::makeEvent} */
	private static int $nextEventId = 10;

	public function testDeleteByUserAndAge() {
		$this->overrideConfigValues( [
			MainConfigNames::UpdateRowsPerQuery => 2,
			'EchoCluster' => false,
			'EchoSharedTrackingDB' => false,
			'EchoSharedTrackingCluster' => false,
		] );

		// Insert four notifications, one of which is recent
		$user = $this->getTestUser()->getUser();
		$firstNotification = new Notification( $user, $this->makeEvent( '20150607080901' ) );
		$firstNotification->insert();
		$secondNotification = new Notification( $user, $this->makeEvent( '20260607080903' ) );
		$secondNotification->insert();
		$thirdNotification = new Notification( $user, $this->makeEvent( '20150607080902' ) );
		$thirdNotification->insert();
		$fourthNotification = new Notification( $user, $this->makeEvent( '20160607080903' ) );
		$fourthNotification->insert();

		// Check the DB is correctly set up for the test
		$this->newSelectQueryBuilder()
			->select( 'notification_event' )
			->from( 'echo_notification' )
			->assertFieldValues( [
				(string)$firstNotification->getEvent()->getId(),
				(string)$secondNotification->getEvent()->getId(),
				(string)$thirdNotification->getEvent()->getId(),
				(string)$fourthNotification->getEvent()->getId(),
			] );

		// Run the code we are testing with a max age of 5 years (the default for the extension)
		$notifMapper = new NotificationMapper( DbFactory::newFromDefault() );
		$notifMapper->deleteByUserAndAge( $user, 157783680 );

		// Assert that the notification that was too new and the last notification was
		// not deleted (as we delete a max of 2 events)
		$this->newSelectQueryBuilder()
			->select( 'notification_event' )
			->from( 'echo_notification' )
			->assertFieldValues( [
				(string)$secondNotification->getEvent()->getId(),
				(string)$fourthNotification->getEvent()->getId(),
			] );
	}

	private function makeEvent( string $timestamp ): Event {
		$agent = $this->getTestSysop()->getUser();
		return Event::newFromRow( (object)[
			'event_id' => static::$nextEventId++,
			'event_type' => 'welcome',
			'event_page_id' => 1,
			'event_deleted' => 0,
			'event_agent_id' => $agent->getId(),
			'event_extra' => '',
			'notification_timestamp' => $timestamp,
		] );
	}
}
