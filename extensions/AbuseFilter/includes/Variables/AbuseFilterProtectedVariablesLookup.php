<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;

/**
 * This service is used to generate the list of variables which are protected variables.
 */
class AbuseFilterProtectedVariablesLookup {
	public const SERVICE_NAME = 'AbuseFilterProtectedVariablesLookup';

	public const CONSTRUCTOR_OPTIONS = [
		'AbuseFilterProtectedVariables',
	];

	private ServiceOptions $options;
	private AbuseFilterHookRunner $hookRunner;

	public function __construct(
		ServiceOptions $options,
		AbuseFilterHookRunner $hookRunner
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->hookRunner = $hookRunner;
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
