<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

// PHPUnit doesn't understand code coverage for code outside of classes/functions,
// like service wiring files. This *is* tested though, see
// tests/phpunit/integration/ServiceWiringTest.php
// @codeCoverageIgnoreStart

/*
 * CheckUser provides a service for this, but
 * we define our own nullable here to make CheckUser a soft dependency
 */
return [
	'NukeIPLookup' => static function (
		MediaWikiServices $services
	) {
		// Allow IP lookups if CheckUser is present and temp user config is known and enabled
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			return null;
		}
		$tempUserConfig = $services->getTempUserConfig();
		$tempUserIsKnown = $tempUserConfig->isKnown();
		$tempUserIsEnabled = $tempUserConfig->isEnabled();
		if ( !$tempUserIsKnown || !$tempUserIsEnabled ) {
			return null;
		}
		return $services->get( 'CheckUserTemporaryAccountsByIPLookup' );
	}
];

// @codeCoverageIgnoreEnd
