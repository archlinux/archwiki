<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLParsingUtil;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLParsingUtil
 */
class MMLParsingUtilTest extends MediaWikiUnitTestCase {

	public function testInvalidColor() {
		$result = MMLParsingUtil::parseDefineColorExpression( "INVALID" );
		$this->assertNull( $result );
	}

	public function testRGBOne() {
		$result = MMLParsingUtil::parseDefineColorExpression(
			"\\definecolor {ultramarine}{rgb}{0,0.12549019607843,0.37647058823529}" );
		$this->assertEquals( 'ultramarine', $result['name'] );
		$this->assertEquals( 'rgb', $result['type'] );
		$this->assertEquals( '#002060', $result['hex'] );
	}

	public function testInvalidColorString() {
		$result = MMLParsingUtil::parseDefineColorExpression(
			"\\definecolor {gray}{0.123}" );
		$this->assertNull( $result );
	}

	public function testUnicode_afr() {
		$result = MMLParsingUtil::mapToFrakturUnicode( 'a' );
		$this->assertEquals( '&#x1D51E;', $result );
	}

	public function testUnicode_bfr() {
		$result = MMLParsingUtil::mapToFrakturUnicode( 'B' );
		$this->assertEquals( '&#x1D505;', $result );
	}

	public function testUnicode_Cfr() {
		$result = MMLParsingUtil::mapToFrakturUnicode( 'C' );
		$this->assertEquals( '&#x0212D;', $result );
	}

	public function testUnicodeUtf8Input() {
		$result = MMLParsingUtil::mapToFrakturUnicode( '𝔄' );
		$this->assertEquals( '𝔄', $result );
	}
}
