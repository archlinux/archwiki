<?php

use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Model\Event;
use Wikimedia\Rdbms\IDatabase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Notifications\Mapper\EventMapper
 */
class EventMapperTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'echo_event';
		$this->tablesUsed[] = 'echo_notification';
		$this->tablesUsed[] = 'echo_target_page';
	}

	public function provideDataTestInsert() {
		return [
			[
				'successful insert with next sequence = 1',
				[ 'insert' => true, 'insertId' => 1 ],
				1
			],
			[
				'successful insert with insert id = 2',
				[ 'insert' => true, 'insertId' => 2 ],
				2
			]
		];
	}

	/**
	 * @dataProvider provideDataTestInsert
	 */
	public function testInsert( $message, $dbResult, $result ) {
		$event = $this->mockEvent();
		$eventMapper = new EventMapper( $this->mockMWEchoDbFactory( $dbResult ) );
		$this->assertEquals( $result, $eventMapper->insert( $event ), $message );
	}

	/**
	 * Successful fetchById()
	 */
	public function testSuccessfulFetchById() {
		$eventMapper = new EventMapper(
			$this->mockMWEchoDbFactory(
				[
					'selectRow' => (object)[
						'event_id' => 1,
						'event_type' => 'test',
						'event_variant' => '',
						'event_extra' => '',
						'event_page_id' => '',
						'event_agent_id' => '',
						'event_agent_ip' => '',
						'event_deleted' => 0,
					]
				]
			)
		);
		$res = $eventMapper->fetchById( 1 );
		$this->assertInstanceOf( Event::class, $res );
	}

	public function testUnsuccessfulFetchById() {
		$eventMapper = new EventMapper(
			$this->mockMWEchoDbFactory(
				[
					'selectRow' => false
				]
			)
		);
		$this->expectException( MWException::class );
		$eventMapper->fetchById( 1 );
	}

	/**
	 * @return Event
	 */
	protected function mockEvent() {
		$event = $this->createMock( Event::class );
		$event->method( 'toDbArray' )
			->willReturn( [] );

		return $event;
	}

	/**
	 * @param array $dbResult
	 * @return MWEchoDbFactory
	 */
	protected function mockMWEchoDbFactory( $dbResult ) {
		$dbFactory = $this->createMock( MWEchoDbFactory::class );
		$dbFactory->method( 'getEchoDb' )
			->willReturn( $this->mockDb( $dbResult ) );

		return $dbFactory;
	}

	/**
	 * @param array $dbResult
	 * @return IDatabase
	 */
	protected function mockDb( array $dbResult ) {
		$dbResult += [
			'insert' => '',
			'insertId' => '',
			'select' => '',
			'selectRow' => ''
		];
		$db = $this->createMock( IDatabase::class );
		$db->method( 'insert' )
			->willReturn( $dbResult['insert'] );
		$db->method( 'insertId' )
			->willReturn( $dbResult['insertId'] );
		$db->method( 'select' )
			->willReturn( $dbResult['select'] );
		$db->method( 'selectRow' )
			->willReturn( $dbResult['selectRow'] );

		return $db;
	}

	public function testFetchByPage() {
		$user = $this->getTestUser()->getUser();
		$page = $this->getExistingTestPage();

		// Create a notification that is not associated with any page
		Event::create( [
			'type' => 'welcome',
			'agent' => $user,
		] );

		// Create a notification with a title
		$eventWithTitle = Event::create( [
			'type' => 'welcome',
			'agent' => $user,
			'title' => $page->getTitle()
		] );

		// Create a notification with a target-page
		$eventWithTargetPage = Event::create( [
			'type' => 'welcome',
			'agent' => $user,
			'extra' => [ 'target-page' => $page->getId() ]
		] );

		$eventMapper = new EventMapper();

		$this->assertArrayEquals(
			[ $eventWithTitle->getId(), $eventWithTargetPage->getId() ],
			$eventMapper->fetchIdsByPage( $page->getId() )
		);
	}

}
