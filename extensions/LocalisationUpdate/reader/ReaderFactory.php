<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

/**
 * Constructs readers for files based on the names.
 */
class LU_ReaderFactory {
	/**
	 * Constructs a suitable reader for a given path.
	 * @param string $filename Usually a relative path to the file name.
	 * @return LU_Reader
	 * @throw Exception
	 */
	public function getReader( $filename ) {
		if ( preg_match( '/i18n\.php$/', $filename ) ) {
			return new LU_PHPReader();
		}

		// Ugly hack for core i18n files
		if ( preg_match( '/Messages(.*)\.php$/', $filename ) ) {
			$code = Language::getCodeFromFileName( basename( $filename ), 'Messages' );
			return new LU_PHPReader( $code );
		}

		if ( preg_match( '/\.json/', $filename ) ) {
			$code = basename( $filename, '.json' );
			return new LU_JSONReader( $code );
		}

		throw new Exception( "Unknown file format: " . $filename );
	}
}
