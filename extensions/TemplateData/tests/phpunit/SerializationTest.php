<?php

/**
 * @group TemplateData
 * @covers \TemplateDataHooks::setStatusToParserOutput
 * @covers \TemplateDataHooks::getStatusFromParserOutput
 */
class SerializationTest extends MediaWikiTestCase {
	public function testParserOutputPersistenceForwardCompatibility() {
		$output = new ParserOutput();

		$status = Status::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );

		// Set JSONified state. Should work before we set JSON-serializable data,
		// to be robust against old code reading new data after a rollback.
		$output->setExtensionData( 'TemplateDataStatus',
			TemplateDataHooks::jsonSerializeStatus( $status ) );

		$result = TemplateDataHooks::getStatusFromParserOutput( $output );
		$this->assertEquals( $status->getStatusValue(), $result->getStatusValue() );
		$this->assertEquals( $status->__toString(), $result->__toString() );
	}

	public function testParserOutputPersistenceBackwardCompatibility() {
		$output = new ParserOutput();

		$status = Status::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );

		// Set the object directly. Should still work once we normally set JSON-serializable data.
		$output->setExtensionData( 'TemplateDataStatus', $status );

		$result = TemplateDataHooks::getStatusFromParserOutput( $output );
		$this->assertEquals( $status->getStatusValue(), $result->getStatusValue() );
		$this->assertEquals( $status->__toString(), $result->__toString() );
	}

	public function provideStatus() {
		yield [ Status::newGood() ];
		$status = Status::newFatal( 'a', 'b', 'c' );
		$status->fatal( 'f' );
		$status->warning( 'd', 'e' );
		yield [ $status ];
	}

	/**
	 * @dataProvider provideStatus
	 * @covers \TemplateDataHooks::setStatusToParserOutput
	 * @covers \TemplateDataHooks::getStatusFromParserOutput
	 */
	public function testParserOutputPersistenceRoundTrip( Status $status ) {
		$parserOutput = new ParserOutput();
		TemplateDataHooks::setStatusToParserOutput( $parserOutput, $status );
		$result = TemplateDataHooks::getStatusFromParserOutput( $parserOutput );
		$this->assertEquals( $status->getStatusValue(), $result->getStatusValue() );
		$this->assertEquals( $status->__toString(), $result->__toString() );
	}

	/**
	 * @dataProvider provideStatus
	 * @covers \TemplateDataHooks::jsonSerializeStatus
	 * @covers \TemplateDataHooks::newStatusFromJson
	 */
	public function testJsonRoundTrip( Status $status ) {
		$json = TemplateDataHooks::jsonSerializeStatus( $status );
		$result = TemplateDataHooks::newStatusFromJson( $json );
		$this->assertEquals( $status->getStatusValue(), $result->getStatusValue() );
		$this->assertEquals( $status->__toString(), $result->__toString() );
	}
}
