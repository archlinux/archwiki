<?php

namespace MediaWiki\Extension\Thanks;

use MediaWiki\MediaWikiServices;

/**
 * Service container class for the Thanks extension.
 */
class ThanksServices {

	/**
	 * Convenience method for returning an instance without having to use new, for chaining.
	 */
	public static function wrap( MediaWikiServices $services ): self {
		return new self( $services );
	}

	public function __construct(
		private readonly MediaWikiServices $services,
	) {
	}

	public function getQueryHelper(): ThanksQueryHelper {
		return $this->services->get( 'ThanksQueryHelper' );
	}

}
