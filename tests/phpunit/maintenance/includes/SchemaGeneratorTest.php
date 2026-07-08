<?php

namespace MediaWiki\Tests\Maintenance\Includes;

use MediaWiki\DB\AbstractSchemaValidationError;
use MediaWiki\Maintenance\SchemaGenerator;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Maintenance\SchemaGenerator
 */
class SchemaGeneratorTest extends MediaWikiIntegrationTestCase {

	private const DATA_DIR = __DIR__ . '/../../data/schema-maintenance';

	private function getFileWithContent( string $content ): string {
		$testFilename = $this->getNewTempFile();
		$testFile = fopen( $testFilename, 'w' );
		fwrite( $testFile, $content );
		fclose( $testFile );
		return $testFilename;
	}

	/**
	 * @dataProvider provideValidateAndGetSchema
	 */
	public function testValidateAndGetSchema( string $fileContent, string $expectedExceptionSubstring ) {
		$this->expectException( AbstractSchemaValidationError::class );
		$this->expectExceptionMessage( $expectedExceptionSubstring );
		( new SchemaGenerator() )->validateAndGetSchema( $this->getFileWithContent( $fileContent ) );
	}

	public static function provideValidateAndGetSchema() {
		return [
			'Empty JSON file' => [ '', 'does not exist' ],
			'Not valid JSON' => [ '{{{{', 'Invalid JSON' ],
			'Not a valid schema' => [ '{"abc": "test"}', 'did not pass validation' ],
		];
	}

	public function testValidateAndGetSchema__valid() {
		$generator = new SchemaGenerator();
		$schema = $generator->validateAndGetSchema( self::DATA_DIR . '/tables.json' );
		$this->assertIsArray( $schema );
	}

	public function testGenerateSchemaChange__NoSchemaChangesMade() {
		$generator = new SchemaGenerator();
		$this->expectException( AbstractSchemaValidationError::class );
		$this->expectExceptionMessage( 'No schema changes detected' );
		$generator->generateSchemaChange( 'mysql', self::DATA_DIR . '/patch-no_change.json' );
	}

	/**
	 * @dataProvider provideJsonSchemasPaths
	 */
	public function testNormalizePath(
		string $expected,
		string $jsonSchema,
		string $installPath,
		string $extensionDirectory
	) {
		$this->assertEquals(
			$expected,
			SchemaGenerator::normalizePath( $jsonSchema, $installPath, $extensionDirectory )
		);
	}

	public static function provideJsonSchemasPaths() {
		// ($expected, $jsonSchema, $installPath, $extensionDirectory)
		return [
			'Core patch under core root' => [
				'tests/phpunit/data/schema-maintenance/patch-no_change.json',
				self::DATA_DIR . '/patch-no_change.json',
				MW_INSTALL_PATH,
				MW_INSTALL_PATH . '/extensions'
			],
			'Extension patch with default wgExtensionDirectory under core root' => [
				'db_patches/patch.json',
				self::DATA_DIR . '/extensions/FooExt/db_patches/patch.json',
				self::DATA_DIR, // used as MW_INSTALL_PATH
				self::DATA_DIR . '/extensions'
			],
			'Extension patch with custom wgExtensionDirectory outside core root' => [
				'db_patches/patch.json',
				self::DATA_DIR . '/extensions/FooExt/db_patches/patch.json',
				MW_INSTALL_PATH . '/includes', // not a parent of self::DATA_DIR
				self::DATA_DIR . '/extensions'
			],
		];
	}

}
