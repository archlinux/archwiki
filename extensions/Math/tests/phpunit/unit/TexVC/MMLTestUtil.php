<?php

namespace MediaWiki\Extension\Math\TexVC\MMLmappings\Util;

use DOMDocument;
use InvalidArgumentException;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmath;

/**
 * This Utility class has some methods for running
 * tests for the Tex to MathML converters in TexVC.
 * @author Johannes Stegmüller
 */
class MMLTestUtil {
	public static function getJSON( $filePath ) {
		if ( !file_exists( $filePath ) ) {
			throw new InvalidArgumentException( "No testfile found at specified path: " . $filePath );
		}
		$file = file_get_contents( $filePath );
		return json_decode( $file );
	}

	public static function prettifyXML( $xml, $replaceHeader = true ) {
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

	public static function getMMLwrapped( $input ) {
		$math = new MMLmath();
		$mml = $math->encapsulateRaw( $input->renderMML() );
		return self::prettifyXML( $mml );
	}
}
