<?php
/**
 * Utilities for ResourceLoader modules used by EditCheck.
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor\EditCheck;

use MessageLocalizer;

class ResourceLoaderData {

	/**
	 * Return configuration data for edit checks, fetched from an on-wiki JSON message
	 *
	 * @param MessageLocalizer $context
	 * @return array Configuration data for edit checks
	 */
	public static function getConfig( MessageLocalizer $context ): array {
		$raw_config = json_decode( $context->msg( 'editcheck-config.json' )->inContentLanguage()->plain(), true );

		return $raw_config ?? [];
	}
}
