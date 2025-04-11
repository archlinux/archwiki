<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\Nodes;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1nb;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray
 */
class TexArrayTest extends MediaWikiUnitTestCase {

	public function testIsTexNode() {
		$this->assertTrue( new TexArray() instanceof TexNode,
			'Should create an instance of TexNode' );
	}

	public function testConcatOutput() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		$this->assertEquals( 'ab', $ta->render(), 'Should concatenate its input' );
	}

	public function testExtractCurlies() {
		$n = new TexNode( new TexArray( new Literal( 'a' ) ) );
		$this->assertEquals( '{a}', $n->inCurlies(),
			'Should create exactly one pair of curlies' );
	}

	public function testExtractIdentifiers() {
		$n = new TexArray( new Literal( 'd' ) );
		$this->assertEquals( [ 'd' ], $n->extractIdentifiers(),
			'Should extract identifiers' );
	}

	public function testExtractIdentifiersFromArg() {
		$n = new TexArray();
		$this->assertEquals( [ 'd' ],
			$n->extractIdentifiers( [ new Literal( 'd' ) ] ),
			'Should extract identifiers from the argument' );
	}

	public function testExtractSplitIdentifiers() {
		$n = new TexArray( new Literal( 'a' ), new Literal( '\'' ) );
		$this->assertEquals( [ 'a\'' ], $n->extractIdentifiers(),
			'Should extract split identifiers' );
	}

	public function testNotConfuseIntegralsIdentifiers() {
		$n = new TexArray( new Literal( 'd' ), new Literal( '\\int' ) );
		$this->assertEquals( [ 'd' ], $n->extractIdentifiers(),
			'Should not confuse integrals and identifiers' );
	}

	public function testNotConfuseIntegralD() {
		$n = new TexArray( new Literal( '\\int' ), new Literal( 'd' ) );
		$this->assertEquals( [], $n->extractIdentifiers(), 'Should not confuse integral d with d identifier' );
	}

	public function testNotConfuseUprightIntegralD() {
		$n = new TexArray( new Literal( '\\int' ), new Literal( '\\mathrm{d}' ) );
		$this->assertEquals( [], $n->extractIdentifiers(),
			'Should not confuse upright integral d with d identifier' );
	}

	public function testExtractIdentifierMods() {
		$n = new TexArray( new TexNode( '' ) );
		$this->assertEquals( [], $n->getModIdent(), 'Should extract identifier modifications' );
	}

	public function testExtractSubscripts() {
		$n = new TexArray( new TexNode( '' ) );
		$this->assertEquals( [], $n->extractSubscripts(), 'Should extract subscripts' );
	}

	public function testUnshift() {
		$n = new TexArray( new TexNode( '' ) );
		$n->unshift( 'test1', 'test2' );
		$this->assertEquals( 3, $n->getLength(), 'Should unshift elements' );
	}

	public function testGenerator() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		foreach ( $ta as $item ) {
			$this->assertInstanceOf(
				'MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal',
				$item,
				'Should iterate over the elements' );
		}
	}

	public function testOffsetExists() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		$this->assertTrue( isset( $ta[0] ) );
		$this->assertFalse( isset( $ta[2] ) );
	}

	public function testOffsetGet() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		$this->assertEquals( 'a', $ta[0]->render() );
		$this->assertNull( $ta[100] );
	}

	public function testOffsetUnset() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		unset( $ta[0] );
		$this->assertNull( $ta[0] );
	}

	public function testOffsetSet() {
		$ta = new TexArray();
		$ta[0] = new Literal( 'a' );
		$this->assertEquals( 'a', $ta[0]->render() );
	}

	public function testOffsetSetInvalid() {
		$this->expectException( InvalidArgumentException::class );
		$ta = new TexArray();
		$ta[0] = 'a';
	}

	public function testSquashLiterals() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( 'b' ) );
		$state = [ 'squashLiterals' => true ];
		$res  = $ta->renderMML( [], $state );
		$this->assertEquals( '<mi>ab</mi>', $res );
	}

	public function testSquashLiteralsMacro() {
		$ta = new TexArray( new Literal( 'a' ), new Literal( '\gamma' ) );
		$state = [ 'squashLiterals' => true ];
		$res  = $ta->renderMML( [], $state );
		$this->assertEquals( '<mi>a</mi><mi>&#x03B3;</mi>', $res );
	}

	public function testSumInLimits() {
		$ta = new TexArray();
		$sum = new Literal( '\sum' );
		$res  = $ta->checkForLimits( $sum, new DQ( new Literal( '\limits' ), new Literal( 'n' ) ) );
		$this->assertTrue( $res[1] );
		$this->assertEquals( $sum, $res[0] );
	}

	public function testCustomOpInLimits() {
		$ta = new TexArray();
		$custom = new Fun1nb( '\operatorname', new TexArray( new Literal( 'S' ) ) );
		$res  = $ta->checkForLimits( $custom, new DQ( new Literal( '\limits' ), new Literal( 'n' ) ) );
		$this->assertTrue( $res[1] );
		$this->assertEquals( $custom, $res[0] );
	}

	public function testRenderADeriv() {
		$n = new TexArray( new Literal( 'A' ) );
		$state = [ 'deriv' => 1 ];
		$mml = $n->renderMML( [], $state );
		$this->assertStringContainsString( '&#x2032;</mo>', $mml );
	}
}
