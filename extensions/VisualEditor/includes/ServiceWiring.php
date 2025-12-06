<?php

/**
 * ServiceWiring files for VisualEditor.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2021 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	VisualEditorParsoidClientFactory::SERVICE_NAME => static function (
		MediaWikiServices $services
	): VisualEditorParsoidClientFactory {
		return new VisualEditorParsoidClientFactory( $services->getPageRestHelperFactory() );
	},
	VisualEditorAvailabilityLookup::SERVICE_NAME => static function (
		MediaWikiServices $services
	) {
		return new VisualEditorAvailabilityLookup(
			new ServiceOptions(
				VisualEditorAvailabilityLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getNamespaceInfo(),
			$services->getExtensionRegistry(),
			$services->getUserOptionsLookup()
		);
	},
];

// @codeCoverageIgnoreEnd
