<?php

namespace Wikimedia\RequestTimeout;

use Exception;
use Wikimedia\NormalizedException\INormalizedException;
use Wikimedia\NormalizedException\NormalizedExceptionTrait;

/**
 * The base class for timeout exceptions thrown by this library
 */
class TimeoutException extends Exception implements INormalizedException {
	use NormalizedExceptionTrait;

	/** @var float */
	private $limit;

	public function __construct( $message, $limit ) {
		$this->limit = $limit;
		$this->normalizedMessage = $message;
		$this->messageContext = [ 'limit' => $limit ];
		parent::__construct( self::getMessageFromNormalizedMessage( $message, $this->messageContext ) );
	}

	/**
	 * Get the limit which was exceeded, in seconds.
	 *
	 * @return float
	 */
	public function getLimit() {
		return $this->limit;
	}
}
