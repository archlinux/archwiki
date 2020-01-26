<?php

use Wikimedia\ScopedCallback;

/**
 * @covers Scribunto_LuaUstringLibrary
 */
class Scribunto_LuaUstringLibraryTest extends Scribunto_LuaEngineUnitTestBase {
	protected static $moduleName = 'UstringLibraryTests';

	private $normalizationDataProvider = null;

	protected function tearDown() {
		if ( $this->normalizationDataProvider ) {
			$this->normalizationDataProvider->destroy();
			$this->normalizationDataProvider = null;
		}
		parent::tearDown();
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'UstringLibraryTests' => __DIR__ . '/UstringLibraryTests.lua',
			'UstringLibraryNormalizationTests' => __DIR__ . '/UstringLibraryNormalizationTests.lua',
		];
	}

	public function testUstringLibraryNormalizationTestsAvailable() {
		if ( UstringLibraryNormalizationTestProvider::available( $err ) ) {
			$this->assertTrue( true );
		} else {
			$this->markTestSkipped( $err );
		}
	}

	public function provideUstringLibraryNormalizationTests() {
		if ( !$this->normalizationDataProvider ) {
			$this->normalizationDataProvider =
				new UstringLibraryNormalizationTestProvider( $this->getEngine() );
		}
		return $this->normalizationDataProvider;
	}

	/**
	 * @dataProvider provideUstringLibraryNormalizationTests
	 */
	public function testUstringLibraryNormalizationTests( $name, $c1, $c2, $c3, $c4, $c5 ) {
		$this->luaTestName = "UstringLibraryNormalization: $name";
		$dataProvider = $this->provideUstringLibraryNormalizationTests();
		$expected = [
			$c2, $c2, $c2, $c4, $c4, // NFC
			$c3, $c3, $c3, $c5, $c5, // NFD
			$c4, $c4, $c4, $c4, $c4, // NFKC
			$c5, $c5, $c5, $c5, $c5, // NFKD
		];
		foreach ( $expected as &$e ) {
			$chars = array_values( unpack( 'N*', mb_convert_encoding( $e, 'UTF-32BE', 'UTF-8' ) ) );
			foreach ( $chars as &$c ) {
				$c = sprintf( "%x", $c );
			}
			$e = "$e\t" . implode( "\t", $chars );
		}
		$actual = $dataProvider->runNorm( $c1, $c2, $c3, $c4, $c5 );
		$this->assertSame( $expected, $actual );
		$this->luaTestName = null;
	}

	/**
	 * @dataProvider providePCREErrors
	 */
	public function testPCREErrors( $ini, $args, $error ) {
		$reset = [];
		foreach ( $ini as $key => $value ) {
			$old = ini_set( $key, $value );
			if ( $old === false ) {
				$this->markTestSkipped( "Failed to set ini setting $key = $value" );
			}
			$reset[] = new ScopedCallback( 'ini_set', [ $key, $old ] );
		}

		$interpreter = $this->getEngine()->getInterpreter();
		$func = $interpreter->loadString( 'return mw.ustring.gsub( ... )', 'fortest' );
		try {
			$interpreter->callFunction( $func, ...$args );
			$this->fail( 'Expected exception not thrown' );
		} catch ( Scribunto_LuaError $e ) {
			$this->assertSame( $error, $e->getMessage() );
		}
	}

	public static function providePCREErrors() {
		return [
			[
				[ 'pcre.backtrack_limit' => 10 ],
				[ 'zzzzzzzzzzzzzzzzzzzz', '^(.-)[abc]*$', '%1' ],
				'Lua error: PCRE backtrack limit reached while matching pattern \'^(.-)[abc]*$\'.'
			],
			// @TODO: Figure out patterns that hit other PCRE limits
		];
	}
}

class UstringLibraryNormalizationTestProvider extends Scribunto_LuaDataProvider {
	protected $file = null;
	protected $current = null;
	protected static $static = [
		'1E0A 0323;1E0C 0307;0044 0323 0307;1E0C 0307;0044 0323 0307;',
		false
	];

	public static function available( &$message = null ) {
		if ( is_readable( __DIR__ . '/NormalizationTest.txt' ) ) {
			return true;
		}
		$message = wordwrap( 'Download the Unicode Normalization Test Suite from ' .
			'http://unicode.org/Public/6.0.0/ucd/NormalizationTest.txt and save as ' .
			__DIR__ . '/NormalizationTest.txt to run normalization tests. Note that ' .
			'running these tests takes quite some time.' );
		return false;
	}

	public function __construct( $engine ) {
		parent::__construct( $engine, 'UstringLibraryNormalizationTests' );
		if ( self::available() ) {
			$this->file = fopen( __DIR__ . '/NormalizationTest.txt', 'r' );
		}
		$this->rewind();
	}

	public function destory() {
		if ( $this->file ) {
			fclose( $this->file );
			$this->file = null;
		}
		parent::destory();
	}

	public function rewind() {
		if ( $this->file ) {
			rewind( $this->file );
		}
		$this->key = 0;
		$this->next();
	}

	public function valid() {
		if ( $this->file ) {
			$v = !feof( $this->file );
		} else {
			$v = $this->key < count( self::$static );
		}
		return $v;
	}

	public function current() {
		return $this->current;
	}

	public function next() {
		$this->current = [ null, null, null, null, null, null ];
		while ( $this->valid() ) {
			if ( $this->file ) {
				$line = fgets( $this->file );
			} else {
				$line = self::$static[$this->key];
			}
			$this->key++;
			if ( preg_match( '/^((?:[0-9A-F ]+;){5})/', $line, $m ) ) {
				$line = rtrim( $m[1], ';' );
				$ret = [ $line ];
				foreach ( explode( ';', $line ) as $field ) {
					$args = [];
					foreach ( explode( ' ', $field ) as $char ) {
						$args[] = hexdec( $char );
					}
					$s = pack( 'N*', ...$args );
					$s = mb_convert_encoding( $s, 'UTF-8', 'UTF-32BE' );
					$ret[] = $s;
				}
				$this->current = $ret;
				return;
			}
		}
	}

	public function runNorm( $c1, $c2, $c3, $c4, $c5 ) {
		return $this->engine->getInterpreter()->callFunction( $this->exports['run'],
			$c1, $c2, $c3, $c4, $c5
		);
	}
}
