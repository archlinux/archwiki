<?php

namespace MediaWiki\Tests\ResourceLoader;

use InvalidArgumentException;
use MediaWiki\ResourceLoader\CodexModule;
use ResourceLoaderTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group ResourceLoader
 * @covers \MediaWiki\ResourceLoader\CodexModule
 */
class CodexModuleTest extends ResourceLoaderTestCase {

	public const FIXTURE_PATH = 'tests/phpunit/data/resourceloader/codexModules/';

	public static function provideModuleConfig() {
		return [
			[ 'Codex subset',
				[
					'codexComponents' => [ 'CdxButton', 'CdxMessage', 'useModelWrapper' ],
					'codexStyleOnly' => false,
					'codexScriptOnly' => false
				],
				[
					'packageFiles' => [
						'codex.js',
						'_codex/constants.js',
						'_codex/useSlotContents2.js',
						'_codex/useWarnOnce.js',
						'_codex/useIconOnlyButton.js',
						'_codex/_plugin-vue_export-helper.js',
						'_codex/CdxButton.js',
						'_codex/useComputedDirection.js',
						'_codex/useComputedLanguage.js',
						'_codex/Icon.js',
						'_codex/CdxMessage.js',
						'_codex/useModelWrapper.js'
					],
					'styles' => [ 'CdxButton.css', 'CdxIcon.css', 'CdxMessage.css' ]
				]
			],
			[ 'Codex subset, style only',
				[
					'codexComponents' => [ 'CdxButton', 'CdxMessage' ],
					'codexStyleOnly' => true,
					'codexScriptOnly' => false
				],
				[
					'packageFiles' => [],
					'styles' => [ 'CdxButton.css', 'CdxIcon.css', 'CdxMessage.css' ]
				]
			],
			[ 'Codex subset, script only',
				[
					'codexComponents' => [ 'CdxButton', 'CdxMessage', 'useModelWrapper' ],
					'codexStyleOnly' => false,
					'codexScriptOnly' => true
				],
				[
					'packageFiles' => [
						'codex.js',
						'_codex/constants.js',
						'_codex/useSlotContents2.js',
						'_codex/useWarnOnce.js',
						'_codex/useIconOnlyButton.js',
						'_codex/_plugin-vue_export-helper.js',
						'_codex/CdxButton.js',
						'_codex/useComputedDirection.js',
						'_codex/useComputedLanguage.js',
						'_codex/Icon.js',
						'_codex/CdxMessage.js',
						'_codex/useModelWrapper.js'
					],
					'styles' => []
				]
			],
			[ 'Exception thrown when a chunk is requested',
				[
					'codexComponents' => [ 'CdxButton', 'buttonHelpers' ],
				],
				[
					'exception' => [
						'class' => InvalidArgumentException::class,
						'message' => '"buttonHelpers" is not an export of Codex and cannot be included in the "codexComponents" array.'
					]
				]
			],
			[ 'Exception thrown when a nonexistent file is requested',
				[
					'codexComponents' => [ 'CdxButton', 'blahblahidontexistblah' ],
				],
				[
					'exception' => [
						'class' => InvalidArgumentException::class,
						'message' => '"blahblahidontexistblah" is not an export of Codex and cannot be included in the "codexComponents" array.'
					]
				]
			],
			[ 'Exception thrown when codexComponents is empty in the module definition',
				[
					'codexComponents' => []
				],
				[
					'exception' => [
						'class' => InvalidArgumentException::class,
						'message' => "All 'codexComponents' properties in your module definition file " .
						'must either be omitted or be an array with at least one component name'
					]
				]
			],
			[ 'Exception thrown when codexComponents is not an array in the module definition',
				[
					'codexComponents' => ''
				],
				[
					'exception' => [
						'class' => InvalidArgumentException::class,
						'message' => "All 'codexComponents' properties in your module definition file " .
						'must either be omitted or be an array with at least one component name'
					]
				]
			],
			[ 'Exception thrown when the @wikimedia/codex module is required',
				[
					'codexComponents' => [ 'CdxButton', 'buttonHelpers' ],
					'dependencies' => [ '@wikimedia/codex' ]
				],
				[
					'exception' => [
						'class' => InvalidArgumentException::class,
						'message' => 'ResourceLoader modules using the CodexModule class cannot ' .
							"list the '@wikimedia/codex' module as a dependency. " .
							"Instead, use 'codexComponents' to require a subset of components."
					]
				]
			],
		];
	}

	/**
	 * @dataProvider provideModuleConfig
	 */
	public function testCodexSubset( $testCase, $moduleDefinition, $expected ) {
		if ( isset( $expected['exception'] ) ) {
			$this->expectException( $expected['exception']['class'] );
			$this->expectExceptionMessage( $expected['exception']['message'] );
		}

		$testModule = new class( $moduleDefinition ) extends CodexModule {
			public const CODEX_MODULE_DIR = CodexModuleTest::FIXTURE_PATH;
		};

		$context = $this->getResourceLoaderContext();
		$config = $context->getResourceLoader()->getConfig();
		$testModule->setConfig( $config );

		$packageFiles = $testModule->getPackageFiles( $context );
		$styleFiles = $testModule->getStyleFiles( $context );

		// Style-only module will not have any packageFiles.
		$packageFilenames = isset( $packageFiles ) ? array_keys( $packageFiles[ 'files' ] ) : [];
		$this->assertEquals( $expected[ 'packageFiles' ] ?? [], $packageFilenames, 'Correct packageFiles added for ' . $testCase );

		// Script-only module will not have any styleFiles.
		$styleFilenames = [];
		if ( count( $styleFiles ) > 0 ) {
			$styleFilenames = array_map( static function ( $filepath ) use ( $testModule ) {
				return str_replace( $testModule::CODEX_MODULE_DIR, '', $filepath->getPath() );
			}, $styleFiles[ 'all' ] );
		}
		$this->assertEquals( $expected[ 'styles' ] ?? [], $styleFilenames, 'Correct styleFiles added for ' . $testCase );
	}

	public function testMissingCodexComponentsDefinition() {
		$moduleDefinition = [
			'codexComponents' => [ 'CdxButton', 'CdxMessage' ]
		];

		$testModule = new class( $moduleDefinition ) extends CodexModule {
			public const CODEX_MODULE_DIR = CodexModuleTest::FIXTURE_PATH;
		};

		$context = $this->getResourceLoaderContext();
		$config = $context->getResourceLoader()->getConfig();
		$testModule->setConfig( $config );

		$packageFiles = $testModule->getPackageFiles( $context );

		$codexPackageFileContent = $packageFiles[ 'files' ][ 'codex.js' ][ 'content' ];
		$expectedProxiedExports = '{"CdxButton":require( "./_codex/CdxButton.js" ),'
			. '"CdxMessage":require( "./_codex/CdxMessage.js" )}';

		// Components defined in the 'codexComponents' array should be proxied in the codex.js
		// package file so that missing components will throw a custom error when required.
		// By asserting what components are proxied, we are indirectly asserting that missing
		// components would throw an error when required.
		$this->assertStringContainsString( $expectedProxiedExports, $codexPackageFileContent );
	}

	public function testGetManifestFile() {
		$moduleDefinition = [ 'codexComponents' => [ 'CdxButton', 'CdxMessage' ] ];
		$testModule = new class( $moduleDefinition ) extends CodexModule {
			public const CODEX_MODULE_DIR = CodexModuleTest::FIXTURE_PATH;
		};

		$context = $this->getResourceLoaderContext();
		$testWrapper = TestingAccessWrapper::newFromObject( $testModule );

		// By default, look for a manifest file called "manifest.json"
		$this->assertEquals(
			MW_INSTALL_PATH . '/' . self::FIXTURE_PATH . 'manifest.json',
			$testWrapper->getManifestFilePath( $context )
		);
	}

	/**
	 * Test that the manifest data structure is transformed correctly.
	 * This test relies on the fixture manifest data that lives in
	 * tests/phpunit/data/resourceloader/codexModules
	 */
	public function testGetCodexFiles() {
		$moduleDefinition = [ 'codexComponents' => [ 'CdxButton', 'CdxMessage' ] ];
		$testModule = new class( $moduleDefinition ) extends CodexModule {
			public const CODEX_MODULE_DIR = CodexModuleTest::FIXTURE_PATH;
		};

		$context = $this->getResourceLoaderContext();
		$testWrapper = TestingAccessWrapper::newFromObject( $testModule );
		$codexFiles = $testWrapper->getCodexFiles( $context );

		// The transformed data structure should have a "files" and a "components" array.
		$this->assertIsArray( $codexFiles );
		$this->assertArrayHasKey( 'files', $codexFiles );
		$this->assertArrayHasKey( 'components', $codexFiles );

		// The "components" array should contain keys like "CdxButton"
		// with values like "CdxButton.js" (matching the names in the manifest)
		$this->assertArrayHasKey( 'CdxButton', $codexFiles[ 'components' ] );
		$this->assertEquals( 'CdxButton.js', $codexFiles[ 'components' ][ 'CdxButton' ] );

		// The "files" array should contains keys like "CdxButton.js"
		// Items in this array are themselves arrays with "styles" and "dependencies" keys.
		$this->assertArrayHasKey( 'CdxButton.js', $codexFiles[ 'files' ] );
		$this->assertArrayHasKey( 'styles', $codexFiles[ 'files' ][ 'CdxButton.js' ] );
		$this->assertArrayHasKey( 'dependencies', $codexFiles[ 'files' ][ 'CdxButton.js' ] );
	}
}
