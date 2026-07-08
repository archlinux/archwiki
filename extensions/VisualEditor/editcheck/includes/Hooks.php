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
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\User\User;

class Hooks implements
	ResourceLoaderRegisterModulesHook,
	GetPreferencesHook
{

	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );

		$checksDir = dirname( __DIR__ ) . '/modules/editchecks/checks';
		$files = array_diff( scandir( $checksDir ), [ '..', '.' ] );

		$veResourceTemplate = [
			'localBasePath' => $checksDir,
			'remoteExtPath' => 'VisualEditor',
		];
		$resourceLoader->register( [
			'ext.visualEditor.editCheck.checks' => $veResourceTemplate + [
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

	/**
	 * Handler for the GetPreferences hook, to add and hide user preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences Their preferences object
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$api = [ 'type' => 'api' ];
		$preferences['visualeditor-editcheck-suggestions-toggle'] = $api;
	}
}
