<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate\Reader;

/**
 * Reads MediaWiki JSON i18n files.
 */
class JSONReader implements Reader {
	/**
	 * @var string|null Language tag
	 */
	protected $code;

	/**
	 * @param string|null $code Language tag
	 */
	public function __construct( $code = null ) {
		$this->code = $code;
	}

	/**
	 * @param string $contents
	 *
	 * @return string[][]
	 */
	public function parse( $contents ) {
		$messages = \FormatJson::decode( $contents, true );
		unset( $messages['@metadata'] );

		if ( $this->code ) {
			return [ $this->code => $messages ];
		}

		// Assuming that the array is keyed by language codes
		return $messages;
	}
}
