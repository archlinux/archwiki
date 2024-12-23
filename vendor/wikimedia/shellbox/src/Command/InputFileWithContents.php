<?php

namespace Shellbox\Command;

use Psr\Http\Message\StreamInterface;

/**
 * @since 4.1.0
 */
abstract class InputFileWithContents extends InputFile {
	/**
	 * Copy the contents of the input file to a fully-qualified path
	 *
	 * @internal
	 * @param string $destPath
	 */
	abstract public function copyTo( $destPath );

	/**
	 * Get the contents of the file as either a PSR-7 StreamInterface or a
	 * string.
	 *
	 * @internal
	 * @return StreamInterface|string
	 */
	abstract public function getStreamOrString();
}
