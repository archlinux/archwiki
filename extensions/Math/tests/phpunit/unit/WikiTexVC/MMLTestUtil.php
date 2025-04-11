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

	public static function createJSONstartEnd( bool $start, string $file ) {
		file_put_contents( $file, $start ? "[\n" : "\n]", FILE_APPEND );
	}

	public static function appendToJSONFile( array $dataArray, string $file ) {
		$jsonData = json_encode( $dataArray, JSON_PRETTY_PRINT ) . ",";
		file_put_contents( $file, $jsonData, FILE_APPEND );
	}

	public static function deleteFile( string $file ): void {
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
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
		$math = new MMLmath();
		$mml = $math->encapsulateRaw( $input->renderMML() );
		return self::prettifyXML( $mml );
	}
}
