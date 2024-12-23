<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\SyntaxError;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexVC
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Parser
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexUtil
 * @covers \MediaWiki\Extension\Math\WikiTexVC\ParserUtil
 */
class ApiTest extends MediaWikiUnitTestCase {

	/** @var TexVC */
	private $texVC;

	protected function setUp(): void {
		parent::setUp();
		$this->texVC = new TexVC();
	}

	public function provideTestCases() {
		$testCases = [
			[
				'in' => '\\newcommand{\\text{do evil things}}',
				'status' => 'F',
				'details' => '\\newcommand'
			],
			[
				'in' => '\\sin\\left(\\frac12x\\right)',
				'output' => '\\sin \\left({\\frac {1}{2}}x\\right)'
			],
			[
				'in' => '\\reals',
				'output' => '\mathbb {R} ',
				'ams_required' => true
			],
			[
				'in' => '\\lbrack',
				'output' => '\\lbrack '
			],
			[
				'in' => '\\figureEightIntegral',
				'status' => 'F',
				'details' => '\\figureEightIntegral'
			],
			[
				'in' => '\diamondsuit '
			],
			[
				'in' => '\\sinh x'
			],
			[
				'in' => '\\begin{foo}\\end{foo}',
				'status' => 'F',
				'details' => '\\begin{foo}'
			],
			[
				'in' => '\\hasOwnProperty',
				'status' => 'F',
				'details' => '\\hasOwnProperty'
			],
			[
				'in' => '\\hline',
				'status' => 'S'
			],
			[
				'in' => '\\begin{array}{c}\\hline a \\\\ \\hline\\hline b \\end{array}',
				'output' => '{\\begin{array}{c}\\hline a\\\\\\hline \\hline b\\end{array}}'
			],
			[
				'in' => '\\Diamond ',
				'ams_required' => true
			],
			[
				'in' => '{\\begin{matrix}a\\ b\\end{matrix}}',
				'ams_required' => true
			],
			[
				'in' => '{\\cancel {x}}',
				'cancel_required' => true
			],
			[
				'in' => '\\color {red}',
				'color_required' => true
			],
			[
				'in' => '\\euro',
				'output' => '\\mbox{\\euro} ',
				'euro_required' => true
			],
			[
				'in' => '\\coppa',
				'output' => '\\mbox{\\coppa} ',
				'teubner_required' => true
			],
			[
				'in' => '{\\color [rgb]{1,0,0}{\\mbox{This text is red.}}}',
				'color_required' => true
			],
			[
				'in' => '{\\color[rgb]{1.5,0,0}{\\mbox{This text is bright red.}}}',
				'status' => 'S'
			],
			[
				'in' => '{\\color [RGB]{51,0,0}{\\mbox{This text is dim red.}}}',
				'output' => '{\\color [rgb]{0.2,0,0}{\\mbox{This text is dim red.}}}',
				'color_required' => true
			],
			[
				'in' => '{\\color[RGB]{256,0,0}{\\mbox{This text is bright red.}}}',
				'status' => 'S'
			],
			[
				'in' => '\\ce{ H2O }',
				'mhchem_required' => true,
				'status' => 'C'
			],
			[
				'in' => '\\ce{[Zn(OH)4]^2-}',
				'mhchem_required' => true,
				'status' => 'C'
			],
			[
				'in' => '{\\begin{aligned}a\\\\b\\end{aligned}}',
				'ams_required' => true,
			],
			[
				'in' => '{\\begin{aligned}a\\\\[6pt] b\\end{aligned}}',
				'output' => '{\\begin{aligned}a\\\\[6pt]b\\end{aligned}}',
				'ams_required' => true,
			],
			[
				'in' => '{\\begin{aligned}a\\\\[-3.4mm] b\\end{aligned}}',
				'output' => '{\\begin{aligned}a\\\\[-3.4mm]b\\end{aligned}}',
				'ams_required' => true,
			],
			[
				'in' => '{\\begin{aligned}a\\\\[8mu] b\\end{aligned}}',
				'output' => '{\\begin{aligned}a\\\\[8mu]b\\end{aligned}}',
				'ams_required' => true,
			],
			[
				'in' => '{\\begin{aligned}a\\\\[-2.563pt]b\\\\c\\\\[2em]d\\end{aligned}}',
				'ams_required' => true,
			],
		];
		foreach ( $testCases as $case ) {
			yield $case['in'] => [ $case ];
		}
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testDefinedCases( array $case ) {
		$result = $this->texVC->check( $case['in'], [ 'report_required' => true ] );
		$resultStatus = $result['status'] ?? '';
		$caseStatus = $case['status'] ?? '+';
		$this->assertSame( $caseStatus, $resultStatus, 'Status incorrect' );
		if ( $resultStatus === '+' ) {
			$this->assertSame( $case['output'] ?? $case['in'], $result['output'] ?? '', 'Output incorrect' );
			foreach ( array_keys( $result ) as $f ) {
				if ( preg_match( '/_required/', $f ) ) {
					$this->assertSame( array_key_exists( $f, $case ), $result[$f],
						'A required field does not match in result ' . $f );
				}
				$this->assertCount( 0, $result['warnings'], 'No warnings expected here.' );
			}
		}
		if ( $resultStatus === 'F' ) {
			$this->assertSame( $case['details'], $result['details'] ?? '', 'Details incorrect' );
			$this->assertCount( 0, $result['warnings'], 'No warnings expected here.' );
		}
	}

	public function testSuccess1() {
		$message = 'should return success (1)';
		$result = $this->texVC->check( '\\sin(x)+{}{}\\cos(x)^2 newcommand' );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertEquals( '\\sin(x)+{}{}\\cos(x)^{2}newcommand', $result['output'], $message );
	}

	public function testSuccess2() {
		$message = 'should return success (2)';
		$result = $this->texVC->check( 'y=x+2' );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertEquals( 'y=x+2', $result['output'], $message );
	}

	public function testReport1() {
		$message = 'should report undefined functions (1)';
		$result = $this->texVC->check( '\\foo' );
		$this->assertEquals( 'F', $result['status'], $message );
		$this->assertEquals( '\\foo', $result['details'], $message );
	}

	public function testReport2() {
		$message = 'should report undefined functions (2)';
		$result = $this->texVC->check( '\\write18' );
		$this->assertEquals( 'F', $result['status'], $message );
		$this->assertEquals( '\\write', $result['details'], $message );
	}

	public function testUndefined() {
		$message = 'should report undefined parser errors';
		$result = $this->texVC->check( '^' );
		$this->assertEquals( 'S', $result['status'], $message );
	}

	public function testDebugException() {
		// should throw an exception in debug mode
		$this->expectException( SyntaxError::class );
		$this->texVC->check( '^', [ 'debug' => true ] );
	}

	public function testParsedInput() {
		$message = 'should accept parsed input';
		$parsed = $this->texVC->parse( 'y=x+2' );
		$result = $this->texVC->check( $parsed );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertEquals( 'y=x+2', $result['output'], $message );
	}

	public function testRetryParsing() {
		$message = 'should retry parsing if oldmhchem is not set';
		$result = $this->texVC->check( '\\ce {A\\;+\\;B\\;->\\;C}', [ 'usemhchem' => true ] );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertEquals( 'mhchem-deprecation', $result['warnings'][0]['type'], $message );
		$this->assertEquals( 'S', $result['warnings'][0]['details']['status'], $message );
	}

	public function testDeprecationWarning() {
		// This test is not complete yet
		$message = 'should show a deprecation warning for \\and';
		$result = $this->texVC->check( '\\and' );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertEquals( 'texvc-deprecation', $result['warnings'][0]['type'], $message );
		$this->assertEquals( 'S', $result['warnings'][0]['details']['status'], $message );
	}

	public function testDeprecationWarning2() {
		$message = 'should not show a deprecation warning for \\land';
		$result = $this->texVC->check( '\\land' );
		$this->assertEquals( '+', $result['status'], $message );
		$this->assertCount( 0, $result['warnings'], $message );
	}

	public function testNoRetry() {
		$message = 'should not retry parsing if oldmhchem is set';
		$result = $this->texVC->check( '\\ce {A\\;+\\;B\\;->\\notvalidcommand}',
			[ 'usemhchem' => true, 'oldmhchem' => true ] );
		$this->assertEquals( 'F', $result['status'], $message );
	}

	public function testSquareDq() {
		$result = $this->texVC->check( ']_x',
			[ 'usemhchem' => true, 'oldmhchem' => true ] );
		$this->assertEquals( ']_{x}', $result['output'] );
	}

	public function testSquareFq() {
		$result = $this->texVC->check( ']_x^2',
			[ 'usemhchem' => true, 'oldmhchem' => true ] );
		$this->assertEquals( ']_{x}^{2}', $result['output'] );
	}

	public function mhchemtexifiedTest() {
		$result = $this->texVC->check( '\\longleftrightarrows',
			[ 'usemhchemtexified' => true ] );
		$this->assertEquals( '\\longleftrightarrows', $result['output'] );
	}

	public function mhchemtexifiedTestFail() {
		$result = $this->texVC->check( '\\longleftrightarrows' );
		$this->assertEquals( 'C', $result['status'] );
		$this->assertFalse( $result['success'] );
	}
}
