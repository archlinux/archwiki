<?php
/**
 * VisualEditor extension hooks for EditCheck
 *
 * @file
 * @ingroup Extensions
 * @copyright 2025 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor\EditCheck;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

/**
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	ResourceLoaderRegisterModulesHook
{

	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );
		$experimentalConfig = $veConfig->get( 'VisualEditorEditCheckLoadExperimental' );

		if ( !$experimentalConfig ) {
			return;
		}

		$experimentalDir = dirname( __DIR__ ) . '/modules/editchecks/experimental';
		$files = array_diff( scandir( $experimentalDir ), [ '..', '.' ] );
		if ( is_array( $experimentalConfig ) ) {
			$files = array_intersect( $files, $experimentalConfig );
		}
		$veResourceTemplate = [
			'localBasePath' => $experimentalDir,
			'remoteExtPath' => 'VisualEditor',
		];
		$resourceLoader->register( [
			'ext.visualEditor.editCheck.experimental' => $veResourceTemplate + [
				'group' => 'visualEditorA',
				'packageFiles' => $files + [
					[
						"name" => "init.js",
						"main" => true,
						"content" => array_reduce( $files, static function ( $carry, $file ) {
							return $carry . "require('./$file');\n";
						}, "" ),
					],
				],
				"dependencies" => [ 'ext.visualEditor.editCheck' ],
			] ] );
	}
}
