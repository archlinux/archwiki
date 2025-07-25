<?php

namespace Cite;

use MediaWiki\Parser\Sanitizer;

/**
 * Compiles unique identifiers and formats them as anchors for use in `href="#…"` and `id="…"`
 * attributes.
 *
 * @license GPL-2.0-or-later
 */
class AnchorFormatter {

	/**
	 * Generates identifiers for use in backlinks and their targets to jump from the reference list
	 * back up to one of possibly many footnote markers in the article.
	 */
	public static function getBackLinkIdentifier( ?string $name, int $globalId, int $count ): string {
		// This intentionally drops "0" and such, that's invalid anyway
		if ( $name ) {
			$id = self::normalizeFragmentIdentifier( "{$name}_$globalId" );
			// TODO: Can we change this to use the number as it is, without decrementing?
			$id .= '-' . ( $count - 1 );
		} else {
			$id = $globalId;
		}
		return "cite_ref-$id";
	}

	/**
	 * @return string URL and HTML escaped to be used as part of a [[#…]] link in wikitext
	 */
	public function backLink( ?string $name, int $globalId, int $count ): string {
		$id = $this->getBackLinkIdentifier( $name, $globalId, $count );
		// This does both URL encoding (e.g. %A0, which only makes sense in href="…") and HTML
		// entity encoding (e.g. &#xA0;). The browser will decode in reverse order.
		return Sanitizer::safeEncodeAttribute( Sanitizer::escapeIdForLink( $id ) );
	}

	/**
	 * @return string Already escaped to be used directly in an id="…" attribute
	 */
	public function backLinkTarget( ?string $name, int $globalId, int $count ): string {
		$id = $this->getBackLinkIdentifier( $name, $globalId, $count );
		return Sanitizer::safeEncodeAttribute( $id );
	}

	/**
	 * Generates identifiers for use in reference links and their targets to jump from a footnote
	 * marker in the article down to the corresponding "note" item in the reference list.
	 */
	public static function getNoteIdentifier( ?string $name, int $globalId ): string {
		// This intentionally drops "0" and such, that's invalid anyway
		if ( $name ) {
			$id = self::normalizeFragmentIdentifier( $name ) . '-' . $globalId;
		} else {
			$id = $globalId;
		}
		return "cite_note-$id";
	}

	public static function getNoteTextIdentifier( ?string $name, int $globalId ): string {
		return 'mw-reference-text-' . self::getNoteIdentifier( $name, $globalId );
	}

	/**
	 * @return string URL and HTML escaped to be used as part of a [[#…]] link in wikitext
	 */
	public function jumpLink( ?string $name, int $globalId ): string {
		$id = $this->getNoteIdentifier( $name, $globalId );
		// This does both URL encoding (e.g. %A0, which only makes sense in href="…") and HTML
		// entity encoding (e.g. &#xA0;). The browser will decode in reverse order.
		return Sanitizer::safeEncodeAttribute( Sanitizer::escapeIdForLink( $id ) );
	}

	/**
	 * @return string Already escaped to be used directly in an id="…" attribute
	 */
	public function jumpLinkTarget( ?string $name, int $globalId ): string {
		$id = $this->getNoteIdentifier( $name, $globalId );
		return Sanitizer::safeEncodeAttribute( $id );
	}

	/**
	 * Normalizes and sanitizes anchor names for use in id="…" and <a href="#…"> attributes.
	 */
	private static function normalizeFragmentIdentifier( string $id ): string {
		// MediaWiki normalizes spaces and underscores in [[#…]] links, but not in id="…"
		// attributes. To make them behave the same we normalize in advance.
		return preg_replace( '/[_\s]+/u', '_', $id );
	}

}
