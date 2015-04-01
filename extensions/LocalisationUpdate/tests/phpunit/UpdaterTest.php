<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

class LU_UpdaterTest extends MediaWikiTestCase {
	public function testIsDirectory() {
		$updater = new LU_Updater();

		$this->assertTrue(
			$updater->isDirectory( '/IP/extensions/Translate/i18n/*.json' ),
			'Extension json files are a file pattern'
		);

		$this->assertFalse(
			$updater->isDirectory( '/IP/extensions/Translate/Translate.i18n.php' ),
			'Extension php file is not a pattern'
		);
	}

	public function testExpandRemotePath() {
		$updater = new LU_Updater();
		$repos = array( 'main' => 'file:///repos/%NAME%/%SOME-VAR%' );

		$info = array(
			'repo' => 'main',
			'name' => 'product',
			'some-var' => 'file',
		);
		$this->assertEquals(
			'file:///repos/product/file',
			$updater->expandRemotePath( $info, $repos ),
			'Variables are expanded correctly'
		);
	}

	public function testReadMessages() {
		$updater = $updater = new LU_Updater();

		$input = array( 'file' => 'Hello World!' );
		$output = array( 'en' => array( 'key' => $input['file'] ) );

		$reader = $this->getMock( 'LU_Reader' );
		$reader
			->expects( $this->once() )
			->method( 'parse' )
			->will( $this->returnValue( $output ) );

		$factory = $this->getMock( 'LU_ReaderFactory' );
		$factory
			->expects( $this->once() )
			->method( 'getReader' )
			->will( $this->returnValue( $reader ) );

		$observed = $updater->readMessages( $factory, $input );
		$this->assertEquals( $output, $observed, 'Tries to parse given file' );
	}

	public function testFindChangedTranslations() {
		$updater = $updater = new LU_Updater();

		$origin = array(
			'A' => '1',
			'C' => '3',
			'D' => '4',
		);
		$remote = array(
			'A' => '1', // No change key
			'B' => '2', // New key
			'C' => '33', // Changed key
			'D' => '44', // Blacklisted key
		);
		$blacklist = array( 'D' => 0 );
		$expected = array( 'B' => '2', 'C' => '33' );
		$observed = $updater->findChangedTranslations( $origin, $remote, $blacklist );
		$this->assertEquals( $expected, $observed, 'Changed and new keys returned' );
	}
}
