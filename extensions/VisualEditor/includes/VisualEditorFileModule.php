<?php

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\FileModule;

/**
 * ResourceLoader module for VisualEditor lib/ve modules, reading file lists from modules.json.
 */
class VisualEditorFileModule extends FileModule {

	public function __construct(
		array $options = [],
		?string $localBasePath = null,
		?string $remoteBasePath = null
	) {
		if ( !isset( $options['veModules'] ) || !is_array( $options['veModules'] ) ) {
			parent::__construct( $options, $localBasePath, $remoteBasePath );
			return;
		}

		$jsonPath = __DIR__ . '/../lib/ve/build/modules.json';
		$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
		$cacheKey = $cache->makeKey(
			'visualeditor-modules-json',
			$jsonPath,
			filemtime( $jsonPath )
		);
		$veModulesJson = $cache->getWithSetCallback(
			$cacheKey,
			$cache::TTL_DAY,
			static function () use ( $jsonPath ) {
				return json_decode( file_get_contents( $jsonPath ), true );
			}
		);

		// Prepend scripts/styles from $options['veModules'] to $options['scripts']/$options['styles']
		$scripts = [];
		$debugScripts = [];
		$styles = [];
		foreach ( $options['veModules'] as $veModuleName ) {
			if ( !isset( $veModulesJson[$veModuleName] ) ) {
				throw new ConfigException( 'veModule not found: ' . $veModuleName );
			}
			$module = $veModulesJson[$veModuleName];
			if ( isset( $module['scripts'] ) ) {
				foreach ( $module['scripts'] as $item ) {
					$path = is_array( $item ) ? $item['file'] : $item;
					$fullPath = 'lib/ve/' . $path;
					if ( is_array( $item ) && !empty( $item['debug'] ) ) {
						$debugScripts[] = $fullPath;
					} else {
						$scripts[] = $fullPath;
					}
				}
			}
			if ( isset( $module['styles'] ) ) {
				foreach ( $module['styles'] as $item ) {
					$path = is_array( $item ) ? $item['file'] : $item;
					$styles[] = 'lib/ve/' . $path;
				}
			}
		}
		if ( isset( $options['scripts'] ) && is_array( $options['scripts'] ) ) {
			$options['scripts'] = array_merge( $scripts, $options['scripts'] );
		} else {
			$options['scripts'] = $scripts;
		}
		if ( isset( $options['debugScripts'] ) && is_array( $options['debugScripts'] ) ) {
			$options['debugScripts'] = array_merge( $debugScripts, $options['debugScripts'] );
		} else {
			$options['debugScripts'] = $debugScripts;
		}
		if ( isset( $options['styles'] ) && is_array( $options['styles'] ) ) {
			$options['styles'] = array_merge( $styles, $options['styles'] );
		} else {
			$options['styles'] = $styles;
		}

		parent::__construct( $options, $localBasePath, $remoteBasePath );
	}
}
