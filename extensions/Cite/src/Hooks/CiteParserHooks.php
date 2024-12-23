<?php

namespace Cite\Hooks;

use Cite\Cite;
use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserClearStateHook;
use MediaWiki\Hook\ParserClonedHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\StripState;

/**
 * @license GPL-2.0-or-later
 */
class CiteParserHooks implements
	ParserFirstCallInitHook,
	ParserClearStateHook,
	ParserClonedHook,
	ParserAfterParseHook
{

	private CiteParserTagHooks $citeParserTagHooks;

	public function __construct(
		Config $config
	) {
		$this->citeParserTagHooks = new CiteParserTagHooks( $config );
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
		$parser->extCite = null;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserCloned
	 *
	 * @param Parser $parser
	 */
	public function onParserCloned( $parser ) {
		$parser->extCite = null;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 *
	 * @param Parser $parser
	 * @param string &$text
	 * @param StripState $stripState
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		if ( isset( $parser->extCite ) ) {
			/** @var Cite $cite */
			$cite = $parser->extCite;
			$text .= $cite->checkRefsNoReferences( $parser, $parser->getOptions()->getIsSectionPreview() );
		}
	}

}
