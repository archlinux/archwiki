<?php
namespace MediaWiki\Extension\Math\Tests\WikiTexVC\Nodes;

use ArgumentCountError;
use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\LengthSpec;
use MediaWikiUnitTestCase;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Nodes\LengthSpec
 */
class LengthSpecTest extends MediaWikiUnitTestCase {

	public function testEmptyLengthSpec() {
		$this->expectException( ArgumentCountError::class );
		new LengthSpec();
	}

	public function testInvalidNumber() {
		$this->expectException( InvalidArgumentException::class );
		new LengthSpec( '+', [ '1' ], 'pt' );
	}

	public function testNullUnit() {
		$this->expectException( TypeError::class );
		new LengthSpec( '+', [ '1', '.', '0' ], null );
	}

	public function testRenderLengthSpec() {
		$lengthSpec = new LengthSpec( '+', [ [ '1' ], '.', [ '0' ] ], 'pt' );
		$this->assertEquals( '[+1.0pt]', $lengthSpec->render() );
	}

	public function testRenderLengthSpec2() {
		$lengthSpec = new LengthSpec( '+', [ '.', [ '0' ] ], 'pt' );
		$this->assertEquals( '[+.0pt]', $lengthSpec->render() );
	}

	public function testRenderLengthSpecLong() {
		$lengthSpec = new LengthSpec( null, [ [ '1', '2', '3' ], '.', [ '4', '5', '6' ] ], 'pt' );
		$this->assertEquals( '[123.456pt]', $lengthSpec->render() );
	}
}
