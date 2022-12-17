<?php

namespace MediaWiki\Extension\Math\Tests\TexVC\Nodes;

use InvalidArgumentException;
use MediaWiki\Extension\Math\TexVC\Nodes\TexNode;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @covers \MediaWiki\Extension\Math\TexVC\Nodes\TexNode
 */
class TexNodeTest extends MediaWikiUnitTestCase {

	public function testEmptyNode() {
		$n = new TexNode();
		$this->assertSame( '', $n->render(), 'Should create an empty node' );
	}

	public function testEmptyStringNode() {
		$n = new TexNode( '' );
		$this->assertSame( '', $n->render(), 'Should create a node with am empty string' );
	}

	public function testHelloWorldNode() {
		$n = new TexNode( 'hello', ' ', 'world' );
		$this->assertEquals( 'hello world', $n->render(), 'Should create a hello world node' );
	}

	public function testNestedNode() {
		$n = new TexNode( 'hello', new TexNode( ' ' ), new TexNode( new TexNode( 'world' ) ) );
		$this->assertEquals( 'hello world', $n->render(), 'Should create a nested hello world node' );
	}

	public function testIntegerArgs() {
		$this->expectException( InvalidArgumentException::class );
		( new TexNode( 1 ) )->render();
		throw new RuntimeException( 'Should not accept integers as arguments' );
	}

	public function testAddCurlies() {
		$n = new TexNode( 'a' );
		$this->assertEquals( '{a}', $n->inCurlies(), 'Should add curlies' );
	}

	public function testNotNestCurlies() {
		$n = new TexNode( new TexNode( 'a' ) );
		$this->assertEquals( '{a}', $n->inCurlies(), 'Should not nest curlies' );
	}

	public function testProduceEmptyCurlies() {
		$n = new TexNode( '' );
		$this->assertEquals( '{}', $n->inCurlies(), 'Should produce empty curlies' );
	}

	public function testExtractIdentifiers() {
		$n = new TexNode( new TexNode( 'a' ) );
		$this->assertEquals( [ 'a' ], $n->extractIdentifiers(), 'Should extract identifiers' );
	}

	public function testIdentiferMods() {
		$n = new TexNode( '' );
		$this->assertEquals( [], $n->getModIdent(),
			'Should contain a method stub for extracting identifier modifications' );
	}

	public function testExtractSubscripts() {
		$n = new TexNode( '' );
		$this->assertEquals( [], $n->extractSubscripts(),
			'Should contain a method stub for extracting subscripts' );
	}

	public function testMatchFunctionalityFalse() {
		$res = TexNode::match( 'asd', 'sda' );

		$this->assertFalse( $res,
			'should return false on non-matching strings' );
	}

	public function testMatchFunctionalityString() {
		$res = TexNode::match( 'asd', 'asd' );

		$this->assertEquals( 'asd',  $res,
			'should return matching string' );
	}

	public function testMatchFunctionalityArrayFalse() {
		$res = TexNode::match( [ 'asd', 'ert' ], 'sda' );

		$this->assertFalse( $res,
			'should return false on non-matching arrays' );
	}

	public function testMatchFunctionalityArray() {
		$res = TexNode::match( [ 'ert', 'asd' ], 'asd' );

		$this->assertEquals( 'asd',  $res,
			'should return matching string for matching item in array' );
	}

	public function testSpecialCase1() {
		$res = TexNode::texContainsFunc( '\\operatorname', '\\operatorname {someword}' );
		$this->assertEquals( '\\operatorname',  $res,
			'should return matching operator for operatorname with someword' );
	}

	public function testSpecialCase1Array() {
		$res = TexNode::texContainsFunc( [ '\\operatorname', '\\nonexistingooperator' ], '\\operatorname {someword}' );
		$this->assertEquals( '\\operatorname',  $res,
			'should return matching operator for operatorname with someword as array' );
	}

	public function testSpecialCase2() {
		$res = TexNode::texContainsFunc( '\\mbox', '\\mbox{\\somefunc}' );
		$this->assertEquals( '\\mbox',  $res,
			'should return matching operator for mbox with somefunc' );
	}

}
