<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

namespace LocalisationUpdate;

class ReaderFactoryTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider getReaderProvider
	 */
	public function testGetReader( $input, $expected, $comment ) {
		$factory = new ReaderFactory();
		$reader = $factory->getReader( $input );
		$observed = get_class( $reader );
		$this->assertEquals( $expected, $observed, $comment );
	}

	public function getReaderProvider() {
		return array(
			array(
				'languages/messages/MessagesFi.php',
				'LocalisationUpdate\PHPReader',
				'core php file',
			),
			array(
				'extensions/Translate/Translate.i18n.php',
				'LocalisationUpdate\PHPReader',
				'extension php file',
			),
			array(
				'extension/Translate/i18n/core/de.json',
				'LocalisationUpdate\JSONReader',
				'extension json file',
			),
		);
	}
}
