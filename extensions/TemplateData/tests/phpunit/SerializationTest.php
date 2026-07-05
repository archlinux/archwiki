<?php

use MediaWiki\Extension\TemplateData\TemplateDataStatus;
use MediaWiki\Parser\ParserOutput;

/**
 * @covers \MediaWiki\Extension\TemplateData\TemplateDataStatus
 * @license GPL-2.0-or-later
 */
class SerializationTest extends MediaWikiIntegrationTestCase {

	public function testParserOutputPersistenceForwardCompatibility() {
		$output = new ParserOutput();

		$status = StatusValue::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );

		// Set JSONified state. Should work before we set JSON-serializable data,
		// to be robust against old code reading new data after a rollback.
		$output->setExtensionData( 'TemplateDataStatus',
			TemplateDataStatus::jsonSerialize( $status )
		);

		$result = TemplateDataStatus::newFromJson( $output->getExtensionData( 'TemplateDataStatus' ) );
		$this->assertEquals( $status, $result );
		$this->assertSame( (string)$status, (string)$result );
	}

	public function testParserOutputPersistenceBackwardCompatibility() {
		$output = new ParserOutput();

		$status = StatusValue::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );

		// Set the object directly. Should still work once we normally set JSON-serializable data.
		$output->setExtensionData( 'TemplateDataStatus', $status );

		$result = TemplateDataStatus::newFromJson( $output->getExtensionData( 'TemplateDataStatus' ) );
		$this->assertEquals( $status, $result );
		$this->assertSame( (string)$status, (string)$result );
	}

	public static function provideStatus() {
		yield [ StatusValue::newGood() ];
		$status = StatusValue::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );
		yield [ $status ];
	}

	/**
	 * @dataProvider provideStatus
	 */
	public function testParserOutputPersistenceRoundTrip( StatusValue $status ) {
		$parserOutput = new ParserOutput();
		$parserOutput->setExtensionData( 'TemplateDataStatus', TemplateDataStatus::jsonSerialize( $status ) );
		$result = TemplateDataStatus::newFromJson( $parserOutput->getExtensionData( 'TemplateDataStatus' ) );
		$this->assertEquals( $status, $result );
		$this->assertSame( (string)$status, (string)$result );
	}

	/**
	 * @dataProvider provideStatus
	 */
	public function testJsonRoundTrip( StatusValue $status ) {
		$json = TemplateDataStatus::jsonSerialize( $status );
		$result = TemplateDataStatus::newFromJson( $json );
		$this->assertEquals( $status, $result );
		$this->assertSame( (string)$status, (string)$result );
	}

}
