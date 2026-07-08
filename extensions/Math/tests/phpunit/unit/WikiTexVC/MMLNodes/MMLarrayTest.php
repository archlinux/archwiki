<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray
 */
class MMLarrayTest extends MediaWikiUnitTestCase {
	public function testIsEmpty() {
		$base = new MMLarray( '', null, new MMLbase( 'test', 'texClass', [] ) );
		$this->assertTrue( $base->isEmpty() );
		$base->addChild( new MMLmi( 'test', [], '' ) );
		$this->assertFalse( $base->isEmpty() );
	}

	public function testIsEmptyString() {
		$base = new MMLarray( '', null, new MMLbase( 'test', 'texClass', [] ) );
		$this->assertTrue( $base->isEmpty() );
		$base->addChild( 'non-empty-string' );
		$this->assertFalse( $base->isEmpty() );
	}

	public function testToStringWithStringsOnly() {
		$base = new MMLarray( '', null, 'Hello', ' ', 'World' );
		$this->assertSame( 'Hello World', (string)$base );
	}

	public function testToStringWithEmptyArray() {
		$base = new MMLarray();
		$this->assertSame( '', (string)$base );
	}
}
