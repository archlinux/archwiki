<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use Wikimedia\ScopedCallback;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UstringLibrary
 */
abstract class UstringLibraryTestBase extends LuaEngineUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'UstringLibraryTests';

	/** @var UstringLibraryNormalizationTestProvider|null */
	private $normalizationDataProvider = null;

	protected function tearDown(): void {
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

	public static function provideUstringLibraryNormalizationTests(): array {
		try {
			$instance = new static( 'provideUstringLibraryNormalizationTests' );
			$engine = $instance->getEngine();
			$engine->getInterpreter();
			$provider = new UstringLibraryNormalizationTestProvider( $engine );
			$data = iterator_to_array( $provider );
			$provider->destroy();
			$engine->destroy();
			return $data;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private function getNormalizationDataProvider(): ?UstringLibraryNormalizationTestProvider {
		if ( !$this->normalizationDataProvider ) {
			try {
				$this->getEngine()->getInterpreter();
				$this->normalizationDataProvider =
					new UstringLibraryNormalizationTestProvider( $this->getEngine() );
			} catch ( \Throwable $e ) {
				return null;
			}
		}
		return $this->normalizationDataProvider;
	}

	/**
	 * @dataProvider provideUstringLibraryNormalizationTests
	 */
	public function testUstringLibraryNormalizationTests( $name, $c1, $c2, $c3, $c4, $c5 ) {
		$msg = "UstringLibraryNormalization: $name";
		$dataProvider = $this->getNormalizationDataProvider();
		if ( !$dataProvider ) {
			$this->markTestSkipped( 'Normalization data provider not available' );
		}
		$expected = [
			// NFC
			$c2, $c2, $c2, $c4, $c4,
			// NFD
			$c3, $c3, $c3, $c5, $c5,
			// NFKC
			$c4, $c4, $c4, $c4, $c4,
			// NFKD
			$c5, $c5, $c5, $c5, $c5,
		];
		foreach ( $expected as &$e ) {
			$chars = array_values( unpack( 'N*', mb_convert_encoding( $e, 'UTF-32BE', 'UTF-8' ) ) );
			foreach ( $chars as &$c ) {
				$c = sprintf( "%x", $c );
			}
			$e = "$e\t" . implode( "\t", $chars );
		}
		$actual = $dataProvider->runNorm( $c1, $c2, $c3, $c4, $c5 );
		$this->assertSame( $expected, $actual, $msg );
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
		} catch ( LuaError $e ) {
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
