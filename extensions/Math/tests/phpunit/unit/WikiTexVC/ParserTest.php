<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\Parser;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Parser
 */
class ParserTest extends MediaWikiUnitTestCase {

	/** @var Parser */
	private $parser;

	protected function setUp(): void {
		parent::setUp();
		$this->parser = new Parser();
	}

	public function provideTestCases() {
		yield [ '' ];
		yield [ 'a' ];
		yield [ 'a^2' ];
		yield [ 'a^2+b^{2}' ];
		yield [ 'l_a^2+l_b^2=l_c^2' ];
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testSimpleParse( string $input ) {
		$this->parser->parse( $input, [ 'debug' => true ] );
		$this->addToAssertionCount( 1 );
	}

	public function testExample() {
		$this->parser->parse( '\\sin(x)+{}{}\\cos(x)^2 newcommand', [ 'debug' => true ] );
		$this->addToAssertionCount( 1 );
	}

	public function testSpecific() {
		 $resultR = $this->parser->parse( '\\reals' );
		 $resultS = $this->parser->parse( '\\mathbb{R}' );
		 $this->assertEquals( $resultR, $resultS, 'Should parse texVC specific functions' );
	}
}
