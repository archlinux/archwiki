<?php

namespace Cite\Hooks;

use Cite\CiteFactory;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserClearStateHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\StripState;

/**
 * @license GPL-2.0-or-later
 */
class CiteParserHooks implements
	ParserFirstCallInitHook,
	ParserClearStateHook,
	ParserAfterParseHook
{

	private readonly CiteParserTagHooks $citeParserTagHooks;

	public function __construct(
		private readonly CiteFactory $citeFactory,
	) {
		$this->citeParserTagHooks = new CiteParserTagHooks( $citeFactory );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$this->citeParserTagHooks->register( $parser );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserClearState
	 *
	 * @param Parser $parser
	 */
	public function onParserClearState( $parser ) {
		$this->citeFactory->destroyCiteForParser( $parser );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 *
	 * @param Parser $parser
	 * @param string &$text
	 * @param StripState $stripState
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$cite = $this->citeFactory->peekCiteForParser( $parser );
		if ( $cite !== null ) {
			$text .= $cite->checkRefsNoReferences( $parser );
		}
	}

}
