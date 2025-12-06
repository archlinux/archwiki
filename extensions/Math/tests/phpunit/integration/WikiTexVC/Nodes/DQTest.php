<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\Nodes;

use ArgumentCountError;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ
 */
class DQTest extends MediaWikiIntegrationTestCase {

	public function testEmptyDQ() {
		$this->expectException( ArgumentCountError::class );
		new DQ();
		throw new ArgumentCountError( 'Should not create an empty dq' );
	}

	public function testOneArgumentDQ() {
		$this->expectException( ArgumentCountError::class );
		new DQ( new Literal( 'a' ) );
		throw new ArgumentCountError( 'Should not create a dq with one argument' );
	}

	public function testIncorrectTypeDQ() {
		$this->expectException( TypeError::class );
		new DQ( 'a', 'b' );
		throw new RuntimeException( 'Should not create a dq with incorrect type' );
	}

	public function testBasicDQ() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$this->assertEquals( 'a_{b}', $dq->render(), 'Should create a basic dq' );
	}

	public function testGetters() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$this->assertNotEmpty( $dq->getBase() );
		$this->assertNotEmpty( $dq->getDown() );
	}

	public function testEmptyBaseDQ() {
		$dq = new DQ( new TexNode(), new Literal( 'b' ) );
		$this->assertEquals( '_{b}', $dq->render(), 'Should create an empty base dq' );
	}

	public function testRenderEmptyDq() {
		$dq = new DQ( TexArray::newCurly(), new Literal( 'b' ) );
		$this->assertStringContainsString( new MMLmrow(), $dq->toMMLTree() );
	}

	public function testRenderEmptyDisplaystyle() {
		$dq = new DQ( new Literal( '\\displaystyle' ), new Literal( 'b' ) );
		$this->assertStringContainsString( new MMLmrow(), $dq->toMMLTree() );
	}

	public function testBigSum() {
		$dq = new DQ( new Literal( '\\sum' ), new Literal( 'i' ) );
		$this->assertStringContainsString( "<munder>", $dq->toMMLTree() );
	}

	public function testSmallSum() {
		$dq = new DQ( new Literal( '\\sum' ), new Literal( 'i' ) );
		$state = [ 'styleargs' => [ 'displaystle' => 'false' ] ];
		$this->assertStringContainsString(
			"<msub>",
			$dq->toMMLTree( [], $state ) );
	}

	public function testIdentifiers() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$identifiers = $dq->extractIdentifiers();
		$this->assertCount( 1, $identifiers );
		$this->assertEquals( 'a_{b}', $identifiers[0] );
	}

	public function testExtractSubscriptsBasic() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$subscripts = $dq->extractSubscripts();
		$this->assertCount( 1, $subscripts );
		$this->assertEquals( 'a_{b}', $subscripts[0] );
	}

	public function testExtractSubscriptsEmptyBase() {
		$dq = new DQ( new TexNode(), new Literal( 'b' ) );
		$subscripts = $dq->extractSubscripts();
		$this->assertArrayEquals( [], $subscripts );
	}

	public function testGetModIdentBasic() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$modIdent = $dq->getModIdent();
		$this->assertCount( 1, $modIdent );
		$this->assertEquals( 'a_{b}', $modIdent[0] );
	}

	public function testGetModIdentPrimeBase() {
		$dq = new DQ( new Literal( '\'' ), new Literal( 'b' ) );
		$modIdent = $dq->getModIdent();
		$this->assertArrayEquals( [], $modIdent );
	}

	public function testExtractIdentifiersPrimeBase() {
		$dq = new DQ( new Literal( '\'' ), new Literal( 'b' ) );
		$identifiers = $dq->extractIdentifiers();
		$this->assertContains( '\'', $identifiers );
		$this->assertContains( 'b', $identifiers );
	}

	public function testExtractIdentifiersIntBase() {
		$dq = new DQ( new Literal( '\\int' ), new Literal( 'b' ) );
		$identifiers = $dq->extractIdentifiers();
		$this->assertContains( '\\int', $identifiers );
		$this->assertContains( 'b', $identifiers );
	}

	public function testToMMLTreeEmptyBranch() {
		$dq = new DQ( new TexNode(), new TexNode() );
		$this->assertNull( $dq->toMMLTree(), 'toMMLTree should return null for empty DQ' );
	}

	public function testToMMLTreeLimitsCase() {
		$dq = new DQ( new Literal( 'a' ), new Literal( 'b' ) );
		$state = [
				'limits' => new Literal( 'c' ),
		];
		$result = $dq->toMMLTree( [], $state );
		$this->assertNotNull( $result, 'toMMLTree should handle limits case and not return null' );
		$this->assertInstanceOf( MMLmunder::class, $result, 'toMMLTree should return MMLmunder' );
	}
}
