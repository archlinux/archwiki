<?php
/**
 * Resets the page_random field for articles in the provided time range.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */

use MediaWiki\Diff\ComplexityException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Wikimedia\Bcp47Code\Bcp47Code;

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script that compares variant conversion output between Parser and
 * HtmlOutputRendererHelper.
 *
 * @ingroup Maintenance
 */
class CompareLanguageConverterOutput extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Compares variant conversion output between Parser and HtmlOutputRendererHelper' );
		$this->addArg(
			'page-title',
			'Name of the page to be parsed and compared',
			true
		);
		$this->addArg(
			'target-variant',
			'Target variant language code to transform the content to',
			true
		);
	}

	public function execute() {
		$mwInstance = MediaWikiServices::getInstance();

		$pageName = $this->getArg( 'page-title' );
		$pageTitle = Title::newFromText( $pageName );

		if ( !$pageTitle || !$pageTitle->exists() ) {
			$this->fatalError( "Title with name $pageName not found" );
		}

		$targetVariantCode = $this->getArg( 'target-variant' );
		$languageNameUtils = $mwInstance->getLanguageNameUtils();
		if ( !$languageNameUtils->isValidBuiltInCode( $targetVariantCode ) ) {
			$this->fatalError( "$targetVariantCode is not a supported variant" );
		}
		$targetVariant = $mwInstance->getLanguageFactory()->getLanguage(
			$targetVariantCode
		);

		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$baseLanguage = $pageTitle->getPageLanguage();

		$parserOutput = $this->getParserOutput( $pageTitle, $baseLanguage, $targetVariant );
		$parsoidOutput = $this->getParsoidOutput( $pageTitle, $targetVariant, $user );
		$converterUsed = $this->getConverterUsed( $parsoidOutput );

		$this->compareOutput( $parserOutput, $parsoidOutput, $converterUsed );

		return true;
	}

	private function newHtmlOutputRendererHelper(): HtmlOutputRendererHelper {
		$services = MediaWikiServices::getInstance();

		$helper = new HtmlOutputRendererHelper(
			$services->getParsoidOutputStash(),
			new NullStatsdDataFactory(),
			$services->getParsoidOutputAccess(),
			$services->getHtmlTransformFactory(),
			$services->getContentHandlerFactory(),
			$services->getLanguageFactory()
		);
		return $helper;
	}

	private function getParserOptions( Language $language ): ParserOptions {
		$parserOpts = ParserOptions::newFromAnon();
		$parserOpts->setTargetLanguage( $language );
		$parserOpts->disableContentConversion( false );
		$parserOpts->disableTitleConversion( false );

		return $parserOpts;
	}

	private function getParserOutput(
		Title $pageTitle,
		Language $baseLanguage,
		Language $targetVariant
	): ParserOutput {
		// We update the default language variant because we want Parser to
		// perform variant conversion to it.
		global $wgDefaultLanguageVariant;
		$wgDefaultLanguageVariant = $targetVariant->getCode();

		$mwInstance = MediaWikiServices::getInstance();

		$languageFactory = $mwInstance->getLanguageFactory();
		$parser = $mwInstance->getParser();
		$parserOptions = $this->getParserOptions(
			$languageFactory->getParentLanguage( $baseLanguage )
		);

		$content = $mwInstance->getRevisionLookup()
			->getRevisionByTitle( $pageTitle )
			->getContent( SlotRecord::MAIN );
		$wikiContent = ( $content instanceof TextContent ) ? $content->getText() : '';

		return $parser->parse( $wikiContent, $pageTitle, $parserOptions );
	}

	private function getParsoidOutput(
		Title $pageTitle,
		Bcp47Code $targetVariant,
		User $user
	): ParserOutput {
		$htmlOutputRendererHelper = $this->newHtmlOutputRendererHelper();
		$htmlOutputRendererHelper->init( $pageTitle, [
			'stash' => false,
			'flavor' => 'view',
		], $user );
		$htmlOutputRendererHelper->setVariantConversionLanguage( $targetVariant );

		return $htmlOutputRendererHelper->getHtml();
	}

	private function getWords( string $output ): array {
		$tagsRemoved = strip_tags( $output );
		$words = preg_split( '/\s+/', trim( $tagsRemoved ), -1, PREG_SPLIT_NO_EMPTY );
		return $words;
	}

	private function getBody( string $output ): string {
		$dom = new DOMDocument();
		// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
		@$dom->loadHTML( $output );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body === null ) {
			// Body element not present
			return $output;
		}

		return $body->textContent;
	}

	private function compareOutput(
		ParserOutput $parserOutput,
		ParserOutput $parsoidOutput,
		string $converterUsed
	): void {
		$parsoidText = $parsoidOutput->getText( [ 'deduplicateStyles' => false ] );
		$parserText = $parserOutput->getText( [ 'deduplicateStyles' => false ] );

		$parsoidWords = $this->getWords( $this->getBody( $parsoidText ) );
		$parserWords = $this->getWords( $parserText );

		$parserWordCount = count( $parserWords );
		$parsoidWordCount = count( $parsoidWords );
		$this->output( "Word count: Parsoid: $parsoidWordCount; Parser: $parserWordCount\n" );

		$this->outputSimilarity( $parsoidWords, $parserWords );
		$this->output( "\n" );
		$this->outputDiff( $parsoidWords, $parserWords, $converterUsed );
	}

	private function getConverterUsed( ParserOutput $parsoidOutput ): string {
		$isCoreConverterUsed = strpos(
			$parsoidOutput->getRawText(),
			'Variant conversion performed using the core LanguageConverter'
		);

		if ( $isCoreConverterUsed ) {
			return 'Core LanguageConverter';
		} else {
			return 'Parsoid LanguageConverter';
		}
	}

	// Inspired from: https://stackoverflow.com/a/55927237/903324
	private function mb_sprintf( string $format, ...$args ): string {
		$params = $args;

		return sprintf(
			preg_replace_callback(
				'/(?<=%|%-)\d+(?=s)/',
				static function ( array $matches ) use ( &$params ) {
					$value = array_shift( $params );

					return (string)( strlen( $value ) - mb_strlen( $value ) + $matches[0] );
				},
				$format
			),
			...$args
		);
	}

	private function outputSimilarity( array $parsoidWords, array $parserWords ): void {
		$parsoidOutput = implode( ' ', $parsoidWords );
		$parserOutput = implode( ' ', $parserWords );
		$this->output(
			'Total characters: Parsoid: ' . strlen( $parsoidOutput ) .
			'; Parser: ' . strlen( $parserOutput ) . "\n"
		);

		$similarityPercent = 0;
		$similarCharacters = similar_text( $parsoidOutput, $parserOutput, $similarityPercent );
		$similarityPercent = round( $similarityPercent, 2 );

		$this->output(
			"Similarity via similar_text(): $similarityPercent%; Similar characters: $similarCharacters"
		);
	}

	private function outputDiff( array $parsoidWords, array $parserWords, string $converterUsed ): void {
		$out = str_repeat( '-', 96 ) . "\n";
		$out .= sprintf( "| %5s | %-35s | %-35s | %-8s |\n", 'Line', 'Parsoid', 'Parser', 'Diff' );
		$out .= sprintf( "| %5s | %-35s | %-35s | %-8s |\n", '', "($converterUsed)", '', '' );
		$out .= str_repeat( '-', 96 ) . "\n";

		try {
			$diff = new Diff( $parsoidWords, $parserWords );
		} catch ( ComplexityException $e ) {
			$this->output( $e->getMessage() );
			$this->error( 'Encountered ComplexityException while computing diff' );
		}

		// Print the difference between the words
		$wordDiffFormat = ( new ArrayDiffFormatter() )->format( $diff );
		foreach ( $wordDiffFormat as $index => $wordDiff ) {
			$action = $wordDiff['action'];
			$old = $wordDiff['old'] ?? null;
			$new = $wordDiff['new'] ?? null;

			$out .= $this->mb_sprintf(
				"| %5s | %-35s | %-35s | %-8s |\n",
				str_pad( (string)( $index + 1 ), 5, ' ', STR_PAD_LEFT ),
				mb_strimwidth( $old ?? '- N/A -', 0, 35, '…' ),
				mb_strimwidth( $new ?? '- N/A -', 0, 35, '…' ),
				$action
			);
		}

		// Print the footer.
		$out .= str_repeat( '-', 96 ) . "\n";
		$this->output( "\n" . $out );
	}
}

$maintClass = CompareLanguageConverterOutput::class;
require_once RUN_MAINTENANCE_IF_MAIN;
