<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\ServiceNames;

/**
 * This service is used to generate the list of variables which are protected variables.
 */
class AbuseFilterProtectedVariablesLookup {
	public const SERVICE_NAME = ServiceNames::ProtectedVariablesLookup;

	public const CONSTRUCTOR_OPTIONS = [
		'AbuseFilterProtectedVariables',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly AbuseFilterHookRunner $hookRunner
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Returns an array of all variables which are considered protected variables, and therefore can only be used
	 * in protected filters.
	 *
	 * @return string[]
	 */
	public function getAllProtectedVariables(): array {
		$protectedVariables = [];
		$this->hookRunner->onAbuseFilterCustomProtectedVariables( $protectedVariables );
		return array_unique( array_merge(
			$protectedVariables, $this->options->get( 'AbuseFilterProtectedVariables' )
		) );
	}
}
