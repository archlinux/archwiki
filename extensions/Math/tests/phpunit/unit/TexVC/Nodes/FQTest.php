<?php

namespace MediaWiki\Extension\Math\Tests\TexVC\Nodes;

use ArgumentCountError;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\Nodes\Curly;
use MediaWiki\Extension\Math\TexVC\Nodes\FQ;
use MediaWiki\Extension\Math\TexVC\Nodes\Literal;
use MediaWiki\Extension\Math\TexVC\Nodes\TexArray;
use MediaWikiUnitTestCase;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\TexVC\Nodes\FQ
 */
class FQTest extends MediaWikiUnitTestCase {

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

	public function testRenderEmptyDq() {
		$fq = new FQ( new Curly( new TexArray() ), new Literal( 'b' ), new Literal( 'c' ) );
		$this->assertStringContainsString( ( new MMLmrow() )->getEmpty(), $fq->renderMML() );
	}
}
