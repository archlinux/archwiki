<?php

use MediaWiki\MediaWikiServices;
use PageImages\PageImages;

/** @phpcs-require-sorted-array */
return [
	'PageImages.PageImages' => static function ( MediaWikiServices $services ): PageImages {
		return new PageImages(
			$services->getMainConfig(),
			$services->getDBLoadBalancerFactory(),
			$services->getRepoGroup(),
			$services->getUserOptionsLookup()
		);
	},
];
