<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\Hooks\CiteParserHooks;
use MediaWiki\Config\Config;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\StripState;

/**
 * @covers \Cite\Hooks\CiteParserHooks
 * @license GPL-2.0-or-later
 */
class CiteParserHooksTest extends \MediaWikiUnitTestCase {

	private function newCiteParserHooks() {
		return new CiteParserHooks(
			$this->createNoOpMock( Config::class )
		);
	}

	public function testOnParserFirstCallInit() {
		$parser = $this->createNoOpMock( Parser::class, [ 'setHook' ] );
		$expectedTags = [ 'ref' => true, 'references' => true ];
		$parser->expects( $this->exactly( 2 ) )
			->method( 'setHook' )
			->willReturnCallback( function ( $tag ) use ( &$expectedTags ) {
				$this->assertArrayHasKey( $tag, $expectedTags );
				unset( $expectedTags[$tag] );
			} );

		$citeParserHooks = $this->newCiteParserHooks();
		$citeParserHooks->onParserFirstCallInit( $parser );
	}

	public function testOnParserClearState() {
		$parser = $this->createNoOpMock( Parser::class, [ '__isset' ] );
		$parser->extCite = $this->createMock( Cite::class );

		$citeParserHooks = $this->newCiteParserHooks();
		$citeParserHooks->onParserClearState( $parser );

		$this->assertNull( $parser->extCite ?? null );
	}

	public function testOnParserCloned() {
		$parser = $this->createNoOpMock( Parser::class, [ '__isset' ] );
		$parser->extCite = $this->createMock( Cite::class );

		$citeParserHooks = $this->newCiteParserHooks();
		$citeParserHooks->onParserCloned( $parser );

		$this->assertNull( $parser->extCite ?? null );
	}

	public function testAfterParseHooks() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'checkRefsNoReferences' );

		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'getIsSectionPreview' )
			->willReturn( false );

		$parser = $this->createNoOpMock( Parser::class, [ 'getOptions', 'getOutput' ] );
		$parser->method( 'getOptions' )
			->willReturn( $parserOptions );
		$parser->method( 'getOutput' )
			->willReturn( $this->createMock( ParserOutput::class ) );
		$parser->extCite = $cite;

		$text = '';
		$citeParserHooks = $this->newCiteParserHooks();
		$citeParserHooks->onParserAfterParse( $parser, $text, $this->createMock( StripState::class ) );
	}

}
