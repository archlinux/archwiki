<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class ResourceLoaderHandler implements ResourceLoaderRegisterModulesHook {

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CodeEditor' ) ) {
			$resourceLoader->register( 'ext.abuseFilter.ace', [
				'localBasePath' => dirname( __DIR__ ) . '/../../modules',
				'remoteExtPath' => 'AbuseFilter/modules',
				'scripts' => 'mode-abusefilter.js',
				'dependencies' => 'ext.codeEditor.ace',
			] );
		}
	}

}
