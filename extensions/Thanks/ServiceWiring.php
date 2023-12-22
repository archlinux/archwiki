<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Thanks\Storage\LogStore;
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
	'ThanksLogStore' => static function ( MediaWikiServices $services ): LogStore {
		return new LogStore(
			$services->getDBLoadBalancerFactory(),
			$services->getActorNormalization(),
			new ServiceOptions(
				LogStore::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	}
];
