<?php

use MediaWiki\MediaWikiServices;

/*
 * CheckUser provides a service for this, but
 * we define our own nullable here to make CheckUser a soft dependency
 */

return [
	'NukeIPLookup' => static function (
		MediaWikiServices $services
	) {
		// Allow IP lookups if temp user is known and CheckUser is present
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			return null;
		}
		$tempUserIsKnown = $services->getTempUserConfig()->isKnown();
		if ( !$tempUserIsKnown ) {
			return null;
		}
		return $services->get( 'CheckUserTemporaryAccountsByIPLookup' );
	}
];
