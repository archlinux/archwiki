<?php

namespace MediaWiki\SyntaxHighlight;

use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'SyntaxHighlight.SyntaxHighlight' => static function ( MediaWikiServices $services ): SyntaxHighlight {
		return new SyntaxHighlight(
			$services->getMainConfig(),
			$services->getMainWANObjectCache()
		);
	},
];
