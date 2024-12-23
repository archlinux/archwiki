<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC;

class ParserUtil {

	/**
	 * @param array|null $options
	 * @return array
	 */
	public static function createOptions( $options ) {
		# get reference of the options for usage in functions and initialize with default values.
		$optionsBase = [
			'usemathrm' => false,
			'usemhchem' => false,
			'usemhchemtexified' => false,
			'useintent' => false,
			'oldtexvc' => false,
			'oldmhchem' => false,
			'debug' => false,
			'report_required' => false
		];
		return array_merge( $optionsBase, $options ?? [] );
	}
}
