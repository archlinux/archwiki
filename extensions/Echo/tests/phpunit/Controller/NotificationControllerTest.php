<?php

namespace MediaWiki\Extension\Notifications\Test\Controller;

use MapCacheLRU;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Controller\NotificationController;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\UserLocator;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\TestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Notifications\Controller\NotificationController
 */
class NotificationControllerTest extends MediaWikiIntegrationTestCase {

	public static function evaluateUserLocatorsProvider() {
		return [
			[
				'With no options only default recipient locator is returned',
				// expected result
				[ [ 987, 123 ] ],
				// event user locator config
				[],
				static function ( TestCase $test, $event ) {
					$event->expects( $test->once() )
						->method( 'getExtraParam' )
						->with( Event::RECIPIENTS_IDX )
						->willReturn( [ 987, 123 ] );
				}
			],

			[
				'Does not error when given non-existant user-locator and recipients is not set ',
				// expected result
				[ [] ],
				// event user locator config
				[ 'not-callable' ],
			],

			[
				'Calls selected locator and returns result',
				// expected result
				[ [ 123 ], [] ],
				// event user locator config
				static function () {
					return [ 123 => 123 ];
				}
			],

			[
				'Passes parameters to locateFromEventExtra in expected manner',
				// expected result
				[ [ 123 ], [ 256 ] ],
				// event user locator config
				[
					[ [ UserLocator::class, 'locateFromEventExtra' ], [ 'other-user' ] ],
				],
				// additional setup
				static function ( TestCase $test, $event ) {
					$event->expects( $test->exactly( 2 ) )
						->method( 'getExtraParam' )
						->withConsecutive( [ 'other-user' ], [ Event::RECIPIENTS_IDX ] )
						->willReturnOnConsecutiveCalls( 123, [ 256 ] );
				}
			],
		];
	}

	/**
	 * @dataProvider evaluateUserLocatorsProvider
	 */
	public function testEvaluateUserLocators( $message, $expect, $locatorConfigForEventType, $setup = null ) {
		$this->overrideConfigValue( 'EchoNotifications', [
			'unit-test' => [
				AttributeManager::ATTR_LOCATORS => $locatorConfigForEventType
			],
		] );

		$event = $this->createMock( Event::class );
		$event->method( 'getType' )
			->willReturn( 'unit-test' );

		if ( $setup !== null ) {
			$setup( $this, $event );
		}

		$result = NotificationController::evaluateUserCallable( $event, AttributeManager::ATTR_LOCATORS );
		$this->assertEquals( $expect, array_map( 'array_keys', $result ), $message );
	}

	public function testEvaluateUserLocatorPassesParameters() {
		$callback = function ( $event, $firstOption, $secondOption ) {
			$this->assertInstanceOf( Event::class, $event );
			$this->assertEquals( 'first', $firstOption );
			$this->assertEquals( 'second', $secondOption );

			return [];
		};

		$this->testEvaluateUserLocators(
			__FUNCTION__,
			[ [], [] ],
			[ [ $callback, 'first', 'second' ] ]
		);
	}

	public static function getUsersToNotifyForEventProvider() {
		return [
			[
				'Filters anonymous users',
				// expected result
				[],
				// users returned from locator
				[ User::newFromName( '4.5.6.7', false ) ],
			],

			[
				'Filters duplicate users',
				// expected result
				[ 123 ],
				// users returned from locator
				[ User::newFromId( 123 ), User::newFromId( 123 ) ],
			],

			[
				'Filters non-user objects',
				// expected result
				[ 123 ],
				// users returned from locator
				[ null, 'foo', User::newFromId( 123 ), (object)[], 456 ],
			],
		];
	}

	/**
	 * @dataProvider getUsersToNotifyForEventProvider
	 */
	public function testGetUsersToNotifyForEvent(
		$message,
		$expect,
		$users
	) {
		$this->overrideConfigValue( 'EchoNotifications', [
			'unit-test' => [
				AttributeManager::ATTR_LOCATORS => static function () use ( $users ) {
					return $users;
				},
			],
		] );

		$event = $this->createMock( Event::class );
		$event->method( 'getType' )
			->willReturn( 'unit-test' );

		$result = NotificationController::getUsersToNotifyForEvent( $event );
		$ids = [];
		foreach ( $result as $user ) {
			$ids[] = $user->getId();
		}
		$this->assertEquals( $expect, $ids, $message );
	}

	public function testDoesNotDeliverDisabledEvent() {
		$event = $this->createMock( Event::class );
		$event->method( 'isEnabledEvent' )
			->willReturn( false );
		// Assume it would have to check the event type to
		// determine how to deliver
		$event->expects( $this->never() )
			->method( 'getType' );

		NotificationController::notify( $event, false );
	}

	public static function getEventNotifyTypesProvider() {
		return [
			[
				'Selects the `all` configuration by default',
				// expected result
				[ 'web' ],
				// event type
				'bar',
				// default notification types configuration
				[ 'web' => true ],
				// per-category notification type availability
				[
					'f' => [ 'email' => true ]
				],
				// event types
				[
					'foo' => [
						'category' => 'f',
					],
					'bar' => [
						'category' => 'b',
					]
				],
			],

			[
				'Overrides `all` configuration with event category configuration',
				// expected result
				[ 'web' ],
				// event type
				'foo',
				// default notification types configuration
				[ 'web' => true, 'email' => true ],
				// per-category notification type availability
				[
					'f' => [ 'email' => false ],
					'b' => [ 'sms' => true ],
				],
				// event types
				[
					'foo' => [
						'category' => 'f',
					],
					'bar' => [
						'category' => 'b',
					],
				],
			]
		];
	}

	/**
	 * @dataProvider getEventNotifyTypesProvider
	 */
	public function testGetEventNotifyTypes(
		$message,
		$expect,
		$type,
		array $defaultNotifyTypeAvailability,
		array $notifyTypeAvailabilityByCategory,
		array $notifications
	) {
		$this->overrideConfigValues( [
			'DefaultNotifyTypeAvailability' => $defaultNotifyTypeAvailability,
			'NotifyTypeAvailabilityByCategory' => $notifyTypeAvailabilityByCategory,
			'EchoNotifications' => $notifications,
			'EchoNotificationCategories' => array_fill_keys(
				array_keys( $notifyTypeAvailabilityByCategory ),
				[ 'priority' => 4 ]
			),
		] );
		$result = NotificationController::getEventNotifyTypes( $type );
		$this->assertEquals( $expect, $result, $message );
	}

	public function testEnqueueEvent() {
		$event = $this->createMock( Event::class );
		$event->method( 'getExtraParam' )
			->willReturn( null );
		$event->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( Title::newFromText( 'test-title' ) );
		$event->expects( $this->once() )
			->method( 'toDbArray' )
			->willReturn( [
				'event_type' => 'test',
				'event_extra' => [
					'extra-key' => 'extra'
				],
			] );
		NotificationController::enqueueEvent( $event );
		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$queues = $jobQueueGroup->getQueuesWithJobs();
		$this->assertCount( 1, $queues );
		$this->assertEquals( 'EchoNotificationJob', $queues[0] );
		$job = $jobQueueGroup->pop( 'EchoNotificationJob' );
		$this->assertEquals( 'Test-title', $job->params[ 'title' ] );
		$this->assertEquals(
			[
				'event_type' => 'test',
				'event_extra' => [
					'extra-key' => 'extra'
				],
			],
			$job->params[ 'eventData' ]
		);
	}

	public function testNotSupportedDelay() {
		$queueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$this->assertCount( 0, $queueGroup->getQueuesWithJobs() );

		$event = $this->createMock( Event::class );
		$event->method( 'getExtraParam' )
			->willReturnMap( [
				[ 'delay', null, 120 ],
				[ 'rootJobSignature', null, 'test-signature' ],
				[ 'rootJobTimestamp', null, wfTimestamp() ]
			] );
		$event->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( Title::newFromText( 'test-title' ) );
		$event->method( 'getId' )
			->willReturn( 42 );
		NotificationController::enqueueEvent( $event );

		$this->assertCount( 0, $queueGroup->getQueuesWithJobs() );
	}

	public function testEventParams() {
		$rootJobTimestamp = wfTimestamp();
		MWTimestamp::setFakeTime( 0 );

		$event = $this->createMock( Event::class );
		$event->method( 'getExtraParam' )
			->willReturnMap( [
				[ 'delay', null, 10 ],
				[ 'rootJobSignature', null, 'test-signature' ],
				[ 'rootJobTimestamp', null, $rootJobTimestamp ]
			] );
		$event->expects( $this->once() )
			->method( 'toDbArray' )
			->willReturn( [
				'event_type' => 'test',
				'event_extra' => [
					'delay' => 10,
					'rootJobSignature' => 'test-signature',
					'rootJobTimestamp' => $rootJobTimestamp,
				],
			] );

		$params = NotificationController::getEventParams( $event );
		$expectedParams = [
			'eventData' => [
				'event_type' => 'test',
				'event_extra' => [
					'delay' => 10,
					'rootJobSignature' => 'test-signature',
					'rootJobTimestamp' => $rootJobTimestamp,
				],
			],
			'rootJobSignature' => 'test-signature',
			'rootJobTimestamp' => $rootJobTimestamp,
			'jobReleaseTimestamp' => 10
		];
		$this->assertArrayEquals( $expectedParams, $params );
	}

	/**
	 * @dataProvider pageLinkedTitleMutedByUserDataProvider
	 * @param int $mockArticleID
	 * @param int[] $mockMutedTitlePreferences
	 * @param bool $expected
	 */
	public function testIsPageLinkedTitleMutedByUser(
		int $mockArticleID, array $mockMutedTitlePreferences, $expected ): void {
		$title = $this->getMockTitle( $mockArticleID );
		$user = $this->getMockUser();
		$userOptionsLookup = $this->getUserOptionsLookupMock( $mockMutedTitlePreferences );
		$wrapper = TestingAccessWrapper::newFromClass( NotificationController::class );
		$wrapper->mutedPageLinkedTitlesCache = $this->createMock( MapCacheLRU::class );
		$this->setService( 'UserOptionsLookup', $userOptionsLookup );
		$this->assertSame(
			$expected,
			$wrapper->isPageLinkedTitleMutedByUser( $title, $user )
		);
	}

	public static function pageLinkedTitleMutedByUserDataProvider(): array {
		return [
			[
				123,
				[],
				false
			],
			[
				123,
				[ 123, 456, 789 ],
				true
			],
			[
				456,
				[ 489 ],
				false
			]

		];
	}

	private function getMockTitle( int $articleID ) {
		$title = $this->createMock( Title::class );
		$title->method( 'getArticleID' )
			->willReturn( $articleID );
		return $title;
	}

	private function getMockUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getId' )
			->willReturn( 456 );
		return $user;
	}

	private function getUserOptionsLookupMock( $mutedTitlePreferences = [] ) {
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->willReturn( implode( "\n", $mutedTitlePreferences ) );
		return $userOptionsLookupMock;
	}
}
