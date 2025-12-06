<?php

namespace MediaWiki\Extension\TextExtracts;

use DOMElement;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * Provides text-only or limited-HTML extracts of page HTML
 *
 * @license GPL-2.0-or-later
 */
class ExtractFormatter {
	public const SECTION_MARKER_START = "\1\2";
	public const SECTION_MARKER_END = "\2\1";

	/**
	 * @var DOMElement
	 */
	private $body;

	/**
	 * @var string[]
	 */
	private $itemsToRemove = [
		'img',
		'audio',
		'video',
	];

	/**
	 * @param string $html HTML to be formatted
	 */
	public function __construct( $html ) {
		$doc = DOMUtils::parseHTML( $html );
		$this->body = DOMCompat::getBody( $doc );
	}

	/**
	 * Adds one or more selector of content to remove.
	 *
	 * @param string[]|string $selectors Selector(s) of stuff to remove
	 */
	public function remove( $selectors ): void {
		$this->itemsToRemove = array_merge( $this->itemsToRemove, (array)$selectors );
	}

	/**
	 * Get plaintext
	 *
	 * @return string
	 */
	public function getText(): string {
		$this->filterContent();

		// Insert section markers before headings in the DOM
		$headings = DOMCompat::querySelectorAll( $this->body, 'h1, h2, h3, h4, h5, h6' );
		foreach ( $headings as $heading ) {
			$i = substr( $heading->tagName, 1 );
			$marker = $this->body->ownerDocument->createTextNode(
				"\n\n" . self::SECTION_MARKER_START . $i . self::SECTION_MARKER_END
			);
			$heading->parentNode->insertBefore( $marker, $heading );
		}

		$text = $this->body->textContent;
		// Replace nbsp with space
		$text = str_replace( "\u{00A0}", ' ', $text );
		// Normalize Windows linebreaks
		$text = str_replace( "\r", "\n", $text );
		// Normalize multiple linebreaks
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Get filtered HTML
	 *
	 * @return string
	 */
	public function getHtml(): string {
		$this->filterContent();

		// Flatten <a> tags
		$links = DOMCompat::querySelectorAll( $this->body, 'a' );
		foreach ( $links as $a ) {
			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			while ( $child = $a->firstChild ) {
				$a->parentNode->insertBefore( $child, $a );
			}
			$a->parentNode->removeChild( $a );
		}

		$html = DOMCompat::getInnerHTML( $this->body );

		return trim( $html );
	}

	/**
	 * Removes content we've chosen to remove then removes class and style
	 * attributes from the remaining span elements.
	 *
	 * @return void
	 */
	public function filterContent(): void {
		foreach ( $this->itemsToRemove as $selector ) {
			$elements = DOMCompat::querySelectorAll( $this->body, $selector );
			foreach ( $elements as $el ) {
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
			}
		}

		// Remove class and style attributes from all span elements
		$spans = $this->body->getElementsByTagName( 'span' );
		foreach ( $spans as $span ) {
			$span->removeAttribute( 'class' );
			$span->removeAttribute( 'style' );
		}
	}
}
