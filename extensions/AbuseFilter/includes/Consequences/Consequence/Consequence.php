<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;

/**
 * Base command-style class for consequences.
 */
abstract class Consequence {

	public function __construct( protected readonly Parameters $parameters ) {
	}

	/**
	 * @return bool A generic success indicator, subclasses can be more specific
	 */
	abstract public function execute(): bool;
}
