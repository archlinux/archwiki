<?php

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLDomVisitor;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;

/**
 * Test the results of MathFormatter
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLDomVisitor
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLDomVisitorTest extends MediaWikiIntegrationTestCase {

	public function testLeafNodeConversion() {
		$visitor = new MMLDomVisitor();
		$mi = new MMLmi( '', [ 'mathvariant' => Variants::BOLD ], 'x' );
		$visitor->visit( $mi );
		$this->assertEquals(
			'<mi mathvariant="bold">x</mi>',
			$visitor->getHTML()
		);
	}

	public function testEmptyContainerConversion() {
		$visitor = new MMLDomVisitor();
		$mrow = new MMLmrow();
		$visitor->visit( $mrow );
		$this->assertEquals(
			'<mrow data-mjx-texclass="ORD"></mrow>',
			$visitor->getHTML()
		);
	}

	public function testStringCastLeafNode() {
		$visitor = new MMLDomVisitor();
		$mn = new MMLmn( '', [], '5' );
		$mn->accept( $visitor );
		$this->assertEquals(
			'<mn>5</mn>',
			$visitor->getHTML()
		);
	}

	public function testSpecialCharacterEscaping() {
		$visitor = new MMLDomVisitor();
		$mi = new MMLmi( '', [], '<>&"' );
		$mi->accept( $visitor );
		$this->assertEquals(
			'<mi>&lt;&gt;&amp;"</mi>',
			$visitor->getHTML()
		);
	}

	public function testEscapedUnicode() {
		$visitor = new MMLDomVisitor();
		$mi = new MMLmi( '', [], '&#x03B3;' );
		$mi->accept( $visitor );
		$this->assertEquals(
			'<mi>&#x03B3;</mi>',
			$visitor->getHTML()
		);
	}

	public function testNestedStructure() {
		$visitor = new MMLDomVisitor();
		$mi = new MMLmi( '', [], 'x' );
		$mo = new MMLmo( '', [], '+' );
		$mn = new MMLmn( '', [], '5' );
		$mrow = new MMLmrow( '', [], $mi, $mo, $mn );

		$expected = <<<XML
		<mrow>
		  <mi>x</mi>
		  <mo>+</mo>
		  <mn>5</mn>
		</mrow>
		XML;
		$visitor->visit( $mrow );
		$this->assertXmlStringEqualsXmlString( $expected, $visitor->getHTML() );
	}

	public function testDeepNesting() {
		$visitor = new MMLDomVisitor();
		$inner = new MMLmrow(
			'', [], new MMLmi( '', [], 'x' ),
			new MMLmo( '', [], '=' )
		);
		$outer = new MMLmrow( '', [], $inner, new MMLmn( '', [], '5' ) );
		$visitor->visit( $outer );
		$expected = <<<XML
		<mrow>
			<mrow>
				<mi>x</mi>
				<mo>=</mo>
			</mrow>
			<mn>5</mn>
		</mrow>
		XML;
		$this->assertXmlStringEqualsXmlString( $expected, $visitor->getHTML() );
	}

	public function testBasicStringChild() {
		$visitor = new MMLDomVisitor();
		$mrow = new MMLbase( 'mrow', '', [], '<mo>&#x27F9;</mo>' );

		$mrow->accept( $visitor );
		$output = $visitor->getHTML();

		$expected = '<mrow><mo>&#x27F9;</mo></mrow>';
		$this->assertXmlStringEqualsXmlString( $expected, $output );
	}

	public function testMixedChildren() {
		$visitor = new MMLDomVisitor();
		$mrow = new MMLmrow( '', [],
			new MMLmo( '', [], '+' ),
			'<mo>&#x27F9;</mo>',
			new MMLmn( '', [], '2' )
		);

		$mrow->accept( $visitor );
		$output = $visitor->getHTML();

		$expected = <<<XML
		<mrow>
			<mo>+</mo>
			<mo>&#x27F9;</mo>
			<mn>2</mn>
		</mrow>
		XML;
		$this->assertXmlStringEqualsXmlString( $expected, $output );
	}

	public function testNestedXmlString() {
		$visitor = new MMLDomVisitor();
		$mrow = new MMLbase( 'mrow', '', [],
			'<msup><mi>x</mi><mn>2</mn></msup>'
		);

		$mrow->accept( $visitor );
		$output = $visitor->getHTML();

		$expected = '<mrow><msup><mi>x</mi><mn>2</mn></msup></mrow>';
		$this->assertXmlStringEqualsXmlString( $expected, $output );
	}

	public function testTextAndXmlCombination() {
		$visitor = new MMLDomVisitor();
		$node = new MMLmrow( '', [],
			new MMLmtext( '', [], 'Solve: ' ),
			'<mrow><mi>x</mi><mo>+</mo><mn>5</mn></mrow>'
		);

		$node->accept( $visitor );
		$output = $visitor->getHTML();

		$expected = <<<XML
		<mrow>
		<mtext>Solve: </mtext>
			<mrow>
				<mi>x</mi>
				<mo>+</mo>
				<mn>5</mn>
			</mrow>
		</mrow>
		XML;
		$this->assertXmlStringEqualsXmlString( $expected, $output );
	}

	public function testEmptyStringChild() {
		$visitor = new MMLDomVisitor();
		$mrow = new MMLmrow( '', [], '' );

		$mrow->accept( $visitor );
		$output = $visitor->getHTML();

		$this->assertSame( '<mrow></mrow>', trim( $output ) );
	}

	public function testHugeXmlDepthHandling() {
		$deepNesting = str_repeat( '<sub>', 500 ) . '&#x338;' . str_repeat( '</sub>', 500 );
		$mrow = new MMLbase( 'math', '', [], $deepNesting );
		$visitor = new MMLDomVisitor();
		$mrow->accept( $visitor );
		$output = $visitor->getHTML();

		$this->assertStringContainsString( '<sub><sub>', $output, 'Should contain nested tags' );
		$this->assertStringContainsString( '&#x338', $output, 'Should contain inner content' );
		$this->assertStringEndsWith( '</math>', $output, 'Should have proper closing' );

		$firstPos = strpos( $output, '<sub>' );
		$lastPos = strrpos( $output, '</sub>' );
		$inner = substr( $output, $firstPos, $lastPos - $firstPos );
		$nestingCount = substr_count( $inner, '<sub>' );

		$this->assertGreaterThan(
			256,
			$nestingCount,
			'Should handle more than default 256 nesting levels'
		);
	}

	public function testMMLarrayAsRoot() {
		$mrow = new MMLbase( 'mrow', '', [], 'Hello' );
		$array = new MMLarray( $mrow, '<mi>World</mi>' );
		$html = (string)$array;
		$this->assertStringContainsString( '<mrow>Hello</mrow>', $html );
		$this->assertStringContainsString( '<mi>World</mi>', $html );
	}

	public function testMMLarrayAsChild() {
		$array = new MMLarray( new MMLbase( 'mrow', '', [], 'Inner' ) );
		$parent = new MMLbase( 'mroot', '', [], $array );
		$html = (string)$parent;
		$this->assertStringContainsString( '<mroot><mrow>Inner</mrow></mroot>', $html );
	}

	public function testEmptyMMLarray() {
		$array = new MMLarray();
		$html = (string)$array;
		$this->assertSame( '', $html );
	}

	public function testMixedChildrenInArray() {
		$array = new MMLarray(
			new MMLmi( '', [], 'x' ),
			'<mn>3</mn>',
			null
		);
		$html = (string)$array;
		$this->assertStringContainsString( '<mi>x</mi>', $html );
		$this->assertStringContainsString( '<mn>3</mn>', $html );
	}

	public function testArrayRoot() {
		$array = new MMLarray(
			new MMLmi( '', [], 'x' ),
			'<mn>3</mn>',
			null
		);
		$visitor = new MMLDomVisitor();
		$this->expectException( InvalidArgumentException::class );
		$visitor->visit( $array );
	}
}
