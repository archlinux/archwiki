<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

namespace LocalisationUpdate;

/**
 * Reads MediaWiki JSON i18n files.
 */
class JSONReader implements Reader {
	/// @var string Language tag
	protected $code;

	public function __construct( $code = null ) {
		$this->code = $code;
	}

	public function parse( $contents ) {
		$messages = \FormatJson::decode( $contents, true );
		unset( $messages['@metadata'] );

		if ( $this->code ) {
			return array( $this->code => $messages );
		}

		// Assuming that the array is keyed by language codes
		return $messages;
	}
}
