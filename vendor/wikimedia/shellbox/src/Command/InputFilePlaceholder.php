<?php

namespace Shellbox\Command;

use Shellbox\ShellboxError;

/**
 * An input file placeholder to represent a temporary file in the server
 */
class InputFilePlaceholder extends InputFile {
	public function copyTo( $destPath ) {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}

	public function getStreamOrString() {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}
}
