<?php

namespace Shellbox\Command;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Shellbox\FileUtils;

class InputFileFromStream extends InputFileWithContents {
	/** @var StreamInterface */
	private $stream;

	/**
	 * @internal
	 * @param StreamInterface $stream
	 */
	public function __construct( StreamInterface $stream ) {
		$this->stream = $stream;
	}

	public function copyTo( $destPath ) {
		// Rewind for consistency with getStreamOrString()
		$this->stream->rewind();
		Utils::copyToStream( $this->stream, FileUtils::openOutputFileStream( $destPath ) );
	}

	public function getStreamOrString() {
		// The client needs to read it twice.
		// Rewind, otherwise we may get different results each time.
		$this->stream->rewind();
		return $this->stream;
	}
}
