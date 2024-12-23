<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Matrix;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing
 */
class BaseParsingTest extends TestCase {

	public function testAccent() {
		$node = new Fun1(
			'\\widetilde',
				( new Literal( 'a' ) )
			);
		$result = BaseParsing::accent( $node, [], null, 'widetilde', '007E' );
		$this->assertStringContainsString( '~', $result );
		$this->assertStringContainsString( 'mover', $result );
	}

	public function testAccentArgPassing() {
		$node = new Fun1(
			'\\widetilde',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::accent( $node, [ 'k' => 'v' ], null, 'widetilde', '007E' );
		$this->assertStringContainsString( '<mi k="v"', $result );
	}

	public function testArray() {
		$node = new Matrix( 'matrix',
			new TexArray( new TexArray( new Literal( 'a' ) ) ) );

		$result = BaseParsing::array( $node, [], null, 'array', '007E' );
		$this->assertStringContainsString( '<mi>a</mi>', $result );
	}

	public function testBoldGreek() {
		$node = new Fun1(
			'\\boldsymbol',
			( new Literal( '\\alpha' ) )
		);
		$result = BaseParsing::boldsymbol( $node, [], null, 'boldsymbol' );
		$this->assertStringContainsString( 'mathvariant="bold-italic"', $result );
	}

	public function testBoldSymbol() {
		$node = new Fun1(
			'\\boldsymbol',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::boldsymbol( $node, [], null, 'boldsymbol' );
		$this->assertStringContainsString( 'mathvariant="bold-italic"', $result );
	}

	public function testCancel() {
		$node = new Fun1(
			'\\cancel',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::cancel( $node, [], null, 'cancel', 'something' );
		$this->assertStringContainsString( '<mi>a</mi><mrow class="menclose-something"/>',
			$result );
		$this->assertStringContainsString( '<menclose notation="something" class="menclose">',
			$result );
	}

	public function testUnderOver() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [], null, '', '00AF' );
		$this->assertStringStartsWith( '<mrow', $result );
		$this->assertStringContainsString( 'mover', $result );
	}

	public function testUnderOverUnder() {
		$node = new Fun1(
			'\\underline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [], null, '', '00AF' );
		$this->assertStringContainsString( 'munder', $result );
	}

	public function testUnderOverDqUnder() {
		$node = new Fun1(
			'\\underline', new DQ(
			( new Literal( 'a' ) ),
			( new Literal( 'b' ) )
		) );
		$result = BaseParsing::underover( $node, [], null, '', '00AF' );
		$this->assertStringContainsString( 'munder', $result );
		$this->assertStringContainsString( 'mrow', $result );
	}

	public function testUnderOverInvalid() {
		$node = new Fun1(
			'\\someline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [], null, '', '00AF' );
		$this->assertStringContainsString( 'merror', $result );
	}

	public function testUnderArgPassing() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [ 'k' => 'v' ], null, '', '00AF' );
		$this->assertStringContainsString( '<mi k="v"', $result );
	}

	public function testUnderBadArgPassing() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node,
			[ 'k' => '"<script>alert("problem")</script>"' ], null, '', '00AF' );
		$this->assertStringContainsString( 'k="&quot;&lt;script&gt;alert(&quot;problem&quot;)', $result );
	}

	public function testAlignAt() {
		$matrix = new Matrix( 'alignat',
			new TexArray( new TexArray( new Literal( '\\sin' ) ) ) );
		$result = BaseParsing::alignAt( $matrix, [], null, 'alignat', '002A' );
		$this->assertStringContainsString( 'mtable', $result );
	}

	public function testHLineTop() {
		$matrix = new Matrix( 'matrix',
			new TexArray( new TexArray( new TexArray( new Literal( '\\hline ' ), new Literal( 'a'
			) ) ) ) );
		$result = BaseParsing::matrix( $matrix, [], null, 'matrix', '002A' );
		$this->assertStringContainsString( 'top', $result );
	}

	public function testHLineBottom() {
		$matrix = new Matrix( 'matrix',
			new TexArray( new TexArray( new Literal( 'a' ) ),
				new TexArray( new TexArray( new Literal( '\\hline ' ) ) ) ) );
		$result = BaseParsing::matrix( $matrix, [], null, 'matrix', '002A' );
		$this->assertStringContainsString( 'bottom', $result );
		$this->assertStringContainsString( '<mi>a</mi>', $result );
	}

	public function testHLineLastLine() {
		$matrix = new Matrix( 'matrix',
			new TexArray( new TexArray( new Literal( 'a' ) ),
				new TexArray( new TexArray( new Literal( '\\hline ' ), new Literal( 'a'
				) ) ) ) );
		$result = BaseParsing::matrix( $matrix, [], null, 'matrix', '002A' );
		$this->assertStringContainsString( 'class="mwe-math-matrix-top"', $result );
		$this->assertStringContainsString( '<mi>a</mi>', $result );
	}

	public function testComplicatedHline() {
		$matrix = ( new TexVC() )->parse( '\\begin{array}{c}
\\hline a\\\\
\\hline 1\\\\
2\\\\
\\hline
\\end{array}' )[0];
		$result = BaseParsing::matrix( $matrix, [], null, 'matrix', '002A' );
		$this->assertStringContainsString( 'class="mwe-math-matrix-top"', $result );
		$this->assertStringContainsString( 'class="mwe-math-matrix-top mwe-math-matrix-bottom"', $result );
	}

	public function testHandleOperatorName() {
		$node = new Fun1(
			'\\operatorname',
			( new Literal( 'sn' ) )
		);
		$result = BaseParsing::handleOperatorName( $node, [], [
			"foundNamedFct" => [ true, true ]
		], 'operatorname' );
		$this->assertStringContainsString( 'sn</mi>', $result );
		$this->assertStringContainsString( '<mo>&#x2061;</mo>', $result );
	}

	public function testHandleOperatorLast() {
		$node = new Fun1(
			'\\operatorname',
			( new Literal( 'sn' ) )
		);
		$result = BaseParsing::handleOperatorName( $node, [], [
			"foundNamedFct" => [ true, false ]
		], 'operatorname' );
		$this->assertStringContainsString( 'sn</mi>', $result );
		$this->assertStringNotContainsString( '<mo>&#x2061;</mo>', $result );
	}

	public function testColumnSpecs() {
		$matrix = ( new TexVC() )->parse( '\\begin{array}{lcr}
z & = & a \\\\
f(x,y,z) & = & x + y + z
\\end{array}' )[0];
		$result = BaseParsing::matrix( $matrix, [], null, 'matrix', '002A' );
		$this->assertStringContainsString( 'left center right ', $result );
	}

	public function testNamedOperator() {
		$node = new Literal( '\\gcd' );
		$result = BaseParsing::namedOp( $node, [], [], '\\gcd' );
		$this->assertStringContainsString( '>gcd</mi>', $result );
	}
}
