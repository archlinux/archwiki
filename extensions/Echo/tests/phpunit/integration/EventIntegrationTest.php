<?php

use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers \MediaWiki\Extension\Notifications\Model\Event
 * @covers \MediaWiki\Extension\Notifications\Controller\NotificationController
 * @covers \MediaWiki\Extension\Notifications\Jobs\NotificationJob
 * @group Database
 */
class EventIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testEventInsertionImmediate() {
		$this->clearHook( 'BeforeEchoEventInsert' );
		$this->overrideConfigValue( 'EchoUseJobQueue', false );

		$user = $this->getTestUser()->getUser();
		$event = Event::create( [
			'type' => 'welcome',
			'agent' => $user,
			'extra' => [ 'key' => 'value' ]
		] );
		$eventId = $event->getId();
		$this->assertNotFalse( $eventId );
		$this->assertSame( $eventId, $event->acquireId() );
		$this->assertSelect(
			'echo_event',
			[ 'count' => 'COUNT(*)' ],
			[ 'event_type' => 'welcome' ],
			[ [ '1' ] ]
		);
	}

	public function testEventNotInserted() {
		$this->clearHook( 'BeforeEchoEventInsert' );
		$this->overrideConfigValue( 'EchoUseJobQueue', false );

		$event = Event::create( [
			'type' => 'welcome',
			// anons cannot be notified
			'agent' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
			'extra' => [ 'key' => 'value' ]
		] );
		$this->assertFalse( $event->getId() );
		$this->assertSelect(
			'echo_event',
			[ 'count' => 'COUNT(*)' ],
			[ 'event_type' => 'welcome' ],
			[ [ '0' ] ]
		);
	}

	public function testEventNotCreatedForUnknownUser() {
		$this->clearHook( 'BeforeEchoEventInsert' );

		$event = Event::create( [
			'type' => 'welcome',
			'agent' => UserIdentityValue::newAnonymous( 'Anonymous user' ),
		] );
		$this->assertFalse( $event );
	}

	public function testEventInsertionDeferred() {
		$this->markTestSkipped( 'T386364' );
		$this->clearHook( 'BeforeEchoEventInsert' );
		$this->clearHook( 'PageSaveComplete' );
		$this->overrideConfigValue( 'EchoUseJobQueue', true );

		$user = $this->getTestUser()->getUser();
		$title = $this->getExistingTestPage()->getTitle();
		$this->runJobs();

		$event = Event::create( [
			'type' => 'welcome',
			'agent' => $user,
			'title' => $title,
			'extra' => [ 'key' => 'value' ]
		] );
		$this->assertFalse( $event->getId() );
		unset( $event );

		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$queues = $jobQueueGroup->getQueuesWithJobs();
		$this->assertSame( [ 'EchoNotificationJob' ], $queues );
		$job = $jobQueueGroup->pop( 'EchoNotificationJob' );
		$job->run();

		$eventMapper = new EventMapper();
		$events = $eventMapper->fetchByPage( $title->getArticleID() );
		$this->assertCount( 1, $events );
		[ $event ] = $events;
		$this->assertSame( 'welcome', $event->getType() );
		$this->assertTrue( $user->equals( $event->getAgent() ) );
	}

}
