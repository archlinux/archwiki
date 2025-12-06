<?php

namespace Cite\ResourceLoader;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\FileModule;
use MediaWiki\ResourceLoader\Module;

/**
 * @license MIT
 */
class OptionalLoader {

	/**
	 * Add optional array values to a ResourceLoader module definition depending on loaded extensions.
	 *
	 * "optional": {
	 *   "MyExtension": {
	 *     "dependencies": [ ... ],
	 *     "messages": [ ... ],
	 *     "packageFiles": [ ... ],
	 *     ...
	 *   }
	 * }
	 *
	 * Copied from DiscussionTools
	 */
	public static function addOptional( array $info ): Module {
		$extensionRegistry = ExtensionRegistry::getInstance();

		if ( isset( $info['optional'] ) ) {
			foreach ( $info['optional'] as $ext => $extOptions ) {
				if ( $extensionRegistry->isLoaded( $ext ) ) {
					foreach ( $extOptions as $key => $values ) {
						if ( !isset( $info[$key] ) ) {
							$info[$key] = [];
						}
						// TODO: Support non-array properties
						$info[$key] = array_merge( $info[$key], (array)$values );
					}
				}
			}
		}

		$class = $info['class'] ?? FileModule::class;
		return new $class( $info );
	}
}
