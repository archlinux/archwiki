<?php

namespace MediaWiki\Tests\Maintenance;

use Benchmarker;
use MediaWikiCoversValidator;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers Benchmarker
 */
class BenchmarkerTest extends \PHPUnit\Framework\TestCase {

	use MediaWikiCoversValidator;

	public function testBenchSimple() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 3;

		$count = 0;
		$bench->bench( [
			'test' => static function () use ( &$count ) {
				$count++;
			}
		] );

		$this->assertSame( 3, $count );
	}

	public function testBenchSetup() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 2;

		$buffer = [];
		$bench->bench( [
			'test' => [
				'setup' => static function () use ( &$buffer ) {
					$buffer[] = 'setup';
				},
				'function' => static function () use ( &$buffer ) {
					$buffer[] = 'run';
				}
			]
		] );

		$this->assertSame( [ 'setup', 'run', 'run' ], $buffer );
	}

	public function testBenchVerbose() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output', 'hasOption', 'verboseRun' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 1;

		$bench->expects( $this->exactly( 1 ) )->method( 'hasOption' )
			->willReturnMap( [
				[ 'verbose', true ],
			] );

		$bench->expects( $this->once() )->method( 'verboseRun' )
			->with( 0 )
			->willReturn( null );

		$bench->bench( [
			'test' => static function () {
			}
		] );
	}

	public function noop() {
	}

	public function testBenchName_method() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output', 'addResult' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 1;

		$bench->expects( $this->once() )->method( 'addResult' )
			->with( $this->callback( static function ( $res ) {
				return isset( $res['name'] ) && $res['name'] === ( __CLASS__ . '::noop()' );
			} ) );

		$bench->bench( [
			[ 'function' => [ $this, 'noop' ] ]
		] );
	}

	public function testBenchName_string() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output', 'addResult' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 1;

		$bench->expects( $this->once() )->method( 'addResult' )
			->with( $this->callback( static function ( $res ) {
				return $res['name'] === "strtolower('A')";
			} ) );

		$bench->bench( [ [
			'function' => 'strtolower',
			'args' => [ 'A' ],
		] ] );
	}

	/**
	 * @covers Benchmarker::verboseRun
	 */
	public function testVerboseRun() {
		$bench = $this->getMockBuilder( Benchmarker::class )
			->onlyMethods( [ 'execute', 'output', 'hasOption', 'startBench', 'addResult' ] )
			->getMock();
		$benchProxy = TestingAccessWrapper::newFromObject( $bench );
		$benchProxy->defaultCount = 1;

		$bench->expects( $this->exactly( 1 ) )->method( 'hasOption' )
			->willReturnMap( [
				[ 'verbose', true ],
			] );

		$bench->expects( $this->once() )->method( 'output' )
			->with( $this->callback( static function ( $out ) {
				return preg_match( '/memory.+ peak/', $out ) === 1;
			} ) );

		$bench->bench( [
			'test' => static function () {
			}
		] );
	}
}
