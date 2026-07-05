<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util;

use DOMDocument;
use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmath;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;

/**
 * This Utility class has some methods for running
 * tests for the Tex to MathML converters in WikiTexVC.
 * @author Johannes Stegmüller
 */
class MMLTestUtil {
	/**
	 * @return mixed
	 */
	public static function getJSON( string $filePath ) {
		if ( !file_exists( $filePath ) ) {
			throw new InvalidArgumentException( "No testfile found at specified path: " . $filePath );
		}
		return json_decode( file_get_contents( $filePath ) );
	}

	public static function prettifyXML( string $xml, bool $replaceHeader = true ): string {
		$dom = new DOMDocument();
		// Initial block (must before load xml string)
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		// End initial block
		$dom->loadXML( $xml );
		$out = $dom->saveXML();
		if ( $replaceHeader ) {
			// replacing the xml header in a hacky way
			return substr_replace( $out, "", 0, 22 );
		}
		return $out;
	}

	public static function getMMLwrapped( TexNode $input ): string {
		$math = new MMLmath( "", [], $input->toMMLTree() );
		return self::prettifyXML( (string)$math );
	}
}
