<?php

namespace Shellbox\Command;

use Psr\Http\Message\UriInterface;

/**
 * @since 4.1.0
 */
class InputFileFromUrl extends InputFile {
	/**
	 * @var UriInterface|string
	 */
	private $url;

	/**
	 * @var array
	 */
	private $headers = [];

	/**
	 * @internal
	 * @param UriInterface|string $url
	 */
	public function __construct( $url ) {
		$this->url = $url;
	}

	/**
	 * Replace the current array of headers with the specified list. The array
	 * should be in Guzzle's format: an associative array where the key is the
	 * header name and the value is either a string or an array of strings.
	 * Array values are used to send multiple headers with the same name.
	 *
	 * @param array $headers
	 * @return $this
	 */
	public function headers( array $headers ) {
		$this->headers = $headers;
		return $this;
	}

	public function getClientData() {
		return [
			'type' => 'url',
			'url' => (string)$this->url,
			'headers' => $this->headers
		];
	}

	/**
	 * @return UriInterface|string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}
}
