<?php

namespace Cite\Hooks;

use Cite\CiteFactory;
use MediaWiki\Api\ApiQuerySiteinfo;
use MediaWiki\Api\Hook\APIQuerySiteInfoGeneralInfoHook;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Hook\ParserClearStateHook;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\StripState;

/**
 * Hook handlers for Cite's integration with the (legacy) MediaWiki parser.
 *
 * @license GPL-2.0-or-later
 */
class CiteParserHooks implements
	APIQuerySiteInfoGeneralInfoHook,
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

	/**
	 * Expose configs via action=query&meta=siteinfo
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/APIQuerySiteInfoGeneralInfo
	 *
	 * @param ApiQuerySiteinfo $module
	 * @param array<string,mixed> &$results
	 * @return void
	 */
	public function onAPIQuerySiteInfoGeneralInfo( $module, &$results ) {
		$results['citeresponsivereferences'] = $module->getConfig()->get( 'CiteResponsiveReferences' );
	}

}
