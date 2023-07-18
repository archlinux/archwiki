<?php

namespace MediaWiki\Extension\Math\InputCheck;

use MediaWiki\Extension\Math\TexVC\Nodes\TexArray;
use MediaWikiIntegrationTestCase;
use Message;

/**
 * @group Math
 * @license GPL-2.0-or-later
 * tbd move this to unittests
 * @covers \MediaWiki\Extension\Math\InputCheck\LocalChecker
 */
class LocalCheckerTest extends MediaWikiIntegrationTestCase {
	public function testValid() {
		$checker = new LocalChecker( '\sin x^2' );
		$this->assertNull( $checker->getError() );
		$this->assertTrue( $checker->isValid() );
		$this->assertNull( $checker->getError() );
		$this->assertSame( '\\sin x^{2}', $checker->getValidTex() );
	}

	public function testValidTypeTex() {
		$checker = new LocalChecker( '\sin x^2', 'tex' );
		$this->assertTrue( $checker->isValid() );
	}

	public function testValidTypeChem() {
		$checker = new LocalChecker( '{\\displaystyle {\\ce {\\cdot OHNO_{2}}}}', 'chem' );
		$this->assertTrue( $checker->isValid() );
	}

	public function testValidTypeInline() {
		$checker = new LocalChecker( '{\\textstyle \\log2 }', 'inline-tex' );
		$this->assertTrue( $checker->isValid() );
	}

	public function testInvalidType() {
		$checker = new LocalChecker( '\sin x^2', 'INVALIDTYPE' );
		$this->assertInstanceOf( LocalChecker::class, $checker );
		$this->assertInstanceOf( Message::class, $checker->getError() );
		$this->assertFalse( $checker->isValid() );
		$this->assertNull( $checker->getParseTree() );
	}

	public function testInvalid() {
		$checker = new LocalChecker( '\sin\newcommand' );
		$this->assertFalse( $checker->isValid() );

		$this->assertStringContainsString(
			Message::newFromKey( 'math_unknown_function', '\newcommand' )
				->inContentLanguage()
				->escaped(),
			$checker->getError()
				->inContentLanguage()
				->escaped()
		);

		$this->assertNull( $checker->getValidTex() );
	}

	public function testErrorSyntax() {
		$checker = new LocalChecker( '\left(' );
		$this->assertFalse( $checker->isValid() );
		$this->assertStringContainsString(
			Message::newFromKey( 'math_syntax_error' )
				->inContentLanguage()
				->escaped(),
			$checker->getError()
				->inContentLanguage()
				->escaped()
		);
	}

	public function testGetParseTree() {
		$checker = new LocalChecker( 'e^{i \pi} + 1 = 0' );
		$this->assertTrue( $checker->isValid() );
		$parseTree = $checker->getParseTree();
		$this->assertInstanceOf( TexArray::class, $parseTree );
		$this->assertEquals( 5, $parseTree->getLength() );
	}

	public function testGetParseTreeNull() {
		$checker = new LocalChecker( '\invalid' );
		$this->assertFalse( $checker->isValid() );
		$this->assertNull( $checker->getParseTree() );
	}

	public function testGetParseTreeEmpty() {
		$checker = new LocalChecker( '' );
		$this->assertTrue( $checker->isValid() );
		$parseTree = $checker->getParseTree();
		$this->assertInstanceOf( TexArray::class, $parseTree );
		$this->assertSame( 0, $parseTree->getLength() );
	}
}
