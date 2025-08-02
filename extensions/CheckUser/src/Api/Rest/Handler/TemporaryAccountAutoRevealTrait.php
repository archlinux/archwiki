<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Permissions\Authority;
use MediaWiki\Preferences\PreferencesFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

trait TemporaryAccountAutoRevealTrait {
	/**
	 * If GlobalPreferences is loaded, then the user may be using auto-reveal. In that case,
	 * add whether auto-reveal mode is on or off, to avoid further API calls to determine this.
	 *
	 * @param array &$results The API results
	 */
	protected function addAutoRevealStatusToResults( array &$results ) {
		$preferencesFactory = $this->getPreferencesFactory();

		if ( $preferencesFactory instanceof GlobalPreferencesFactory ) {
			$globalPreferences = $preferencesFactory->getGlobalPreferencesValues(
				$this->getAuthority()->getUser(),
				// Load from the database, not the cache, since we're using it for access.
				true
			);
			$isAutoRevealOn = $globalPreferences &&
				isset( $globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ) &&
				$globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] > ConvertibleTimestamp::time();

			$results['autoReveal'] = $isAutoRevealOn;
		}
	}

	abstract protected function getAuthority(): Authority;

	abstract protected function getPreferencesFactory(): PreferencesFactory;
}
