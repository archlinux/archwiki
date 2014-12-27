<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

/**
 * Accesses file system directly.
 */
class LU_FileSystemFetcher implements LU_Fetcher {
	public function fetchFile( $url ) {
		// Remove the protocol prefix
		$url = preg_replace( '~^file://~', '', $url );

		if ( !is_readable( $url ) ) {
			return false;
		}

		return file_get_contents( $url );
	}

	public function fetchDirectory( $pattern ) {
		// Remove the protocol prefix
		$pattern = preg_replace( '~^file://~', '', $pattern );

		$data = array();
		foreach ( glob( $pattern ) as $file ) {
			if ( is_readable( $file ) ) {
				$data["file://$file"] = file_get_contents( $file );
			}
		}
		return $data;
	}
}
