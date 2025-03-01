<?php

namespace Shellbox\Command;

use Shellbox\Multipart\MultipartReader;
use Shellbox\ShellboxError;

/**
 * An OutputFile which carries its contents for eventual retrieval.
 *
 * @since 4.1.0
 */
abstract class OutputFileWithContents extends OutputFile {

	/**
	 * Copy from the specified source path to the registered destination
	 * location, which may be either a string or a path outside the working
	 * directory.
	 *
	 * @internal
	 * @param string $sourcePath
	 */
	abstract public function copyFromFile( $sourcePath );

	/**
	 * Get the contents of the output file from its final destination. This
	 * should be called after copyFromFile() or readFromMultipart(). It will
	 * throw if the file is not readable.
	 *
	 * @internal
	 * @return string
	 * @throws ShellboxError
	 */
	abstract public function getContents();

	/**
	 * Copy from the MultipartReader to the registered destination location.
	 * The MultipartReader must be at the appropriate place in the input stream.
	 * Used by the client.
	 *
	 * @internal
	 * @param MultipartReader $multipartReader
	 */
	abstract public function readFromMultipart( MultipartReader $multipartReader );

}
