<?php
namespace MediaWiki\Extension\Math\TexVC\MMLmappings\Util;

use IntlChar;

/**
 * Utility Methods for parsing Tex to MathML
 * @author Johannes Stegmüller
 */
class MMLutil {
	/**
	 * Splits a regular expression in the form '\operatorname {someparams}
	 * @param string $input tex expression
	 * @return array|null found groups or null
	 */
	public static function initalParseLiteralExpression( $input ): ?array {
		$pattern = "/([\\a-zA-Z\s]+)\{([^}]+)\}/";
		$matches = [];
		$matched = preg_match_all( $pattern, $input, $matches );
		return $matched ? $matches : null;
	}

	/**
	 * Recognize if input is a unicode string "\\u1235"
	 * If yes converts it to notation "&#x123;", if no just returns the input.
	 * @param string $input input to be checked
	 * @return string modified input or input
	 */
	public static function uc2xNotation( string $input ): string {
		if ( str_starts_with( $input, "\\u" ) ) {
			return str_replace( "\\u", "&#x", $input ) . ";";
		}
		return $input;
	}

	/**
	 * If an input string is in "&#x12..:" notation
	 * then convert it to a string of notation \\u12..
	 * @param string $input input to be checked
	 * @return string modified input or input
	 */
	public static function x2uNotation( string $input ): string {
		if ( str_starts_with( $input, "&#x" ) ) {
			return rtrim( str_replace( "&#x", "\\u", $input ), ";" );
		}
		return $input;
	}

	public static function number2xNotation( $input ): string {
		return "&#x" . $input . ";";
	}

	/**
	 * Rounds a floating point input to three digits precision
	 * and returns it as string with succeeding "em".
	 * @param float $size input to be processed
	 * @return string rounded digits with em
	 */
	public static function round2em( float $size ) {
		$rounded = round( $size, 3 );
		return $rounded . "em";
	}

	/**
	 * In a floating point digit as string, set input to precision of three digits
	 * without rounding.
	 * @param string $size input to be checked
	 * @return string digits of precision three with em
	 */
	public static function size2em( string $size ): string {
		return preg_replace( "/(\.\d\d\d).+/", '$1', $size ) . "em";
	}

	/**
	 * Some common steps of processing an input string before passing it as a key to the mappings.
	 * @param string $input string to be processed
	 * @return string prepared input string
	 */
	public static function inputPreparation( $input ): string {
		$input = trim( $input );
		if ( str_starts_with( $input, "\\" ) && strlen( $input ) >= 2 ) {
			$input = substr( $input, 1 );
			// This is an edge case where S can be a Literal OR an Operator
			if ( $input === "S" ) {
				$input = "\\S";
			}
		}
		return $input;
	}

	public static function createEntity( $code ): ?string {
		return IntlChar::chr( intval( $code, 16 ) );
	}

	/**
	 * From a defined mapping table get the value by key.
	 * Do some optional common conversion steps for the values.
	 * @param string $key key to find entry in table
	 * @param array $mappingTable table which the value is found by key
	 * @param bool $convertUc2X convert first value of the found entry in array to specific "&#x123;" notation
	 * @return mixed|string[]|null the found entry in the table
	 */
	public static function getMappingByKey( string $key, array $mappingTable, bool $convertUc2X = false ) {
		if ( isset( $mappingTable[$key] ) ) {
			$found = $mappingTable[$key];
			if ( is_string( $found ) ) {
				$found = [ $found ];
			}
			if ( $convertUc2X ) {
				$found[0] = self::uc2xNotation( $found[0] );
			}
			return $found;
		}
		return null;
	}

	/**
	 * From a defined mapping table get the value by key.
	 * @param string $key key to find entry in table
	 * @param array $mappingTable table which the value is found by key
	 * @return mixed|string[]|null the found entry in the table
	 */
	public static function getMappingByKeySimple( string $key, array $mappingTable ) {
		if ( isset( $mappingTable[$key] ) ) {
			return $mappingTable[$key];
		}
		return null;
	}

}
