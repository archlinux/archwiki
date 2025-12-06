<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use RuntimeException;

/**
 * @codeCoverageIgnore
 */
class UnsetVariableException extends RuntimeException {

	public function __construct( string $varName ) {
		parent::__construct( "Variable $varName is not set" );
	}
}
