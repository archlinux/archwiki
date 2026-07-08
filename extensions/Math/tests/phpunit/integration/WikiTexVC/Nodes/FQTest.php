<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\Nodes;

use ArgumentCountError;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\FQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWikiIntegrationTestCase;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Nodes\FQ
 */
class FQTest extends MediaWikiIntegrationTestCase {

	public function testEmptyFQ() {
		$this->expectException( ArgumentCountError::class );
		new FQ();
		throw new ArgumentCountError( 'Should not create an empty fq' );
	}

	public function testOneArgumentFQ() {
		$this->expectException( ArgumentCountError::class );
		new FQ( new Literal( 'a' ) );
		throw new ArgumentCountError( 'Should not create a fq with one argument' );
	}

	public function testIncorrectTypeFQ() {
		$this->expectException( TypeError::class );
		new FQ( 'a', 'b', 'c' );
		throw new TypeError( 'Should not create a fq with incorrect type' );
	}

	public function testBasicFQ() {
		$fq = new FQ( new Literal( 'a' ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertEquals( 'a_{b}^{c}', $fq->render(), 'Should create a basic fq' );
	}

	public function testGetters() {
		$fq = new FQ( new Literal( 'a' ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertNotEmpty( $fq->getBase() );
		$this->assertNotEmpty( $fq->getUp() );
		$this->assertNotEmpty( $fq->getDown() );
	}

	public function testRenderEmptyFq() {
		$fq = new FQ( TexArray::newCurly(), new Literal( 'b' ), new Literal( 'c' ) );
		$result = $fq->toMMLTree();
		$this->assertStringContainsString( 'msubsup', $result );
		$this->assertStringContainsString( new MMLmrow(), $result );
	}

	public function testRenderEmptyFqNoCurly() {
		$fq = new FQ( new TexArray(), new Literal( 'b' ), new Literal( 'c' ) );
		$result = $fq->toMMLTree();
		$this->assertStringContainsString( 'msubsup', $result );
		$this->assertStringContainsString( ( new MMLmrow() ), $result );
	}

	public function testLatin() {
		$fq = new FQ( new Literal( 'a' ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertStringContainsString( 'msubsup', $fq->toMMLTree() );
	}

	public function testSum() {
		$fq = new FQ( new Literal( '\sum' ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertStringContainsString( 'munderover', $fq->toMMLTree() );
	}

	public function testGreek() {
		$fq = new FQ( new Literal( '\\alpha' ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertStringContainsString( 'msubsup', $fq->toMMLTree() );
	}

	public function testNoLimits() {
		$fq = new FQ( new Literal( '\\nolimits' ), new Literal( '0' ), new Literal( '1' ) );
		$state = [ 'limits' => new Literal( '\\int ' ) ];
		$this->assertStringContainsString( 'msubsup', $fq->toMMLTree( [], $state ) );
	}

	public function testNoLimitsTextStyle() {
		$fq = new FQ( new Literal( '\\nolimits' ), new Literal( '0' ), new Literal( '1' ) );
		$state = [ 'limits' => new Literal( '\\int ' ), 'styleargs' => [ 'displaystyle' => 'inline' ] ];
		$this->assertStringContainsString( 'msubsup', $fq->toMMLTree( [], $state ) );
	}

	public function testLimits() {
		$fq = new FQ( new Literal( '\\limits' ), new Literal( '0' ), new Literal( '1' ) );
		$state = [ 'limits' => new Literal( '\\int ' ) ];
		$this->assertStringContainsString( 'munderover', $fq->toMMLTree( [], $state ) );
	}

	public function testDqLimits() {
		$fq = new DQ( new Literal( '\\lim' ), new Literal( 'x' ) );
		$state = [ 'limits' => new Literal( '\\lim ' ) ];
		$this->assertStringContainsString( 'munder', $fq->toMMLTree( [], $state ) );
	}

	public function testSideset() {
		$fq = new FQ( new TexArray(), new Literal( '0' ), new Literal( '1' ) );
		$state = [ 'sideset' => true ];
		$this->assertInstanceOf( MMLarray::class, $fq->toMMLTree( [], $state ) );
	}

	public function testOverOperator() {
		$fq = new FQ( new Fun1( '\\overbrace', new Literal( 'x' ) ), new Literal( '0' ), new Literal( '1' ) );
		$this->assertStringContainsString( 'munderover', $fq->toMMLTree() );
	}

	public function testUnderbrace() {
		$fq = new DQ( new Fun1( '\\underbrace', new Literal( 'x' ) ), new Literal( 'y' ) );
		$this->assertStringContainsString( 'munder', $fq->toMMLTree() );
	}

	public function testEmptyDq() {
		$fq = new DQ( new TexArray(), new TexArray() );
		$this->assertTrue( $fq->toMMLTree()->isEmpty() );
	}

	public function testDqSum() {
		$fq = new DQ( new Literal( '\\sum' ), new Literal( 'n' ) );
		$this->assertStringContainsString( 'munder', $fq->toMMLTree() );
	}
}
