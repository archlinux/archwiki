<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\OperatorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\OperatorDictionary
 */
class OperatorDictionaryTest extends TestCase {
	public function testExistingOperatorWithU() {
		$this->assertNull( OperatorDictionary::getOperatorByKey( '\u3009' ) );
		$this->assertNull( OperatorDictionary::getOperatorByKey( '&#x3009;' ) );
	}

	public function testExistingOperatorNormal() {
		$op = OperatorDictionary::getOperatorByKey( '!' );
		$this->assertIsArray( $op );
		$this->assertCount( 1, $op );
	}

	public function testExistingOperatorSpecial() {
		$op = OperatorDictionary::getOperatorByKey( '>' );
		$this->assertIsArray( $op );
		$this->assertCount( 2, $op );
		$this->assertTrue( $op[1] );
	}

}
