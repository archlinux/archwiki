<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

namespace LocalisationUpdate;

/**
 * Interface for classes which fetch files over different protocols and ways.
 */
interface Fetcher {
	/**
	 * Fetches a single resource.
	 *
	 * @return bool|string False on failure.
	 */
	public function fetchFile( $url );

	/**
	 * Fetch a list of resources. This has the benefit of being able to pick up
	 * new languages as they appear if languages are stored in separate files.
	 *
	 * @return array
	 */
	public function fetchDirectory( $pattern );
}
