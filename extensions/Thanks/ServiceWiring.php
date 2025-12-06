<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Thanks\Storage\LogStore;
use MediaWiki\Extension\Thanks\ThanksQueryHelper;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'ThanksLogStore' => static function ( MediaWikiServices $services ): LogStore {
		return new LogStore(
			$services->getConnectionProvider(),
			$services->getActorNormalization(),
			new ServiceOptions(
				LogStore::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'ThanksQueryHelper' => static function (
			MediaWikiServices $services
		): ThanksQueryHelper {
			return new ThanksQueryHelper(
				$services->getTitleFactory(),
				$services->getConnectionProvider(),
				$services->getActorNormalization(),
				$services->getUserNameUtils()
			);
	},
];
