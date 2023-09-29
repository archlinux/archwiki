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
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	VisualEditorHookRunner::SERVICE_NAME => static function ( MediaWikiServices $services ): VisualEditorHookRunner {
		return new VisualEditorHookRunner( $services->getHookContainer() );
	},

	VisualEditorParsoidClientFactory::SERVICE_NAME => static function (
		MediaWikiServices $services
	): VisualEditorParsoidClientFactory {
		$isPrivateWiki = !$services->getPermissionManager()->isEveryoneAllowed( 'read' );

		return new VisualEditorParsoidClientFactory(
			new ServiceOptions(
				VisualEditorParsoidClientFactory::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
				[
					VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => $isPrivateWiki
				]
			),
			$services->getHttpRequestFactory(),
			LoggerFactory::getInstance( 'VisualEditor' ),
			$services->getPageRestHelperFactory()
		);
	},
];
