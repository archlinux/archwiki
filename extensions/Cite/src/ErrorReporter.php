<?php

namespace Cite;

use InvalidArgumentException;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\Sanitizer;
use StatusValue;

/**
 * @license GPL-2.0-or-later
 */
class ErrorReporter {

	private ReferenceMessageLocalizer $messageLocalizer;
	private ?Language $cachedInterfaceLanguage = null;

	public function __construct( ReferenceMessageLocalizer $messageLocalizer ) {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * @deprecated Intermediate helper function. Long-term all errors should be rendered, not only
	 * the first one.
	 */
	public function firstError( Parser $parser, StatusValue $status ): string {
		$error = $status->getErrors()[0];
		return $this->halfParsed( $parser, $error['message'], ...$error['params'] );
	}

	/**
	 * @param Parser $parser
	 * @param string $key Message name of the error or warning
	 * @param mixed ...$params
	 *
	 * @return string Half-parsed wikitext with extension's tags already being expanded
	 */
	public function halfParsed( Parser $parser, string $key, ...$params ): string {
		$msg = $this->msg( $parser, $key, ...$params );
		$wikitext = $parser->recursiveTagParse( $msg->plain() );
		return $this->wrapInHtmlContainer( $wikitext, $key, $msg->getLanguage() );
	}

	/**
	 * @param Parser $parser
	 * @param string $key Message name of the error or warning
	 * @param mixed ...$params
	 *
	 * @return string Plain, unparsed wikitext
	 * @return-taint tainted
	 */
	public function plain( Parser $parser, string $key, ...$params ): string {
		$msg = $this->msg( $parser, $key, ...$params );
		$wikitext = $msg->plain();
		return $this->wrapInHtmlContainer( $wikitext, $key, $msg->getLanguage() );
	}

	/**
	 * @param Parser $parser
	 * @param string $key
	 * @param mixed ...$params
	 *
	 * @return Message
	 */
	private function msg( Parser $parser, string $key, ...$params ): Message {
		$language = $this->getInterfaceLanguageAndSplitCache( $parser->getOptions() );
		$msg = $this->messageLocalizer->msg( $key, ...$params )->inLanguage( $language );

		[ $type ] = $this->parseTypeAndIdFromMessageKey( $key );

		if ( $type === 'error' ) {
			// Take care; this is a sideeffect that might not belong to this class.
			$parser->addTrackingCategory( 'cite-tracking-category-cite-error' );
		}

		// Optional wrapper messages: cite_error, cite_warning
		$wrapper = $this->messageLocalizer->msg( "cite_$type", $msg->plain() )->inLanguage( $language );
		return $wrapper->isDisabled() ? $msg : $wrapper;
	}

	/**
	 * Note the startling side effect of splitting ParserCache by user interface language!
	 */
	private function getInterfaceLanguageAndSplitCache( ParserOptions $parserOptions ): Language {
		$this->cachedInterfaceLanguage ??= $parserOptions->getUserLangObj();
		return $this->cachedInterfaceLanguage;
	}

	private function wrapInHtmlContainer(
		string $wikitext,
		string $key,
		Language $language
	): string {
		[ $type, $id ] = $this->parseTypeAndIdFromMessageKey( $key );
		$extraClass = $type === 'warning'
			? ' mw-ext-cite-warning-' . Sanitizer::escapeClass( $id )
			: '';

		return Html::rawElement(
			'span',
			[
				// The following classes are generated here:
				// * mw-ext-cite-warning
				// * mw-ext-cite-error
				'class' => "$type mw-ext-cite-$type" . $extraClass,
				'lang' => $language->getHtmlCode(),
				'dir' => $language->getDir(),
			],
			$wikitext
		);
	}

	/**
	 * @param string $messageKey Expected to be a message key like "cite_error_ref_numeric_key"
	 *
	 * @return string[] Two elements, e.g. "error" and "ref_numeric_key"
	 */
	private function parseTypeAndIdFromMessageKey( string $messageKey ): array {
		if ( !preg_match( '/^cite.(error|warning).(.+)/i', $messageKey, $matches ) ) {
			throw new InvalidArgumentException( 'Unexpected message key' );
		}
		return array_slice( $matches, 1 );
	}

}
