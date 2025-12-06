<?php
namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use LogicException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods
 */
class BaseMethodsTest extends MediaWikiIntegrationTestCase {
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

	public function testInvalidCall() {
		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'Callback to MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1::lap' .
		' should be treated in the respective class.' );
		BaseMethods::checkAndParse( '\\llap', null, [], new TexNode() );
	}
}
