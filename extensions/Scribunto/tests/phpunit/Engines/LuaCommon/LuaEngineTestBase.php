<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\Title\Title;
use MediaWikiLangTestCase;

/**
 * This is the subclass for Lua library tests. It will automatically run all
 * tests against LuaSandbox and LuaStandalone.
 *
 * Most of the time, you'll only need to override the following:
 * - $moduleName: Name of the module being tested
 * - getTestModules(): Add a mapping from $moduleName to the file containing
 *   the code.
 */
abstract class LuaEngineTestBase extends MediaWikiLangTestCase {
	use LuaEngineTestHelper;

	/** @var LuaEngine|null */
	private $engine = null;
	/** @var LuaDataProvider|null */
	private $luaDataProvider = null;
	/** @var string|null */
	protected $engineSkipMessage = null;

	/**
	 * Name of the module being tested
	 * @var string
	 */
	protected static $moduleName = null;

	/**
	 * Class to use for the data provider
	 * @var string
	 */
	protected static $dataProviderClass = LuaDataProvider::class;

	/**
	 * Tests to skip. Associative array mapping test name to skip reason.
	 * @var array<string,string>
	 */
	protected array $skipTests = [];

	/**
	 * @return string Engine name ('LuaSandbox' or 'LuaStandalone')
	 */
	protected function getEngineName(): string {
		throw new \LogicException( static::class . ' must implement getEngineName()' );
	}

	protected function setUp(): void {
		parent::setUp();
		// Don't create the engine here. Child classes may need to configure
		// services or settings (e.g. setContentLang) before the engine is created.
	}

	protected function assertPreConditions(): void {
		parent::assertPreConditions();
		if ( $this->engineSkipMessage !== null ) {
			$this->markTestSkipped( $this->engineSkipMessage );
		}
		try {
			$this->getEngine()->getInterpreter();
		} catch ( \Throwable $e ) {
			$this->markTestSkipped( "Engine not available: " . $e->getMessage() );
		}
	}

	protected function tearDown(): void {
		if ( $this->luaDataProvider ) {
			$this->luaDataProvider->destroy();
			$this->luaDataProvider = null;
		}
		if ( $this->engine ) {
			$this->engine->destroy();
			$this->engine = null;
		}
		parent::tearDown();
	}

	/**
	 * Get the title used for unit tests
	 *
	 * @return Title
	 */
	protected function getTestTitle() {
		// XXX This should use a dedicated test page, not the main page
		$t = Title::newMainPage();
		// Force content model to avoid DB queries
		$t->setContentModel( CONTENT_MODEL_WIKITEXT );
		return $t;
	}

	/**
	 * Modules that should exist
	 * @return string[] Mapping module names to files
	 */
	protected function getTestModules() {
		return [
			'TestFramework' => __DIR__ . '/TestFramework.lua',
		];
	}

	public static function provideLuaData(): array {
		try {
			$instance = new static( 'provideLuaData' );
			$engine = $instance->getEngine();
			$engine->getInterpreter();
			$class = static::$dataProviderClass;
			$provider = new $class( $engine, static::$moduleName );
			$data = iterator_to_array( $provider );
			$provider->destroy();
			$engine->destroy();
			return $data;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	protected function getLuaDataProvider(): ?LuaDataProvider {
		if ( !$this->luaDataProvider ) {
			try {
				$this->getEngine()->getInterpreter();
				$class = static::$dataProviderClass;
				$this->luaDataProvider = new $class( $this->getEngine(), static::$moduleName );
			} catch ( \Throwable $e ) {
				return null;
			}
		}
		return $this->luaDataProvider;
	}

	/**
	 * @dataProvider provideLuaData
	 * @param string $key
	 * @param string $testName
	 * @param mixed $expected
	 */
	public function testLua( $key, $testName, $expected ) {
		$msg = $this->getEngineName() . ': ' . static::$moduleName . "[$key]: $testName";
		if ( isset( $this->skipTests[$testName] ) ) {
			$this->markTestSkipped( $this->skipTests[$testName] );
		} else {
			$provider = $this->getLuaDataProvider();
			if ( !$provider ) {
				$this->markTestSkipped( 'Lua data provider not available' );
			}
			try {
				$actual = $provider->run( $key );
			} catch ( LuaError $ex ) {
				if ( str_starts_with( $ex->getLuaMessage(), 'SKIP: ' ) ) {
					$this->markTestSkipped( substr( $ex->getLuaMessage(), 6 ) );
				}
				throw $ex;
			}
			$this->assertSame( $expected, $actual, $msg );
		}
	}
}
