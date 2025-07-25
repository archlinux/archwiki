<?php

namespace Cite;

use MediaWiki\Extension\CLDR\Alphabets;

/**
 * Wrapper for the CLDR Alphabets class
 *
 * @license GPL-2.0-or-later
 */
class AlphabetsProvider {

	private ?Alphabets $alphabet = null;

	public function __construct() {
		if ( class_exists( Alphabets::class ) ) {
			$this->alphabet = new Alphabets();
		}
	}

	/**
	 * Get alphabet for a locale or its fallbacks
	 *
	 * The index or main alphabet, found in CLDR main `characters.exemplarCharacters`,
	 * and reduced to a simple form for creating a sequence. Returns null when CLDR is not installed
	 *
	 * @param string $code Locale to query. If no entry exists, the fallback
	 * locales are iterated.
	 * @return string[]|null a sequence of symbols
	 */
	public function getIndexCharacters( string $code ): ?array {
		return $this->alphabet ? $this->alphabet->getIndexCharacters( $code ) : null;
	}
}
