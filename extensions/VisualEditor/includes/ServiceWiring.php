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

use MediaWiki\MediaWikiServices;

return [
	VisualEditorHookRunner::SERVICE_NAME => static function ( MediaWikiServices $services ): VisualEditorHookRunner {
		return new VisualEditorHookRunner( $services->getHookContainer() );
	},
];
