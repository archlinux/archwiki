<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

class LU_FinderTest extends MediaWikiTestCase {
	public function testGetComponents() {
		$finder = new LU_Finder(
			array(
				'TranslateSearch' => '/IP/extensions/Translate/TranslateSearch.i18n.php',
				'Babel' => '/IP/extensions/Babel/Babel.i18n.php',
			),
			array(
				'Babel' => '/IP/extensions/Babel/i18n',
				'Door' => array(
					'core' => '/IP/extensions/Door/i18n/core',
					'extra' => '/IP/extensions/Door/i18n/extra',
				),
			),
			'/IP'
		);
		$observed = $finder->getComponents();

		$expected = array(
			'repo' => 'mediawiki',
			'orig' => "file:///IP/languages/messages/Messages*.php",
			'path' => 'languages/messages/Messages*.php',
		);
		$this->assertArrayHasKey( 'core', $observed );
		$this->assertSame( $expected, $observed['core'], 'Core php file' );

		$expected = array(
			'repo' => 'extension',
			'name' => 'Translate',
			'orig' => 'file:///IP/extensions/Translate/TranslateSearch.i18n.php',
			'path' => 'TranslateSearch.i18n.php'
		);
		$this->assertArrayHasKey( 'TranslateSearch', $observed );
		$this->assertSame( $expected, $observed['TranslateSearch'], 'PHP only extension' );

		$expected = array(
			'repo' => 'extension',
			'name' => 'Babel',
			'orig' => 'file:///IP/extensions/Babel/i18n/*.json',
			'path' => 'i18n/*.json'
		);
		$this->assertArrayHasKey( 'Babel-0', $observed );
		$this->assertSame( $expected, $observed['Babel-0'], 'PHP&JSON extension' );

		$expected = array(
			'repo' => 'extension',
			'name' => 'Door',
			'orig' => 'file:///IP/extensions/Door/i18n/core/*.json',
			'path' => 'i18n/core/*.json'
		);
		$this->assertArrayHasKey( 'Door-core', $observed );
		$this->assertSame( $expected, $observed['Door-core'], 'Multidir json extension' );

		$expected = array(
			'repo' => 'extension',
			'name' => 'Door',
			'orig' => 'file:///IP/extensions/Door/i18n/extra/*.json',
			'path' => 'i18n/extra/*.json'
		);
		$this->assertArrayHasKey( 'Door-extra', $observed );
		$this->assertSame( $expected, $observed['Door-extra'], 'Multidir json extension' );
	}
}
