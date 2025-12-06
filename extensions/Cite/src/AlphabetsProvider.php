<?php

namespace Cite;

use MediaWiki\Extension\CLDR\Alphabets;

/**
 * Wrapper for the CLDR Alphabets class
 *
 * @license GPL-2.0-or-later
 */
class AlphabetsProvider {

	public function __construct(
		private readonly ?Alphabets $alphabets,
	) {
	}

	/**
	 * Get alphabet for a locale or its fallbacks
	 *
	 * The index or main alphabet, found in CLDR main `characters.exemplarCharacters`,
	 * and reduced to a simple form for creating a sequence. Returns null when CLDR is not installed
	 *
	 * @param string $languageCode Locale to query. If no entry exists, the fallback
	 * locales are iterated.
	 * @return string[]|null a sequence of symbols
	 */
	public function getIndexCharacters( string $languageCode ): ?array {
		return $this->alphabets ? $this->alphabets->getIndexCharacters( $languageCode ) : null;
	}

}
