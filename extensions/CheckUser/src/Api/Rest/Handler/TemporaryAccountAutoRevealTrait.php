<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Permissions\Authority;

trait TemporaryAccountAutoRevealTrait {
	/**
	 * If GlobalPreferences is loaded, then the user may be using auto-reveal. In that case,
	 * add whether auto-reveal mode is on or off, to avoid further API calls to determine this.
	 *
	 * @param array &$results The API results
	 */
	protected function addAutoRevealStatusToResults( array &$results ) {
		if ( !$this->getCheckUserAutoRevealLookup()->isAutoRevealAvailable() ) {
			return;
		}

		$results['autoReveal'] = $this->getCheckUserAutoRevealLookup()->isAutoRevealOn( $this->getAuthority() );
	}

	abstract protected function getAuthority(): Authority;

	abstract protected function getCheckUserAutoRevealLookup(): CheckUserTemporaryAccountAutoRevealLookup;
}
