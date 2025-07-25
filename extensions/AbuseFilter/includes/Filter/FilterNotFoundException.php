<?php

namespace MediaWiki\Extension\AbuseFilter\Filter;

use Exception;
use Wikimedia\NormalizedException\INormalizedException;
use Wikimedia\NormalizedException\NormalizedExceptionTrait;

/**
 * @codeCoverageIgnore
 */
class FilterNotFoundException extends Exception implements INormalizedException {
	use NormalizedExceptionTrait;

	/**
	 * @param int $filter
	 * @param bool $global
	 */
	public function __construct( int $filter, bool $global ) {
		$this->normalizedMessage = $global
			? "Global filter {filter} does not exist"
			: "Filter {filter} does not exist";
		$this->messageContext = [ 'filter' => $filter ];

		parent::__construct( self::getMessageFromNormalizedMessage( $this->normalizedMessage, $this->messageContext ) );
	}
}
