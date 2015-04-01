<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

class LU_ReaderFactoryTest extends MediaWikiTestCase {
	/**
	 * @dataProvider getReaderProvider
	 */
	public function testGetReader( $input, $expected, $comment ) {
		$factory = new LU_ReaderFactory();
		$reader = $factory->getReader( $input );
		$observed = get_class( $reader );
		$this->assertEquals( $expected, $observed, $comment );
	}

	public function getReaderProvider() {
		return array(
			array(
				'languages/messages/MessagesFi.php',
				'LU_PHPReader',
				'core php file',
			),
			array(
				'extensions/Translate/Translate.i18n.php',
				'LU_PHPReader',
				'extension php file',
			),
			array(
				'extension/Translate/i18n/core/de.json',
				'LU_JSONReader',
				'extension json file',
			),
		);
	}
}
