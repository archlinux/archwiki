<?php

/**
 * This is the subclass for Lua library tests. It will automatically run all
 * tests against LuaSandbox and LuaStandalone.
 *
 * Most of the time, you'll only need to override the following:
 * - $moduleName: Name of the module being tested
 * - getTestModules(): Add a mapping from $moduleName to the file containing
 *   the code.
 */
abstract class Scribunto_LuaEngineTestBase extends MediaWikiLangTestCase {
	use Scribunto_LuaEngineTestHelper;

	private static $staticEngineName = null;
	private $engineName = null;
	private $engine = null;
	private $luaDataProvider = null;

	/**
	 * Name to display instead of the default
	 * @var string
	 */
	protected $luaTestName = null;

	/**
	 * Name of the module being tested
	 * @var string
	 */
	protected static $moduleName = null;

	/**
	 * Class to use for the data provider
	 * @var string
	 */
	protected static $dataProviderClass = 'Scribunto_LuaDataProvider';

	/**
	 * Tests to skip. Associative array mapping test name to skip reason.
	 * @var array
	 */
	protected $skipTests = [];

	public function __construct(
		$name = null, array $data = [], $dataName = '', $engineName = null
	) {
		if ( $engineName === null ) {
			$engineName = self::$staticEngineName;
		}
		$this->engineName = $engineName;
		parent::__construct( $name, $data, $dataName );
	}

	public static function suite( $className ) {
		return self::makeSuite( $className );
	}

	protected function tearDown() {
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
		return Title::newMainPage();
	}

	public function toString() {
		// When running tests written in Lua, return a nicer representation in
		// the failure message.
		if ( $this->luaTestName ) {
			return $this->engineName . ': ' . $this->luaTestName;
		}
		return $this->engineName . ': ' . parent::toString();
	}

	protected function getTestModules() {
		return [
			'TestFramework' => __DIR__ . '/TestFramework.lua',
		];
	}

	public function provideLuaData() {
		if ( !$this->luaDataProvider ) {
			$class = static::$dataProviderClass;
			$this->luaDataProvider = new $class ( $this->getEngine(), static::$moduleName );
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
		$this->luaTestName = static::$moduleName . "[$key]: $testName";
		if ( isset( $this->skipTests[$testName] ) ) {
			$this->markTestSkipped( $this->skipTests[$testName] );
		} else {
			try {
				$actual = $this->provideLuaData()->run( $key );
			} catch ( Scribunto_LuaError $ex ) {
				if ( substr( $ex->getLuaMessage(), 0, 6 ) === 'SKIP: ' ) {
					$this->markTestSkipped( substr( $ex->getLuaMessage(), 6 ) );
				} else {
					throw $ex;
				}
			}
			$this->assertSame( $expected, $actual );
		}
		$this->luaTestName = null;
	}
}

class Scribunto_LuaEngineTestSkip extends PHPUnit\Framework\TestCase {
	private $className = '';
	private $message = '';

	public function __construct( $className = '', $message = '' ) {
		$this->className = $className;
		$this->message = $message;
		parent::__construct( 'testDummy' );
	}

	public function testDummy() {
		if ( $this->className ) {
			$this->markTestSkipped( $this->message );
		} else {
			// Dummy
			$this->assertTrue( true );
		}
	}

	public function toString() {
		return $this->className;
	}
}
