<?php

namespace Wikimedia\LangConv;

use DOMDocument;
use DOMDocumentFragment;
use DOMNode;

/**
 * A machine to convert and/or replace text.
 */
abstract class ReplacementMachine {
	/**
	 * ReplacementMachine constructor.
	 */
	public function __construct() {
	}

	/**
	 * Return the set of language codes supported.  Both key and value are
	 * set in order to facilitate inclusion testing.
	 *
	 * @return array<string,string>
	 */
	abstract public function getCodes();

	/**
	 * Override this method in subclass if you want to limit the possible code pairs bracketed.
	 * (For example, zh has a large number of variants, but we typically want to use only a limited
	 * number of these as possible invert codes.)
	 * @param string $destCode
	 * @param string $invertCode
	 * @return bool whether this is a valid bracketing pair.
	 */
	public function isValidCodePair( $destCode, $invertCode ) {
		return true;
	}

	/**
	 * Replace the given text Node with converted text, protecting any markup which can't be
	 * round-tripped back to `invertCode` with appropriate synthetic language-converter markup.
	 * @param DOMNode $textNode
	 * @param string $destCode
	 * @param string $invertCode
	 * @return DOMNode
	 */
	public function replace( $textNode, $destCode, $invertCode ) {
		$fragment = $this->convert(
			$textNode->ownerDocument,
			$textNode->textContent,
			$destCode,
			$invertCode
		);
		// Was a change made?
		$next = $textNode->nextSibling;
		if (
			// `fragment` has exactly 1 child.
			$fragment->firstChild && !$fragment->firstChild->nextSibling &&
			// `fragment.firstChild` is a DOM text node
			$fragment->firstChild->nodeType === XML_TEXT_NODE &&
			// `textNode` is a DOM text node
			$textNode->nodeType === XML_TEXT_NODE &&
			$textNode->textContent === $fragment->firstChild->textContent
		) {
			return $next; // No change.
		}
		// Poor man's `$textNode->replaceWith($fragment)`; use the
		// actual DOM method if/when we switch to a proper DOM implementation
		$parentNode = $textNode->parentNode;
		if ( $fragment->firstChild ) { # fragment could be empty!
			$parentNode->insertBefore( $fragment, $textNode );
		}
		$parentNode->removeChild( $textNode );

		return $next;
	}

	/**
	 * Convert a string of text.
	 * @param DOMDocument $document
	 * @param string $s text to convert
	 * @param string $destCode destination language code
	 * @param string $invertCode
	 * @return DOMDocumentFragment DocumentFragment containing converted text
	 */
	abstract public function convert( $document, $s, $destCode, $invertCode );

	/**
	 * Allow client to customize the JSON encoding of data-mw-variant
	 * attributes.
	 * @param array $obj The structured attribute value to encode
	 * @return string The encoded attribute value
	 */
	public function jsonEncode( array $obj ): string {
		return json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

}
