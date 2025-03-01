<?php

namespace MediaWiki\Extension\Notifications\Test\Integration\Model;

use InvalidArgumentException;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Model\Notification;
use MediaWiki\Extension\Notifications\Model\TargetPage;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\Extension\Notifications\Model\Notification
 */
class NotificationTest extends MediaWikiIntegrationTestCase {

	public function testNewFromRow() {
		$row = $this->mockNotificationRow() + $this->mockEventRow();

		$notif = Notification::newFromRow( (object)$row );
		$this->assertInstanceOf( Notification::class, $notif );
		// getReadTimestamp() should return null
		$this->assertNull( $notif->getReadTimestamp() );
		$this->assertEquals(
			$notif->getTimestamp(),
			wfTimestamp( TS_MW, $row['notification_timestamp'] )
		);
		$this->assertInstanceOf( Event::class, $notif->getEvent() );
		$this->assertNull( $notif->getTargetPages() );

		// Provide a read timestamp
		$row['notification_read_timestamp'] = time() + 1000;
		$notif = Notification::newFromRow( (object)$row );
		// getReadTimestamp() should return the timestamp in MW format
		$this->assertEquals(
			$notif->getReadTimestamp(),
			wfTimestamp( TS_MW, $row['notification_read_timestamp'] )
		);

		$notif = Notification::newFromRow( (object)$row, [
			TargetPage::newFromRow( (object)$this->mockTargetPageRow() )
		] );
		$this->assertNotEmpty( $notif->getTargetPages() );
		foreach ( $notif->getTargetPages() as $targetPage ) {
			$this->assertInstanceOf( TargetPage::class, $targetPage );
		}
	}

	public function testNewFromRowWithException() {
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->method( $this->logicalOr( 'select', 'from', 'where', 'caller' ) )->willReturnSelf();
		$queryBuilder->method( 'fetchRow' )->willReturn( false );
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newSelectQueryBuilder' )->willReturn( $queryBuilder );
		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnection' )->willReturn( $db );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getExternalLB' )->willReturn( $lb );
		$this->setService( 'DBLoadBalancer', $lb );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
		$row = $this->mockNotificationRow();
		// Provide an invalid event id
		$row['notification_event'] = -1;
		$this->expectException( InvalidArgumentException::class );
		Notification::newFromRow( (object)$row );
	}

	/**
	 * Mock a notification row from database
	 */
	protected function mockNotificationRow(): array {
		return [
			'notification_user' => 1,
			'notification_event' => 1,
			'notification_timestamp' => time(),
			'notification_read_timestamp' => null,
			'notification_bundle_hash' => 'testhash',
		];
	}

	/**
	 * Mock an event row from database
	 */
	protected function mockEventRow(): array {
		return [
			'event_id' => 1,
			'event_type' => 'test_event',
			'event_variant' => '',
			'event_extra' => '',
			'event_page_id' => '',
			'event_agent_id' => '',
			'event_agent_ip' => '',
			'event_deleted' => 0,
		];
	}

	/**
	 * Mock a target page row
	 */
	protected function mockTargetPageRow(): array {
		return [
			'etp_page' => 2,
			'etp_event' => 1
		];
	}

}
