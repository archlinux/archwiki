<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\Bcp47Code;

/**
 * This is a simple value object demonstrating the simplest possible
 * implementation of the Bcp47Code interface.
 */
class Bcp47CodeValue implements Bcp47Code {

	/**
	 * The BCP 47 code corresponding to this language.
	 */
	private string $code;

	/**
	 * Create a new instance of this value object representing a language with
	 * the given BCP 47 code.
	 * @param string $bcp47code the BCP 47 code for the language
	 */
	public function __construct( string $bcp47code ) {
		$this->code = $bcp47code;
	}

	/** @inheritDoc */
	public function toBcp47Code(): string {
		return $this->code;
	}

	/** @inheritDoc */
	public function isSameCodeAs( Bcp47Code $other ): bool {
		return ( $this === $other ) || self::isSameCode( $this, $other );
	}

	public function __toString(): string {
		return $this->toBcp47Code();
	}

	/**
	 * Simple helper to coerce any Bcp47Code into a Bcp47CodeValue.
	 * @param Bcp47Code $language an object representing a language
	 * @return Bcp47CodeValue a simple value object representing a language.
	 */
	public static function fromBcp47Code( Bcp47Code $language ): Bcp47CodeValue {
		if ( $language instanceof Bcp47CodeValue ) {
			return $language;
		}
		return new Bcp47CodeValue( $language->toBcp47Code() );
	}

	/**
	 * Simple helper to compare Bcp47Code in the proper case-insensitive
	 * manner.
	 * @param Bcp47Code $a
	 * @param Bcp47Code $b
	 * @return bool True if the bcp-47 codes should be considered equal
	 */
	public static function isSameCode( Bcp47Code $a, Bcp47Code $b ): bool {
		return ( $a === $b ) || strcasecmp( $a->toBcp47Code(), $b->toBcp47Code() ) === 0;
	}
}
