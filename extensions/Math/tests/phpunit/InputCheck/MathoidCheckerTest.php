<?php

namespace MediaWiki\Extension\Math\InputCheck;

use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use RuntimeException;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \MediaWiki\Extension\Math\InputCheck\MathoidChecker
 */
class MathoidCheckerTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	private const SAMPLE_KEY = 'global:MediaWiki\Extension\Math\InputCheck\MathoidChecker:' .
	'eb27aefff6e58e58dcefa22102531a58';

	public static function provideTexExamples() {
		return [
			[ '\sin x', 'eb27aefff6e58e58dcefa22102531a58' ],
			[ '\sin_x', '7b33c69d6eac9126d41b663b93896951' ],
		];
	}

	/**
	 * @dataProvider provideTexExamples
	 */
	public function testCacheKey( string $input, string $expected ) {
		$checker = $this->getMathoidChecker( $input );
		$realKey = $checker->getCacheKey();
		$this->assertStringEndsWith( $expected, $realKey );
	}

	public function testResponseFromCache() {
		$wanCache = $this->getServiceContainer()->getMainWANObjectCache();
		$wanCache->set( self::SAMPLE_KEY,
			[ 999, 'expected' ],
			WANObjectCache::TTL_INDEFINITE,
			[ 'version' => MathoidChecker::VERSION ]
		);

		$checker = $this->getMathoidChecker();
		$this->assertSame( [ 999, 'expected' ], $checker->getCheckResponse() );
	}

	public function testResponseWithPurge() {
		$wanCache = $this->getServiceContainer()->getMainWANObjectCache();
		$wanCache->set( self::SAMPLE_KEY,
			[ 999, 'unexpected' ],
			WANObjectCache::TTL_INDEFINITE,
			[ 'version' => MathoidChecker::VERSION ]
		);

		$this->setFakeRequest( 200, 'expected' );
		$checker = $this->getMathoidChecker( '\sin x', true );
		$this->assertSame( [ 200, 'expected' ], $checker->getCheckResponse() );
	}

	public function testResponseFromResponse() {
		$this->setMainCache( new EmptyBagOStuff() );

		$this->setFakeRequest( 200, 'expected' );
		$checker = $this->getMathoidChecker();
		$this->assertSame( [ 200, 'expected' ], $checker->getCheckResponse() );
	}

	public function testFailedResponse() {
		$this->setMainCache( new EmptyBagOStuff() );

		$this->setFakeRequest( 401, false );
		$checker = $this->getMathoidChecker();
		$this->expectException( RuntimeException::class );
		$checker->getCheckResponse();
	}

	/**
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param string $mockRequestBody
	 * @param int $mockResponseStatus
	 * @param array $expeted
	 */
	public function testIsValid( $input, $mockRequestBody, $mockResponseStatus, $expeted ) {
		$request = $this->makeFakeHttpRequest( $mockRequestBody, $mockResponseStatus );
		$this->installMockHttp( $request );
		$checker = $this->getMathoidChecker( $input );
		$this->assertSame( $expeted['valid'], $checker->isValid() );
	}

	/**
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param string $mockRequestBody
	 * @param int $mockResponseStatus
	 * @param array $expeted
	 */
	public function testGetChecked( $input, $mockRequestBody, $mockResponseStatus, $expeted ) {
		$request = $this->makeFakeHttpRequest( $mockRequestBody, $mockResponseStatus );
		$this->installMockHttp( $request );
		$checker = $this->getMathoidChecker( $input );
		$this->assertSame( $expeted['checked'], $checker->getValidTex() );
	}

	/**
	 * @dataProvider provideMathoidSamples
	 * @param string $input LaTeX input to check
	 * @param string $mockRequestBody
	 * @param int $mockResponseStatus
	 * @param array $expeted
	 */
	public function testGetError( $input, $mockRequestBody, $mockResponseStatus, $expeted ) {
		$request = $this->makeFakeHttpRequest( $mockRequestBody, $mockResponseStatus );
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
	 * @param bool $purge
	 * @return MathoidChecker
	 */
	private function getMathoidChecker( string $tex = '\sin x', bool $purge = false ): MathoidChecker {
		return Math::getCheckerFactory()
			->newMathoidChecker( $tex, 'tex', $purge );
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

	public static function provideMathoidSamples() {
		yield '\ sin x' => [
			'\sin x',
			file_get_contents( __DIR__ . '/data/mathoid/sinx.json' ), 200,
			[ 'valid' => true, 'checked' => '\sin x' ],
		];
		yield 'invalid F' => [
			'1+\invalid',
			file_get_contents( __DIR__ . '/data/mathoid/invalidF.json' ), 400,
			[ 'valid' => false, 'checked' => null, 'error' => 'unknown function' ],
		];
		yield 'unescaped' => [
			'1.5%',
			file_get_contents( __DIR__ . '/data/mathoid/deprecated.json' ), 200,
			[ 'valid' => true, 'checked' => '1.5\%' ],
		];
		yield 'syntax error' => [
			'\left( x',
			file_get_contents( __DIR__ . '/data/mathoid/syntaxE.json' ), 400,
			[ 'valid' => false, 'checked' => null, 'error' => 'Failed to parse' ],
		];
	}

}
