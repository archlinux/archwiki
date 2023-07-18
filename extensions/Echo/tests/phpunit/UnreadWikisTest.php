<?php

use Wikimedia\TestingAccessWrapper;

/**
 * Tests for unread wiki database access
 *
 * @group Database
 * @covers \EchoUnreadWikis
 */
class UnreadWikisTest extends MediaWikiIntegrationTestCase {

	public function testUpdateCount() {
		$unread = TestingAccessWrapper::newFromObject( new EchoUnreadWikis( 1 ) );
		$unread->dbFactory = $this->mockMWEchoDbFactory( $this->db );
		$unread->updateCount(
			'foobar',
			2,
			new MWTimestamp( '20220322222222' ),
			3,
			new MWTimestamp( '20220322222223' )
		);
		$this->assertSame(
			[
				'foobar' => [
					'alert' => [ 'count' => '2', 'ts' => '20220322222222' ],
					'message' => [ 'count' => '3', 'ts' => '20220322222223' ],
				]
			],
			$unread->getUnreadCounts()
		);
	}

	public function testUpdateCountFalse() {
		$unread = TestingAccessWrapper::newFromObject( new EchoUnreadWikis( 1 ) );
		$unread->dbFactory = $this->mockMWEchoDbFactory( $this->db );
		$unread->updateCount(
			'foobar',
			3,
			false,
			4,
			false
		);
		$this->assertSame(
			[
				'foobar' => [
					'alert' => [ 'count' => '3', 'ts' => '00000000000000' ],
					'message' => [ 'count' => '4', 'ts' => '00000000000000' ],
				]
			],
			$unread->getUnreadCounts()
		);
	}

	/**
	 * Mock object of MWEchoDbFactory
	 * @param \Wikimedia\Rdbms\IDatabase $db
	 * @return MWEchoDbFactory
	 */
	protected function mockMWEchoDbFactory( $db ) {
		$dbFactory = $this->createMock( MWEchoDbFactory::class );
		$dbFactory->expects( $this->any() )
			->method( 'getSharedDb' )
			->will( $this->returnValue( $db ) );

		return $dbFactory;
	}
}
