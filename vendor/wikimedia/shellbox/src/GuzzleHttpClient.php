<?php

namespace Shellbox;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A concrete implementation of the Shellbox HTTP client interface, using
 * Guzzle.
 */
class GuzzleHttpClient implements HttpClientInterface {
	/**
	 * Subclasses can override this method to modify the request before it is
	 * dispatched.
	 *
	 * It is not necessary for the subclass method to call the parent method.
	 *
	 * @stable to override
	 * @param RequestInterface $request
	 * @return RequestInterface
	 */
	protected function modifyRequest( RequestInterface $request ): RequestInterface {
		return $request;
	}

	/**
	 * Subclasses can override this method to modify or handle the response
	 * before it is returned to Shellbox.
	 *
	 * It is not necessary for the subclass method to call the parent method.
	 *
	 * @stable to override
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 */
	protected function modifyResponse( ResponseInterface $response ): ResponseInterface {
		return $response;
	}

	/**
	 * Subclasses can override this to change the way the Guzzle client is
	 * constructed for a given request.
	 *
	 * It is not necessary for the subclass method to call the parent method.
	 *
	 * @stable to override
	 * @param RequestInterface $request
	 * @return GuzzleHttp\Client
	 */
	protected function createClient( RequestInterface $request ) {
		return new GuzzleHttp\Client;
	}

	public function send( RequestInterface $request ): ResponseInterface {
		$request = $this->modifyRequest( $request );
		$guzzleClient = $this->createClient( $request );
		try {
			$response = $guzzleClient->send( $request );
		} catch ( RequestException $e ) {
			if ( $e->getResponse() ) {
				$response = $e->getResponse();
			} else {
				throw $e;
			}
		}
		return $this->modifyResponse( $response );
	}
}
