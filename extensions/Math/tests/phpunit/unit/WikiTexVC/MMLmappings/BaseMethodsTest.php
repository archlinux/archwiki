<?php
namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 */
class BaseMethodsTest extends TestCase {
	public function testCheckAndParseOperatorWithU() {
		$bm = new BaseMethods();
		$this->assertNull( $bm->checkAndParseOperator( '\u3009', null, [], [], [] ) );
		$this->assertNull( $bm->checkAndParseOperator( '&#x3009;', null, [], [], [] ) );
	}

	public function testCheckAndParseOperatorNormal() {
		$bm = new BaseMethods();
		$op = $bm->checkAndParseOperator( '!', null, [], [], [] );
		$this->assertStringContainsString( '!</mo>', $op );
	}

	public function testCheckAndParseOperatorSpecial() {
		$bm = new BaseMethods();
		$op = $bm->checkAndParseOperator( '>', null, [], [], [] );
		$this->assertStringContainsString( '&gt;</mo>', $op );
	}

}
