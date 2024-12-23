<?php

namespace Shellbox\Command;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * An OutputGlob for files that are sent to a server with PUT requests.
 * @since 4.1.0
 */
class OutputGlobToUrl extends OutputGlob {
	/** @var UriInterface */
	private $destUri;

	/**
	 * @internal
	 * @param string $prefix
	 * @param string $extension
	 * @param string|UriInterface $destUrl
	 */
	public function __construct( string $prefix, string $extension, $destUrl ) {
		parent::__construct( $prefix, $extension );
		if ( $destUrl instanceof UriInterface ) {
			$this->destUri = $destUrl;
		} else {
			$this->destUri = new Uri( $destUrl );
		}
	}

	public function getOutputFile( $boxedName ) {
		$path = $this->destUri->getPath() . basename( $boxedName );
		$instance = new OutputFileToUrl( $this->destUri->withPath( $path ) );
		$this->files[$boxedName] = $instance;
		return $instance;
	}

	public function getClientData() {
		return parent::getClientData() + [
			'type' => 'url',
			'url' => (string)$this->destUri,
		];
	}
}
