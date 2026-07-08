<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;

/**
 * This class contains functionalities for MML-node
 * parsing which can be extracted and are used
 * for multiple functions.
 */
class MMLParsingUtil {
	public static function renderApplyFunction(): MMLbase {
		return new MMLmo( "", [], "&#x2061;" );
	}

	public static function getFontArgs( string $name, ?string $variant, ?array $passedArgs ): array {
		$args = [];
		switch ( trim( $name, " \n\r\t\v\0\\" ) ) {
			case "cal":
			case "mathcal":
				$args = [ Tag::MJXVARIANT => "-tex-calligraphic", 'mathvariant' => Variants::SCRIPT ];
				break;
			case "it":
			case "mathit":
				$args = [ Tag::MJXVARIANT => $variant, 'mathvariant' => Variants::ITALIC ];
				break;
			case "bf":
			case "mathbf":
				$args = [ 'mathvariant' => $variant ];
				break;
			// Sstatements from here come from other fct ok ? otherwise create second fct
			case "textit":
				$args = [ 'mathvariant' => Variants::ITALIC ];
				break;
			case "textbf":
				$args = [ 'mathvariant' => Variants::BOLD ];
				break;
			case "textsf":
				$args = [ 'mathvariant' => Variants::SANSSERIF ];
				break;
			case "texttt":
				$args = [ 'mathvariant' => Variants::MONOSPACE ];
				break;
			case "textrm":
				break;
			case "mathrm":
				// Bold always has precedence
				$passedVariant = $passedArgs['mathvariant'] ?? '';
				if ( str_contains( $passedVariant, 'bold' ) ) {
					$args = [ 'mathvariant' => $passedVariant ];
					break;
				}
				$args = [ 'mathvariant' => Variants::NORMAL ];
				break;
			case "emph":
				// Toggle by passed args in emph
				if ( isset( $passedArgs['mathvariant'] ) ) {
					if ( $passedArgs['mathvariant'] === Variants::ITALIC ) {
						$args = [ 'mathvariant' => Variants::NORMAL ];
					}
				} else {
					$args = [ 'mathvariant' => Variants::ITALIC ];
				}
				break;
			default:
				$args = [ 'mathvariant' => $variant ];

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
	 * @return MMLbase negation block as MathML
	 */
	public static function createNot(): MMLbase {
		return new MMLmrow( TexClass::REL, [],
			new MMLmpadded( "", [ "width" => "0" ], new MMLmtext( "", [], "&#x29F8;" ) )
		);
	}

	private static function addToChr( string $chr, string $base ): string {
		return strtoupper( dechex( mb_ord( $chr ) + hexdec( $base ) ) );
	}

	public static function getIntentContent( ?string $input ): ?string {
		if ( !$input ) {
			return null;
		}
		$matchesInt = [];
		$matchInt = preg_match( "/intent=[\'\"](.*)[\'\"]/", $input, $matchesInt );
		if ( $matchInt && count( $matchesInt ) >= 2 ) {
			return $matchesInt[1];
		}
		return null;
	}

	public static function getIntentParams( ?string $intentContent ): ?array {
		if ( !$intentContent ) {
			return null;
		}
		$matchesParams = [];
		// tbd eventually not only alphanumerical chars valid in intent params
		$matchParams = preg_match_all( "/\\\$([a-zA-Z]+)/", $intentContent, $matchesParams );
		if ( $matchParams && count( $matchesParams ) >= 2 ) {
			return $matchesParams[1];
		}
		return null;
	}

	public static function getIntentArgs( ?string $input ): ?string {
		if ( $input &&
			preg_match( "/arg\s*=\s*[\'\"](.*?)[\'\"]/", $input, $matchesArgs )
		) {
			return $matchesArgs[1];
		}
		return null;
	}

	/**
	 * Adds the intent attributes to the top-level MathML node.
	 * Valid attributes for adding are "arg" and "intent".
	 * It overwrites pre-existing attributes in the top-level element.
	 * Example: <msup intent="_($op,_of,$arg)">
	 * @param MMLbase $renderedMML defines input MathML tree
	 * @param array $intentContentAtr defines attributes to add
	 * @return MMLbase MML with added attributes
	 */
	public static function forgeIntentToTopElement( MMLbase $renderedMML, array $intentContentAtr ): MMLbase {
		if ( !$intentContentAtr || $renderedMML->isEmpty() ) {
			return $renderedMML;
		}

		return self::addAttributesToMML( $renderedMML, $intentContentAtr, "" );
	}

	/**
	 * Add parameters from attributes to the MathML tree.
	 * @param MMLbase $renderedMML defines input MathML tree
	 * @param array $intentContentAtr defines attributes to add
	 * @param string $elementTag element tag when using foundNodes
	 * @param bool $useFoundNodes use found nodes
	 * @return MMLbase MML with added attributes
	 */
	public static function addAttributesToMML(
		MMLbase $renderedMML, array $intentContentAtr, string $elementTag, bool $useFoundNodes = false
	): MMLbase {
		if ( $renderedMML->isEmpty() ) {
			return $renderedMML;
		}
		if ( $useFoundNodes ) {
			// implementation missing T423966
			return $renderedMML;
		}
		if ( isset( $intentContentAtr["intent"] ) && is_string( $intentContentAtr["intent"] ) ) {
			$renderedMML->setAttribute( "intent", $intentContentAtr["intent"] );
		}

		if ( isset( $intentContentAtr["arg"] ) && is_string( $intentContentAtr["arg"] ) ) {
			$renderedMML->setAttribute( "arg", $intentContentAtr["arg"] );
		}
		return $renderedMML;
	}

	public static function forgeIntentToSpecificElement(
		MMLbase $renderedMML, array $intentContentAtr, string $elementTag
	): MMLbase {
		if ( !$intentContentAtr || !$elementTag || $renderedMML->isEmpty() ) {
			return $renderedMML;
		}
		return self::addAttributesToMML( $renderedMML, $intentContentAtr, $elementTag, true );
	}

}
