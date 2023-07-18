<?php

use MediaWiki\Extension\Thanks\ThanksQueryHelper;
use MediaWiki\MediaWikiServices;

return [
	'ThanksQueryHelper' => static function (
			MediaWikiServices $services
		): ThanksQueryHelper {
			return new ThanksQueryHelper(
				$services->getTitleFactory(),
				$services->getDBLoadBalancer()
			);
	},
];
