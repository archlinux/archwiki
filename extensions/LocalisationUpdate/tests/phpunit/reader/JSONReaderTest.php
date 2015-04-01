<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

class LU_JSONReaderTest extends MediaWikiTestCase {
	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( $input, $expected, $comment ) {
		$reader = new LU_JSONReader( 'xx' );
		$observed = $reader->parse( $input );
		$this->assertEquals( $expected, $observed['xx'], $comment );
	}

	public function parseProvider() {
		return array(
			array(
				'{}',
				array(),
				'empty file',
			),
			array(
				'{"key":"value"}',
				array( 'key' => 'value' ),
				'file with one string',
			),
			array(
				'{"@metadata":{"authors":["Nike"]},"key":"value2"}',
				array( 'key' => 'value2' ),
				'@metadata is ignored',
			)
		);
	}
}
