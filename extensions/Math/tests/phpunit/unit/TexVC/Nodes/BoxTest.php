<?php

namespace MediaWiki\Extension\Math\Tests\TexVC\Nodes;

use ArgumentCountError;
use MediaWiki\Extension\Math\TexVC\Nodes\Box;
use MediaWiki\Extension\Math\TexVC\Nodes\Literal;
use MediaWikiUnitTestCase;
use RuntimeException;
use TypeError;

/**
 * @covers \MediaWiki\Extension\Math\TexVC\Nodes\Box
 */
class BoxTest extends MediaWikiUnitTestCase {

	public function testEmptyBox() {
		$this->expectException( ArgumentCountError::class );
		new Box();
		throw new ArgumentCountError( 'Should not create an empty box' );
	}

	public function testOneArgumentBox() {
		$this->expectException( ArgumentCountError::class );
		new Box( '\\hbox' );
		throw new ArgumentCountError( 'Should not create a box with one argument' );
	}

	public function testIncorrectTypeBox() {
		$this->expectException( TypeError::class );
		new Box( '\\hbox', new Literal( 'a' ) );
		throw new RuntimeException( 'Should not create a box with incorrect type' );
	}

	public function testBasicFunctionBox() {
		$box = new Box( '\\hbox', 'a' );
		$this->assertEquals( '{\\hbox{a}}', $box->render(), 'Should create a basic function' );
	}

	public function testExtractIdentifiersBox() {
		$box = new Box( '\\hbox', 'a' );
		$this->assertEquals( [], $box->extractIdentifiers(), 'Should extract identifiers' );
	}

	public function testCurliesBox() {
		$box = new Box( '\\hbox', 'a' );
		$this->assertEquals( '{\\hbox{a}}', $box->inCurlies(), 'Should create exactly one set of curlies' );
	}
}
