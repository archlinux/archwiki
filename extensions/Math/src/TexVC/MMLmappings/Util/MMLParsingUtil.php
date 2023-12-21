<?php

namespace MediaWiki\Extension\Math\TexVC\MMLmappings\Util;

use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmtext;

/**
 * This class contains functionalities for MML-node
 * parsing which can be extracted and are used
 * for multiple functions.
 */
class MMLParsingUtil {

	public static function getFontArgs( $name, $variant, $passedArgs ) {
		$args = [];
		switch ( $name ) {
			case "cal":
			case "mathcal":
				$args = [ Tag::MJXVARIANT => "-tex-calligraphic", "mathvariant" => Variants::SCRIPT ];
				break;
			case "it":
			case "mathit":
				$args = [ Tag::MJXVARIANT => $variant, "mathvariant" => Variants::ITALIC ];
				break;
			case "bf":
			case "mathbf":
				$args = [ "mathvariant" => $variant ];
				break;
			// Sstatements from here come from other fct ok ? otherwise create second fct
			case "textit":
				$args = [ "mathvariant" => Variants::ITALIC ];
				break;
			case "textbf":
				$args = [ "mathvariant" => Variants::BOLD ];
				break;
			case "textsf":
				$args = [ "mathvariant" => Variants::SANSSERIF ];
				break;
			case "texttt":
				$args = [ "mathvariant" => Variants::MONOSPACE ];
				break;
			case "textrm":
				break;
			case "emph":
				// Toggle by passed args in emph
				if ( isset( $passedArgs["mathvariant"] ) ) {
					if ( $passedArgs["mathvariant"] === Variants::ITALIC ) {
						$args = [ "mathvariant" => Variants::NORMAL ];
					}
				} else {
					$args = [ "mathvariant" => Variants::ITALIC ];
				}
				break;
			default:
				$args = [ "mathvariant" => $variant ];

		}
		return $args;
	}

	/**
	 * Parses an expression that defines a color; this is usually an argument in Literal.
	 * Example expression is: "\definecolor {ultramarine}{rgb}{0,0.12549019607843,0.37647058823529}"
	 * @param string $input tex-string, which contains the expression
	 * @return array|null either an array which contains hex of parsed expression or null if not parsable
	 */
	public static function parseDefineColorExpression( string $input ): ?array {
		$returnObj = null;
		$matches = [];
		$matched = preg_match_all( '/\{(.*?)\}/', $input, $matches );
		if ( !$matched ) {
			return null;
		}
		$ctr = count( $matches[1] ?? [] );

		if ( $ctr == 3 && $matches[1][1] === "rgb" ) {
			$returnObj = [];
			$rgbValues = explode( ",", $matches[1][2] );
			$r  = round( floatval( $rgbValues[0] ) * 255 );
			$g = round( floatval( $rgbValues[1] ) * 255 );
			$b = round( floatval( $rgbValues[2] ) * 255 );
			$color = sprintf( "#%02x%02x%02x", $r, $g, $b );
			$returnObj["name"] = $matches[1][0];
			$returnObj["type"] = "rgb";
			$returnObj["hex"] = $color;
		}

		return $returnObj;
	}

	/**
	 * Creates a negation block in MathML, usually preceding the negated statement
	 * @return string negation block as MathML
	 */
	public static function createNot() {
		$mmlMrow = new MMLmrow( TexClass::REL );
		$mpadded = new MMLmpadded( "", [ "width" => "0" ] );
		$mtext = new MMLmtext();
		return $mmlMrow->encapsulateRaw( $mpadded->encapsulateRaw( $mtext->encapsulateRaw( "&#x29F8;" ) ) );
	}
}
