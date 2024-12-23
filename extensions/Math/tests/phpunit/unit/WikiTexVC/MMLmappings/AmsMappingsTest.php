<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\AMSMappings;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\AMSMappings
 */
class AmsMappingsTest extends TestCase {

	public function testGetAll() {
		$all = AMSMappings::getAll();
		$this->assertIsArray( $all );
		$this->assertNotEmpty( $all );
	}

	public function provideTestCases(): array {
		// the second argument is an array of known problems which should be removed in the future
		return [
			'amsmacros' => [ 'amsmacros', [ 'MultiIntegral', 'HandleTag', 'HandleNoTag', 'HandleRef', 'HandleDeclareOp',
				'HandleShove' ] ],
		];
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testValidMethods( $setName, $knownProblems = [] ) {
		foreach ( AMSMappings::getAll()[$setName] as $symbol => $payload ) {
			$methodName = is_array( $payload ) ? $payload[0] : $payload;
			if ( in_array( $methodName, $knownProblems ) ) {
				continue;
			}
			$this->assertTrue( method_exists( BaseParsing::class, $methodName ),
				'Method ' . $methodName . ' for symbol ' . $symbol . ' does not exist in BaseParsing' );

		}
	}

	public function testGetOperatorByKey() {
		$this->assertEquals( '&#x2A0C;', AMSMappings::getOperatorByKey( '\\iiiint' )[0] );
	}

	public function testGetMathDelimiterByKey() {
		$this->assertEquals( '&#x007C;', AMSMappings::getMathDelimiterByKey( '\\lvert' )[0] );
	}

	public function testGetSymbolDelimiterByKey() {
		$this->assertEquals( '&#x231C;', AMSMappings::getSymbolDelimiterByKey( '\\ulcorner' )[0] );
	}

	public function testGetMacroByKey() {
		$this->assertEquals( 'Tilde', AMSMappings::getMacroByKey( '\\nobreakspace' )[0] );
		$this->assertEquals( 'macro', AMSMappings::getMacroByKey( '\\implies' )[0] );
	}

	public function testGetInstance() {
		$this->assertInstanceOf( AMSMappings::class, AMSMappings::getInstance() );
	}

	public function testGetIdentifierByKey() {
		$this->assertEquals( '&#x0393;', AMSMappings::getIdentifierByKey( '\\varGamma' )[0] );
	}

	public function testGetEnvironmentByKey() {
		$this->assertEquals( 'EqnArray', AMSMappings::getEnvironmentByKey( 'align' )[0] );
	}

}
