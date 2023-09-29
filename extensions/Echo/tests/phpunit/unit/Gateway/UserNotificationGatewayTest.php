<?php

use MediaWiki\Extension\Notifications\Gateway\UserNotificationGateway;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\Notifications\Gateway\UserNotificationGateway
 */
class UserNotificationGatewayTest extends MediaWikiUnitTestCase {

	public function testMarkRead() {
		// no event ids to mark
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory(),
			$this->mockConfig()
		);
		$this->assertFalse( $gateway->markRead( [] ) );

		// successful update
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'update' => true ] ),
			$this->mockConfig()
		);
		$this->assertTrue( $gateway->markRead( [ 2 ] ) );

		// unsuccessful update
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'update' => false ] ),
			$this->mockConfig()
		);
		$this->assertFalse( $gateway->markRead( [ 2 ] ) );
	}

	public function testMarkAllRead() {
		// successful update
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'update' => true ] ),
			$this->mockConfig()
		);
		$this->assertTrue( $gateway->markAllRead( [ 2 ] ) );

		// null update
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'update' => false ] ),
			$this->mockConfig()
		);
		$this->assertTrue( $gateway->markAllRead( [ 2 ] ) );
	}

	public function testGetNotificationCount() {
		// unsuccessful select
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'selectRowCount' => 0 ] ),
			$this->mockConfig()
		);
		$this->assertSame( 0, $gateway->getCappedNotificationCount( DB_REPLICA, [ 'event_one' ] ) );

		// successful select of alert
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'selectRowCount' => 2 ] ),
			$this->mockConfig()
		);
		$this->assertSame( 2, $gateway->getCappedNotificationCount( DB_REPLICA, [ 'event_one', 'event_two' ] ) );

		// there is event, should return 0
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'selectRowCount' => 2 ] ),
			$this->mockConfig()
		);
		$this->assertSame( 0, $gateway->getCappedNotificationCount( DB_REPLICA, [] ) );

		// successful select
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'selectRowCount' => 3 ] ),
			$this->mockConfig()
		);
		$this->assertSame( 3, $gateway->getCappedNotificationCount( DB_REPLICA, [ 'event_one' ] ) );
	}

	public function testGetUnreadNotifications() {
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'select' => false ] ),
			$this->mockConfig()
		);
		$this->assertSame( [], $gateway->getUnreadNotifications( 'user_talk' ) );

		$dbResult = [
			(object)[ 'notification_event' => 1 ],
			(object)[ 'notification_event' => 2 ],
			(object)[ 'notification_event' => 3 ],
		];
		$gateway = new UserNotificationGateway(
			$this->mockUser(),
			$this->mockMWEchoDbFactory( [ 'select' => $dbResult ] ),
			$this->mockConfig()
		);
		$res = $gateway->getUnreadNotifications( 'user_talk' );
		$this->assertEquals( [ 1 => 1, 2 => 2, 3 => 3 ], $res );
	}

	/**
	 * Mock object of User
	 * @return User
	 */
	protected function mockUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getID' )
			->willReturn( 1 );

		return $user;
	}

	/**
	 * Mock object of MWEchoDbFactory
	 * @param array $dbResult
	 * @return MWEchoDbFactory
	 */
	protected function mockMWEchoDbFactory( array $dbResult = [] ) {
		$dbFactory = $this->createMock( MWEchoDbFactory::class );
		$dbFactory->method( 'getEchoDb' )
			->willReturn( $this->mockDb( $dbResult ) );

		return $dbFactory;
	}

	protected function mockConfig() {
		return new HashConfig( [
			'UpdateRowsPerQuery' => 500,
		] );
	}

	/**
	 * Returns a mock database object
	 * @param array $dbResult
	 * @return \Wikimedia\Rdbms\IDatabase
	 */
	protected function mockDb( array $dbResult = [] ) {
		$dbResult += [
			'update' => '',
			'select' => '',
			'selectRow' => '',
			'selectRowCount' => '',
		];
		$db = $this->createMock( IDatabase::class );
		$db->method( 'update' )
			->willReturn( $dbResult['update'] );
		$db->method( 'select' )
			->willReturn( $dbResult['select'] );
		$db->method( 'selectRow' )
			->willReturn( $dbResult['selectRow'] );
		$db->method( 'selectRowCount' )
			->willReturn( $dbResult['selectRowCount'] );

		return $db;
	}

}
