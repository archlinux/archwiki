<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\CiteFactory;
use Cite\Hooks\CiteParserHooks;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\StripState;

/**
 * @covers \Cite\Hooks\CiteParserHooks
 * @license GPL-2.0-or-later
 */
class CiteParserHooksTest extends \MediaWikiUnitTestCase {

	private function newCiteParserHooks( ?CiteFactory $citeFactory = null ) {
		return new CiteParserHooks(
			$citeFactory ?? $this->createNoOpMock( CiteFactory::class )
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
		$citeFactory = $this->createMock( CiteFactory::class );
		$citeFactory->expects( $this->once() )
			->method( 'destroyCiteForParser' )->with( $parser );

		$citeParserHooks = $this->newCiteParserHooks( $citeFactory );
		$citeParserHooks->onParserClearState( $parser );
	}

	public function testAfterParseHooks() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'checkRefsNoReferences' );

		$parser = $this->createNoOpMock( Parser::class, [ 'getOutput' ] );
		$parser->method( 'getOutput' )
			->willReturn( $this->createMock( ParserOutput::class ) );
		$citeFactory = $this->createMock( CiteFactory::class );
		$citeFactory->method( 'peekCiteForParser' )->with( $parser )->willReturn( $cite );

		$text = '';
		$citeParserHooks = $this->newCiteParserHooks( $citeFactory );
		$citeParserHooks->onParserAfterParse( $parser, $text, $this->createMock( StripState::class ) );
	}

}
