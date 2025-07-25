<?php

namespace MediaWiki\Extension\TemplateStyles;

use MediaWiki\Content\TextContent;

/**
 * @file
 * @license GPL-2.0-or-later
 */

/**
 * Content object for sanitized CSS.
 */
class TemplateStylesContent extends TextContent {

	/**
	 * @param string $text
	 * @param string $modelId
	 */
	public function __construct( $text, $modelId = 'sanitized-css' ) {
		parent::__construct( $text, $modelId );
	}
}
