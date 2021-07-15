<?php

namespace Shellbox\Command;

use Shellbox\Multipart\MultipartReader;
use Shellbox\ShellboxError;

class OutputFilePlaceholder extends OutputFile {
	public function copyFromFile( $sourcePath ) {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}

	public function getContents() {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}

	public function readFromMultipart( MultipartReader $multipartReader ) {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}
}
