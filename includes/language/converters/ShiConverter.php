<?php
/**
 * Shilha specific code.
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
 * @ingroup Language
 */

/**
 * Conversion script between Latin and Tifinagh for Tachelhit.
 * - Tifinagh -> lowercase Latin
 * - lowercase/uppercase Latin -> Tifinagh
 *
 *
 * Based on:
 *   - https://en.wikipedia.org/wiki/Shilha_language
 *   - LanguageSr.php
 *
 * @ingroup Language
 */
class ShiConverter extends LanguageConverterSpecific {
	// The Tifinagh alphabet sequence is based on
	// "Dictionnaire Général de la Langue Amazighe Informatisé"
	// by IRCAM (https://tal.ircam.ma/dglai/lexieam.php, DGLAi),
	// with the labio-velarization mark in the end
	public $mToLatin = [
		'ⴰ' => 'a',
		'ⴱ' => 'b',
		'ⴳ' => 'g',
		'ⴷ' => 'd',
		'ⴹ' => 'ḍ',
		'ⴻ' => 'e',
		'ⴼ' => 'f',
		'ⴽ' => 'k',
		'ⵀ' => 'h',
		'ⵃ' => 'ḥ',
		'ⵄ' => 'ɛ',
		'ⵅ' => 'x',
		'ⵇ' => 'q',
		'ⵉ' => 'i',
		'ⵊ' => 'j',
		'ⵍ' => 'l',
		'ⵎ' => 'm',
		'ⵏ' => 'n',
		'ⵓ' => 'u',
		'ⵔ' => 'r',
		'ⵕ' => 'ṛ',
		'ⵖ' => 'ɣ',
		'ⵙ' => 's',
		'ⵚ' => 'ṣ',
		'ⵛ' => 'c',
		'ⵜ' => 't',
		'ⵟ' => 'ṭ',
		'ⵡ' => 'w',
		'ⵢ' => 'y',
		'ⵣ' => 'z',
		'ⵥ' => 'ẓ',
		'ⵯ' => 'ʷ',
	];

	// The sequence is based on DGLAi, with the non-standard letters in the end
	public $mUpperToLowerCaseLatin = [
		'A' => 'a',
		'B' => 'b',
		'G' => 'g',
		'D' => 'd',
		'Ḍ' => 'ḍ',
		'E' => 'e',
		'F' => 'f',
		'K' => 'k',
		'H' => 'h',
		'Ḥ' => 'ḥ',
		'Ɛ' => 'ɛ',
		'X' => 'x',
		'Q' => 'q',
		'I' => 'i',
		'J' => 'j',
		'L' => 'l',
		'M' => 'm',
		'N' => 'n',
		'U' => 'u',
		'R' => 'r',
		'Ṛ' => 'ṛ',
		'Ɣ' => 'ɣ',
		'S' => 's',
		'Ṣ' => 'ṣ',
		'C' => 'c',
		'T' => 't',
		'Ṭ' => 'ṭ',
		'W' => 'w',
		'Y' => 'y',
		'Z' => 'z',
		'Ẓ' => 'ẓ',
		'O' => 'o',
		'P' => 'p',
		'V' => 'v',
	];

	// The sequence is based on DGLAi, with the labio-velarization mark and
	// the non-standard letters in the end
	public $mToTifinagh = [
		'a' => 'ⴰ',
		'b' => 'ⴱ',
		'g' => 'ⴳ',
		'd' => 'ⴷ',
		'ḍ' => 'ⴹ',
		'e' => 'ⴻ',
		'f' => 'ⴼ',
		'k' => 'ⴽ',
		'h' => 'ⵀ',
		'ḥ' => 'ⵃ',
		'ɛ' => 'ⵄ',
		'x' => 'ⵅ',
		'q' => 'ⵇ',
		'i' => 'ⵉ',
		'j' => 'ⵊ',
		'l' => 'ⵍ',
		'm' => 'ⵎ',
		'n' => 'ⵏ',
		'u' => 'ⵓ',
		'r' => 'ⵔ',
		'ṛ' => 'ⵕ',
		'ɣ' => 'ⵖ',
		's' => 'ⵙ',
		'ṣ' => 'ⵚ',
		'c' => 'ⵛ',
		't' => 'ⵜ',
		'ṭ' => 'ⵟ',
		'w' => 'ⵡ',
		'y' => 'ⵢ',
		'z' => 'ⵣ',
		'ẓ' => 'ⵥ',
		'ʷ' => 'ⵯ',
		'o' => 'ⵓ',
		'p' => 'ⴱ',
		'v' => 'ⴼ',
	];

	/**
	 * Get main language code.
	 * @since 1.36
	 *
	 * @return string
	 */
	public function getMainCode(): string {
		return 'shi';
	}

	/**
	 * Get supported variants of the language.
	 * @since 1.36
	 *
	 * @return array
	 */
	public function getLanguageVariants(): array {
		return [ 'shi', 'shi-tfng', 'shi-latn' ];
	}

	/**
	 * Get language variants fallbacks.
	 * @since 1.36
	 *
	 * @return array
	 */
	public function getVariantsFallbacks(): array {
		return [
			'shi' => 'shi-tfng',
			'shi-tfng' => 'shi',
			'shi-latn' => 'shi',
		];
	}

	protected function loadDefaultTables() {
		$this->mTables = [
			'lowercase' => new ReplacementArray( $this->mUpperToLowerCaseLatin ),
			'shi-tfng' => new ReplacementArray( $this->mToTifinagh ),
			'shi-latn' => new ReplacementArray( $this->mToLatin ),
			'shi' => new ReplacementArray()
		];
	}

	/**
	 * It translates text into variant
	 *
	 * @param string $text
	 * @param string $toVariant
	 *
	 * @return string
	 */
	public function translate( $text, $toVariant ) {
		// If $text is empty or only includes spaces, do nothing
		// Otherwise translate it
		if ( trim( $text ) ) {
			$this->loadTables();
			// To Tifinagh, first translate uppercase to lowercase Latin
			if ( $toVariant == 'shi-tfng' ) {
				$text = $this->mTables['lowercase']->replace( $text );
			}
			$text = $this->mTables[$toVariant]->replace( $text );
		}
		return $text;
	}
}
