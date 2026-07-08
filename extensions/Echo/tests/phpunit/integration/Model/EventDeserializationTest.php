<?php

namespace MediaWiki\Extension\Notifications\Test\Integration;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWikiIntegrationTestCase;
use TestLogger;

/**
 * @coversDefaultClass \MediaWiki\Extension\Notifications\Model\Event
 */
class EventDeserializationTest extends MediaWikiIntegrationTestCase {

	private function buildRow( int $eventId, string $eventType, ?string $extra ) {
		return [
			'event_id' => $eventId,
			'event_type' => $eventType,
			'event_extra' => $extra,
			'event_page_id' => null,
			'event_deleted' => false,
			'event_agent_id' => null,
			'event_agent_ip' => null,
		];
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeseriazationWithLegacyPHP() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );

		$extra = [
			'msg' => 'PHPUnit',
			'value' => 1,
		];
		$row = $this->buildRow( 1, 'test', serialize( $extra ) );

		$event = Event::newFromRow( (object)$row );
		$eventExtra = $event->getExtra();
		$this->assertCount( 2, $eventExtra );
		$this->assertSame( 'PHPUnit', $eventExtra['msg'] );
		$this->assertSame( 1, $eventExtra['value'] );

		$event = Event::newFromArray( $row );
		$eventExtra = $event->getExtra();
		$this->assertCount( 2, $eventExtra );
		$this->assertSame( 'PHPUnit', $eventExtra['msg'] );
		$this->assertSame( 1, $eventExtra['value'] );

		$this->assertSame( [], $testLogger->getBuffer() );
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeseriazationWithJSONCoded() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );

		$extra = [
			'msg' => 'PHPUnit',
			'value' => 1,
		];
		$codec = $this->getServiceContainer()->getJsonCodec();
		$row = $this->buildRow( 1, 'test', $codec->serialize( $extra ) );

		$event = Event::newFromRow( (object)$row );
		$eventExtra = $event->getExtra();
		$this->assertCount( 2, $eventExtra );
		$this->assertSame( 'PHPUnit', $eventExtra['msg'] );
		$this->assertSame( 1, $eventExtra['value'] );

		$event = Event::newFromArray( $row );
		$eventExtra = $event->getExtra();
		$this->assertCount( 2, $eventExtra );
		$this->assertSame( 'PHPUnit', $eventExtra['msg'] );
		$this->assertSame( 1, $eventExtra['value'] );

		$this->assertSame( [], $testLogger->getBuffer() );
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeserializeWhenExtraIsIndexedArray() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );

		$extra = [ 'first', 'second', 'third' ];
		$codec = $this->getServiceContainer()->getJsonCodec();

		$jsonRow = $this->buildRow( 1, 'test', $codec->serialize( $extra ) );
		$eventFromJson = Event::newFromRow( (object)$jsonRow );
		$eventJSONExtra = $eventFromJson->getExtra();
		$this->assertCount( 3, $eventJSONExtra );
		$this->assertSame( 'first', $eventJSONExtra[0] );
		$this->assertSame( 'third', $eventJSONExtra[2] );

		$serializeRow = $this->buildRow( 1, 'test', serialize( $extra ) );
		$legacyEvent = Event::newFromRow( (object)$serializeRow );
		$legacyExtra = $legacyEvent->getExtra();
		$this->assertCount( 3, $legacyExtra );
		$this->assertSame( 'first', $legacyExtra[0] );
		$this->assertSame( 'third', $legacyExtra[2] );

		$this->assertSame( [], $testLogger->getBuffer() );
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeserializeWhenExtraIsNull() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );

		$row = $this->buildRow( 1, 'test', null );
		$event = Event::newFromRow( (object)$row );
		$this->assertSame( [], $event->getExtra() );

		$this->assertSame( [], $testLogger->getBuffer() );
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeserializeWhenItsEmptyArray() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );

		$codec = $this->getServiceContainer()->getJsonCodec();
		$row = $this->buildRow( 1, 'test', $codec->serialize( [] ) );
		$event = Event::newFromRow( (object)$row );
		$this->assertSame( [], $event->getExtra() );

		$legacyRow = $this->buildRow( 1, 'test', serialize( [] ) );
		$legacyEvent = Event::newFromRow( (object)$legacyRow );
		$this->assertSame( [], $legacyEvent->getExtra() );

		$this->assertSame( [], $testLogger->getBuffer() );
	}

	/**
	 * @covers ::deserializeExtra
	 */
	public function testDeserializeWhenCorruptedData() {
		$testLogger = new TestLogger( true );
		$this->setLogger( 'Echo', $testLogger );
		$jsonCorruptedRow = $this->buildRow( 1, 'test', '{ ... corrupted[' );
		$this->assertFalse( Event::newFromRow( (object)$jsonCorruptedRow ) );

		$legacyCorruptedRow = $this->buildRow( 1, 'test', 'anything can be here' );
		$this->assertFalse( Event::newFromRow( (object)$legacyCorruptedRow ) );

		$this->assertCount( 2, $testLogger->getBuffer() );
	}

}
