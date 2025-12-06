<?php

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;

/**
 * Static function collection for general extension support.
 */
class Scribunto {
	/**
	 * Get an engine instance for the given parser, and cache it in the parser
	 * so that subsequent calls to this function for the same parser will return
	 * the same engine.
	 *
	 * @param Parser $parser
	 * @return ScribuntoEngineBase
	 * @deprecated Use EngineFactory::getEngineForParser
	 */
	public static function getParserEngine( Parser $parser ) {
		/** @var EngineFactory $engineFactory */
		$engineFactory = MediaWikiServices::getInstance()->getService( 'Scribunto.EngineFactory' );
		'@phan-var EngineFactory $engineFactory';
		return $engineFactory->getEngineForParser( $parser );
	}

	/**
	 * Test whether the page should be considered a documentation page
	 *
	 * @param Title $title
	 * @param Title|null &$forModule Module for which this is a doc page
	 * @return bool
	 */
	public static function isDocPage( Title $title, ?Title &$forModule = null ) {
		$docPage = wfMessage( 'scribunto-doc-page-name' )->inContentLanguage();
		if ( $docPage->isDisabled() ) {
			return false;
		}

		// Canonicalize the input pseudo-title. The unreplaced "$1" shouldn't
		// cause a problem.
		$docTitle = Title::newFromText( $docPage->plain() );
		if ( !$docTitle ) {
			return false;
		}
		$docPage = $docTitle->getPrefixedText();

		// Make it into a regex, and match it against the input title
		$docPage = str_replace( '\\$1', '(.+)', preg_quote( $docPage, '/' ) );
		if ( preg_match( "/^$docPage$/", $title->getPrefixedText(), $m ) ) {
			$forModule = Title::makeTitleSafe( NS_MODULE, $m[1] );
			return $forModule !== null;
		} else {
			return false;
		}
	}

	/**
	 * Return the Title for the documentation page
	 *
	 * @param Title $title
	 * @return Title|null
	 */
	public static function getDocPage( Title $title ) {
		$docPage = wfMessage( 'scribunto-doc-page-name', $title->getText() )->inContentLanguage();
		if ( $docPage->isDisabled() ) {
			return null;
		}

		return Title::newFromText( $docPage->plain() );
	}
}
