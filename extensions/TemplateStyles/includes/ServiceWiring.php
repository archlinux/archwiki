<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in TemplateStylesServiceWiringTest.php
// @codeCoverageIgnoreStart

/**
 * TemplateStyles wiring for MediaWiki services.
 */
return [
	'TemplateStyles.ContentProvider' => static function ( MediaWikiServices $services ): TemplateStylesContentProvider {
		return new TemplateStylesContentProvider(
			$services->getContentHandlerFactory(),
			$services->getTitleFormatter()
		);
	},
];
// @codeCoverageIgnoreEnd
