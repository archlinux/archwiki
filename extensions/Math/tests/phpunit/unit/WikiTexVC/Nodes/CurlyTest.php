<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray
 */
class CurlyTest extends MediaWikiUnitTestCase {

	public function testRenderTexCurly() {
		$curly = TexArray::newCurly();
		$this->assertEquals( '{}', $curly->render(), 'Should render a curly with empty tex array' );
	}

	public function testRenderListCurly() {
		$curly = TexArray::newCurly(
			new Literal( 'hello' ),
			new Literal( ' ' ),
			new Literal( 'world' )
		 );
		$this->assertEquals( '{hello world}', $curly->render(), 'Should render a list' );
	}

	public function testGetters() {
		$curly = TexArray::newCurly( new Literal( 'b' ) );
		$this->assertNotEmpty( $curly->getArgs() );
	}

	public function testNoExtraCurliesDQ() {
		$dq = new DQ( new Literal( 'a' ),
			TexArray::newCurly( new Literal( 'b' ) ) );
		$this->assertEquals( 'a_{b}', $dq->render(), 'Should not create extra curlies from dq' );
	}

	public function testNoExtraCurliesCurly() {
		$curly = TexArray::newCurly( new Literal( 'a' ) );
		$this->assertEquals( '{a}', $curly->inCurlies(), 'Should not create extra curlies from curly' );
	}

	public function testExtractIdentifierModsCurly() {
		$curly = TexArray::newCurly( new Literal( 'b' ) );
		$this->assertEquals( 'b', $curly->getModIdent(), 'Should extract identifier modifications' );
	}

	public function testExtractSubscirpts() {
		$curly = TexArray::newCurly( new Literal( 'b' ) );
		$this->assertEquals( 'b', $curly->extractSubscripts(), 'Should extract subscripts' );
	}
}
