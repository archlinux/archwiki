<?php

namespace Shellbox\Command;

use Shellbox\FileUtils;
use Shellbox\Multipart\MultipartReader;
use Shellbox\ShellboxError;

/**
 * Encapsulation of an output file that is read into a string
 */
class OutputFileToString extends OutputFileWithContents {
	/** @var string */
	private $contents;

	public function copyFromFile( $sourcePath ) {
		$this->contents = FileUtils::getContents( $sourcePath );
		$this->setReceived();
	}

	public function getContents() {
		if ( $this->contents === null ) {
			throw new ShellboxError( __METHOD__ . ' was called too early or ' .
				'on a non-existent file' );
		}
		return $this->contents;
	}

	public function readFromMultipart( MultipartReader $multipartReader ) {
		$this->contents = $multipartReader->readPartAsString();
		$this->setReceived();
	}
}
