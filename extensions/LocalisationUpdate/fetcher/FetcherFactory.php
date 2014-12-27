<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

/**
 * Constructs fetchers based on the repository urls.
 */
class LU_FetcherFactory {
	public function getFetcher( $path ) {

		if ( strpos( $path, 'https://raw.github.com/' ) === 0 ) {
			return new LU_GitHubFetcher();
		} elseif ( strpos( $path, 'http://' ) === 0 ) {
			return new LU_HttpFetcher();
		} elseif ( strpos( $path, 'https://' ) === 0 ) {
			return new LU_HttpFetcher();
		} else {
			return new LU_FileSystemFetcher();
		}
	}
}
