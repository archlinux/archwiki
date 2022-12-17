<?php

namespace MediaWiki\Extension\Math\Tests\TexVC\Nodes;

use ArgumentCountError;
use MediaWiki\Extension\Math\TexVC\Nodes\DQ;
use MediaWiki\Extension\Math\TexVC\Nodes\Literal;
use MediaWiki\Extension\Math\TexVC\Nodes\TexNode;
use MediaWikiUnitTestCase;
use RuntimeException;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\TexVC\Nodes\DQ
 */
class DQTest extends MediaWikiUnitTestCase {

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

	public function testEmptyBaseDQ() {
		$dq = new DQ( new TexNode(), new Literal( 'b' ) );
		$this->assertEquals( '_{b}', $dq->render(), 'Should create an empty base dq' );
	}
}
