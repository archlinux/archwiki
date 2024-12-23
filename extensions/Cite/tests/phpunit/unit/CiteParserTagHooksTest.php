<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\Hooks\CiteParserTagHooks;
use MediaWiki\Config\Config;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;

/**
 * @covers \Cite\Hooks\CiteParserTagHooks
 * @license GPL-2.0-or-later
 */
class CiteParserTagHooksTest extends \MediaWikiUnitTestCase {

	private function newCiteParserTagHooks() {
		return new CiteParserTagHooks(
			$this->createNoOpMock( Config::class )
		);
	}

	public function testRegister() {
		$parser = $this->createNoOpMock( Parser::class, [ 'setHook' ] );
		$expectedTags = [ 'ref' => true, 'references' => true ];
		$parser->expects( $this->exactly( 2 ) )
			->method( 'setHook' )
			->willReturnCallback( function ( $tag ) use ( &$expectedTags ) {
				$this->assertArrayHasKey( $tag, $expectedTags );
				unset( $expectedTags[$tag] );
			} );

		$citeParserTagHooks = $this->newCiteParserTagHooks();
		$citeParserTagHooks->register( $parser );
	}

	public function testRef_fails() {
		$cite = $this->createMock( Cite::class );
		$cite->method( 'ref' )
			->willReturn( null );

		$parser = $this->createNoOpMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$citeParserTagHooks = $this->newCiteParserTagHooks();
		$html = $citeParserTagHooks->ref( null, [], $parser, $frame );
		$this->assertSame( '&lt;ref&gt;&lt;/ref&gt;', $html );
	}

	public function testRef() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'ref' )
			->willReturn( '<HTML>' );

		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->expects( $this->once() )
			->method( 'addModules' );
		$parserOutput->expects( $this->once() )
			->method( 'addModuleStyles' );

		$parser = $this->createNoOpMock( Parser::class, [ 'getOutput' ] );
		$parser->method( 'getOutput' )
			->willReturn( $parserOutput );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$citeParserTagHooks = $this->newCiteParserTagHooks();
		$html = $citeParserTagHooks->ref( null, [], $parser, $frame );
		$this->assertSame( '<HTML>', $html );
	}

	public function testReferences_fails() {
		$cite = $this->createMock( Cite::class );
		$cite->method( 'references' )
			->willReturn( null );

		$parser = $this->createNoOpMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$citeParserTagHooks = $this->newCiteParserTagHooks();
		$html = $citeParserTagHooks->references( null, [], $parser, $frame );
		$this->assertSame( '&lt;references/&gt;', $html );
	}

	public function testReferences() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'references' )
			->willReturn( '<HTML>' );

		$parser = $this->createNoOpMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$citeParserTagHooks = $this->newCiteParserTagHooks();
		$html = $citeParserTagHooks->references( null, [], $parser, $frame );
		$this->assertSame( '<HTML>', $html );
	}

}
