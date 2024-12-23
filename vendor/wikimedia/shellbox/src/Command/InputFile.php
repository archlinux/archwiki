<?php

namespace Shellbox\Command;

use Shellbox\ShellboxError;

/**
 * The base class for input files
 */
abstract class InputFile {
	use UserDataTrait;

	/**
	 * Get data for JSON serialization by the client.
	 *
	 * @internal
	 * @return array
	 */
	public function getClientData() {
		return [];
	}

	/**
	 * Get an InputFile object to represent a file already created by the server.
	 *
	 * @internal
	 * @param array $data
	 * @return InputFile
	 */
	public static function newFromClientData( $data ) {
		if ( ( $data['type'] ?? '' ) === 'url' ) {
			if ( !isset( $data['url'] ) ) {
				throw new ShellboxError( 'Missing required parameter for URL file: "url"' );
			}
			$file = new InputFileFromUrl( $data['url'] );
			if ( isset( $data['headers'] ) ) {
				$file->headers( $data['headers'] );
			}
			return $file;
		}
		return new InputFilePlaceholder;
	}
}
