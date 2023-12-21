<?php

use PHPUnit\Framework\TestSuite;
use SebastianBergmann\FileIterator\Facade;

/**
 * Test suite that runs extensions unit tests (the `extensions:unit` suite).
 */
class ExtensionsUnitTestSuite extends TestSuite {
	public function __construct() {
		parent::__construct();

		if ( !defined( 'MW_PHPUNIT_EXTENSIONS_PATHS' ) ) {
			throw new RuntimeException( 'The PHPUnit bootstrap was not loaded' );
		}
		$paths = [];
		foreach ( MW_PHPUNIT_EXTENSIONS_PATHS as $path ) {
			// Note that we don't load settings, so we expect to find extensions in their
			// default location
			// Standardize directory separators for Windows compatibility.
			if ( str_contains( strtr( $path, '\\', '/' ), '/extensions/' ) ) {
				$paths[] = "$path/tests/phpunit/unit";
			}
		}
		foreach ( array_unique( $paths ) as $path ) {
			$suffixes = [ 'Test.php' ];
			$fileIterator = new Facade();
			$matchingFiles = $fileIterator->getFilesAsArray( $path, $suffixes );
			$this->addTestFiles( $matchingFiles );
		}
	}

	public static function suite() {
		return new self;
	}
}
