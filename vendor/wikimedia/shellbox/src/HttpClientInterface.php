<?php

namespace Shellbox;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An interface representing an HTTP client provided by the caller. A concrete
 * implementation must be provided by the caller.
 */
interface HttpClientInterface {
	/**
	 * Send an HTTP request and return the response.
	 *
	 * It is permissible to throw an exception for propagation back to the
	 * caller. However, a successfully received response with a status code
	 * of >=400 should ideally be returned to Shellbox as a ResponseInterface,
	 * so that Shellbox can parse and rethrow its own error messages.
	 *
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 */
	public function send( RequestInterface $request ) : ResponseInterface;
}
