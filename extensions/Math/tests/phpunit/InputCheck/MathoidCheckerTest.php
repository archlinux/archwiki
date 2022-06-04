<?php

namespace MediaWiki\Extension\Math\InputCheck;

use HashBagOStuff;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use WANObjectCache;

class MathoidCheckerTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	private const SAMPLE_KEY = 'global:MediaWiki\Extension\Math\InputCheck\MathoidChecker:' .
	'eb27aefff6e58e58dcefa22102531a58';

	public function provideTexExamples() {
		return [
			[ '\sin x', 'eb27aefff6e58e58dcefa22102531a58' ],
			[ '\sin_x', '7b33c69d6eac9126d41b663b93896951' ],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getCacheKey
	 * @dataProvider provideTexExamples
	 */
	public function testCacheKey( string $input, string $expected ) {
		$checker = $this->getMathoidChecker( $input );
		$realKey = $checker->getCacheKey();
		$this->assertStringEndsWith( $expected, $realKey );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getCheckResponse
	 */
	public function testResponseFromCache() {
		$fakeWAN = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$fakeWAN->set( self::SAMPLE_KEY,
			[ 999, 'expected' ],
			WANObjectCache::TTL_INDEFINITE,
			[ 'version' => MathoidChecker::VERSION ] );
		// double check that the fake works
		$this->assertSame( [ 999, 'expected' ], $fakeWAN->get( self::SAMPLE_KEY ) );
		$this->setService( 'MainWANObjectCache', $fakeWAN );
		$checker = $this->getMathoidChecker();
		$this->assertSame( [ 999, 'expected' ], $checker->getCheckResponse() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getCheckResponse
	 */
	public function testResponseFromResponse() {
		$fakeWAN = WANObjectCache::newEmpty();
		$fakeWAN->set( self::SAMPLE_KEY, 'expected' );
		// double check that the fake does not works
		$this->assertSame( false, $fakeWAN->get( self::SAMPLE_KEY ) );
		$this->setService( 'MainWANObjectCache', $fakeWAN );
		$this->setFakeRequest( 200, 'expected' );
		$checker = $this->getMathoidChecker();
		$this->assertSame( [ 200, 'expected' ], $checker->getCheckResponse() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getCheckResponse
	 */
	public function testFailedResponse() {
		$fakeWAN = WANObjectCache::newEmpty();
		$fakeWAN->set( self::SAMPLE_KEY, 'expected' );
		// double check that the fake does not works
		$this->assertSame( false, $fakeWAN->get( self::SAMPLE_KEY ) );
		$this->setService( 'MainWANObjectCache', $fakeWAN );
		$this->setFakeRequest( 401, false );
		$checker = $this->getMathoidChecker();
		$this->expectException( 'MWException' );
		$checker->getCheckResponse();
	}

	/**
	 * @covers       \MediaWiki\Extension\Math\InputCheck\MathoidChecker::isValid
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param HttpRequestFactory $request fake mathoid response
	 * @param array $expeted
	 */
	public function testIsValid( $input, $request, $expeted ) {
		$this->installMockHttp( $request );
		$checker = $this->getMathoidChecker( $input );
		$this->assertSame( $expeted['valid'], $checker->isValid() );
	}

	/**
	 * @covers       \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getValidTex
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param HttpRequestFactory $request fake mathoid response
	 * @param array $expeted
	 */
	public function testGetChecked( $input, $request, $expeted ) {
		$this->installMockHttp( $request );
		$checker = $this->getMathoidChecker( $input );
		$this->assertSame( $expeted['checked'], $checker->getValidTex() );
	}

	/**
	 * @covers       \MediaWiki\Extension\Math\InputCheck\MathoidChecker::getError
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param HttpRequestFactory $request fake mathoid response
	 * @param array $expeted
	 */
	public function testGetError( $input, $request, $expeted ) {
		$this->installMockHttp( $request );
		$checker = $this->getMathoidChecker( $input );
		if ( array_key_exists( 'error', $expeted ) ) {
			$checkerError = $checker->getError();
			$this->assertNotNull( $checkerError );
			$renderedCheckerError = ( new MathMathML( 'a' ) )
				->getError( $checkerError->getKey(), ...$checkerError->getParams() );
			$this->assertStringContainsString( $expeted['error'], $renderedCheckerError );
		} else {
			$this->assertNull( $checker->getError() );
		}
	}

	/**
	 * @param string $tex
	 * @return MathoidChecker
	 */
	private function getMathoidChecker( $tex = '\sin x' ): MathoidChecker {
		return MediaWikiServices::getInstance()
			->getService( 'Math.CheckerFactory' )
			->newMathoidChecker( $tex, 'tex' );
	}

	private function setFakeRequest( $returnStatus, $content ): void {
		$fakeHTTP = $this->createMock( HttpRequestFactory::class );
		$fakeRequest = $this->createMock( \MWHttpRequest::class );
		$fakeRequest->expects( $this->once() )->method( 'execute' )->willReturn( true );
		$fakeRequest->expects( $this->once() )->method( 'getStatus' )->willReturn( $returnStatus );
		if ( $content ) {
			$fakeRequest->expects( $this->once() )->method( 'getContent' )->willReturn( $content );
		} else {
			$fakeRequest->expects( $this->never() )->method( 'getContent' );
		}
		$fakeHTTP->expects( $this->once() )->method( 'create' )->willReturn( $fakeRequest );
		$this->setService( 'HttpRequestFactory', $fakeHTTP );
	}

	public function provideMathoidSamples() {
		yield '\ sin x' => [
			'\sin x',
			$this->makeFakeHttpRequest( file_get_contents( __DIR__ . '/data/mathoid/sinx.json' ), 200 ),
			[ 'valid' => true, 'checked' => '\sin x' ],
		];
		yield 'invalid F' => [
			'1+\invalid',
			$this->makeFakeHttpRequest( file_get_contents( __DIR__ . '/data/mathoid/invalidF.json' ), 400 ),
			[ 'valid' => false, 'checked' => null, 'error' => 'unknown function' ],
		];
		yield 'unescaped' => [
			'1.5%',
			$this->makeFakeHttpRequest( file_get_contents( __DIR__ . '/data/mathoid/deprecated.json' ),
				200 ),
			[ 'valid' => true, 'checked' => '1.5\%' ],
		];
		yield 'syntax error' => [
			'\left( x',
			$this->makeFakeHttpRequest( file_get_contents( __DIR__ . '/data/mathoid/syntaxE.json' ), 400 ),
			[ 'valid' => false, 'checked' => null, 'error' => 'Failed to parse' ],
		];
	}

}
