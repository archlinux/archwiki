<?php

namespace Cite\Hooks;

use Cite\CiteFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * @license GPL-2.0-or-later
 */
class CiteParserTagHooks {

	public function __construct(
		private readonly CiteFactory $citeFactory,
	) {
	}

	/**
	 * Enables the two <ref> and <references> tags.
	 */
	public function register( Parser $parser ): void {
		$parser->setHook( 'ref', [ $this, 'ref' ] );
		$parser->setHook( 'references', [ $this, 'references' ] );
	}

	/**
	 * Parser hook for the <ref> tag.
	 *
	 * @param ?string $text Raw, untrimmed wikitext content of the <ref> tag, if any
	 * @param array<string,?string> $argv
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string HTML
	 */
	public function ref(
		?string $text,
		array $argv,
		Parser $parser,
		PPFrame $frame
	): string {
		$cite = $this->citeFactory->getCiteForParser( $parser );
		$result = $cite->ref( $parser, $text, $argv );

		if ( $result === null ) {
			return htmlspecialchars( "<ref>$text</ref>" );
		}

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( [ 'ext.cite.ux-enhancements' ] );
		$parserOutput->addModuleStyles( [ 'ext.cite.styles' ] );

		$frame->setVolatile();
		return $result;
	}

	/**
	 * Parser hook for the <references> tag.
	 *
	 * @param ?string $text Raw, untrimmed wikitext content of the <references> tag, if any
	 * @param array<string,?string> $argv
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string HTML
	 */
	public function references(
		?string $text,
		array $argv,
		Parser $parser,
		PPFrame $frame
	): string {
		$cite = $this->citeFactory->getCiteForParser( $parser );
		$result = $cite->references( $parser, $text, $argv );

		if ( $result === null ) {
			return htmlspecialchars( $text === null
				? "<references/>"
				: "<references>$text</references>"
			);
		}

		$frame->setVolatile();
		return $result;
	}
}
