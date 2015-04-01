<?php
class ExpressionTest extends MediaWikiTestCase {

	/**
	 * @var ExprParser
	 */
	protected $parser;

	protected function setUp() {
		parent::setUp();
		$this->parser = new ExprParser();
	}

	/**
	 * @dataProvider provideExpressions
	 */
	function testExpression( $input, $expected ) {
		$this->assertEquals(
			$expected,
			$this->parser->doExpression( $input )
		);
	}

	function provideExpressions() {
		return array(
			array( '1 or 0', '1' ),
			array( 'not (1 and 0)', '1' ),
			array( 'not 0', '1' ),
			array( '4 < 5', '1' ),
			array( '-5 < 2', '1' ),
			array( '-2 <= -2', '1' ),
			array( '4 > 3', '1' ),
			array( '4 > -3', '1' ),
			array( '5 >= 2', '1' ),
			array( '2 >= 2', '1' ),
			array( '1 != 2', '1' ),
			array( '-4 * -4 = 4 * 4', '1' ),
			array( 'not (1 != 1)', '1' ),
			array( '1 + 1', '2' ),
			array( '-1 + 1', '0' ),
			array( '+1 + 1', '2' ),
			array( '4 * 4', '16' ),
			array( '(1/3) * 3', '1' ),
			array( '3 / 1.5', '2' ),
			array( '3 / 0.2', '15' ),
			array( '3 / ( 2.0 * 0.1 )', '15' ),
			array( '3 / ( 2.0 / 10 )', '15' ),
			array( '3 / (- 0.2 )', '-15' ),
			array( '3 / abs( 0.2 )', '15' ),
			array( '3 mod 2', '1' ),
			array( '1e4', '10000' ),
			array( '1e-2', '0.01' ),
			array( '4.0 round 0', '4' ),
			array( 'ceil 4', '4' ),
			array( 'floor 4', '4' ),
			array( '4.5 round 0', '5' ),
			array( '4.2 round 0', '4' ),
			array( '-4.2 round 0', '-4' ),
			array( '-4.5 round 0', '-5' ),
			array( '-2.0 round 0', '-2' ),
			array( 'ceil -3', '-3' ),
			array( 'floor -6.0', '-6' ),
			array( 'ceil 4.2', '5' ),
			array( 'ceil -4.5', '-4' ),
			array( 'floor -4.5', '-5' ),
			array( 'abs(-2)', '2' ),
			array( 'ln(exp(1))', '1' ),
			array( 'trunc(4.5)', '4' ),
			array( 'trunc(-4.5)', '-4' ),
			array( '123 fmod (2^64-1)', '123' ),
			array( '5.7 mod 1.3', '0' ),
			array( '5.7 fmod 1.3', '0.5' ),
		);
	}
}

