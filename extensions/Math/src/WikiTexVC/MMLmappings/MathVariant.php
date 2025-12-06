<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLmappings;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;

class MathVariant {
	/** @var self|null */
	private static ?MathVariant $instance = null;
	private array $mathVariants;

	/**
	 * @return false|string
	 */
	public static function getJsonFile() {
		return file_get_contents( __DIR__ . '/mathvariant.json' );
	}

	/**
	 * Reads the JSON file to an object
	 * @return array
	 */
	private function getJSON(): array {
		$file = self::getJsonFile();
		return json_decode( $file, true );
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->mathVariants = $this->getJSON();
	}

	public static function getInstance(): MathVariant {
		if ( self::$instance == null ) {
			self::$instance = new MathVariant();
		}

		return self::$instance;
	}

	/**
	 * Translates a string using the specified math variant.
	 * The MathML full spec defines several math variants, such as 'bold', 'italic', etc.
	 * @see https://www.w3.org/TR/mathml4/#presm_mi_att
	 * However, the MathML core spec only allows 'normal' as a mathvariant attribute value.
	 * Therefore, this method translates the characters in the input string to the respective variant characters.
	 * If no translation is found, the original character remains.
	 */
	public static function translate( string $input, string $variant ): string {
		$variants = self::getInstance()->mathVariants;
		if ( !isset( $variants[$variant] ) ) {
			throw new InvalidArgumentException( "Variant '$variant' does not exist." );
		}
		$in = array_keys( $variants[$variant] );
		$out = array_values( $variants[$variant] );
		return str_replace( $in, $out, $input );
	}

	/**
	 * Removes the mathvariant attribute from the MathML element attributes array,
	 * if it is set to 'normal', which is the only allowed value in MathML core.
	 * @see https://www.w3.org/TR/mathml-core/#dfn-mathvariant
	 */
	public static function removeMathVariantAttribute( array &$attributes ): string {
		if ( isset( $attributes['mathvariant'] )
			&& $attributes['mathvariant'] !== Variants::NORMAL ) {
			$variant = $attributes['mathvariant'];
			unset( $attributes['mathvariant'] );
			return $variant;
		}
		return 'normal';
	}

	#

	/**
	 * @internal For use by tests only.
	 */
	public static function tearDown(): void {
		self::$instance = null;
	}

}
