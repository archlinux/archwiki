<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {
	private ExtensionRegistry $extensionRegistry;

	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$dir = dirname( __DIR__, 2 ) . '/modules/';
		$modules = [];

		if ( $this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			$modules[ 'ext.guidedTour.tour.checkuserinvestigateform' ] = [
				'localBasePath' => $dir . 'ext.guidedTour.tour.checkuserinvestigateform',
				'remoteExtPath' => "CheckUser/modules",
				'scripts' => "checkuserinvestigateform.js",
				'dependencies' => 'ext.guidedTour',
				'messages' => [
					'checkuser-investigate-tour-targets-title',
					'checkuser-investigate-tour-targets-desc'
				]
			];
			$modules[ 'ext.guidedTour.tour.checkuserinvestigate' ] = [
				'localBasePath' => $dir . 'ext.guidedTour.tour.checkuserinvestigate',
				'remoteExtPath' => "CheckUser/module",
				'scripts' => 'checkuserinvestigate.js',
				'dependencies' => [ 'ext.guidedTour', 'ext.checkUser' ],
				'messages' => [
					'checkuser-investigate-tour-useragents-title',
					'checkuser-investigate-tour-useragents-desc',
					'checkuser-investigate-tour-addusertargets-title',
					'checkuser-investigate-tour-addusertargets-desc',
					'checkuser-investigate-tour-filterip-title',
					'checkuser-investigate-tour-filterip-desc',
					'checkuser-investigate-tour-block-title',
					'checkuser-investigate-tour-block-desc',
					'checkuser-investigate-tour-copywikitext-title',
					'checkuser-investigate-tour-copywikitext-desc',
				],
			];
		}

		if ( $this->extensionRegistry->isLoaded( 'IPInfo' ) ) {
			$modules[ 'ext.checkUser.ipInfo.hooks' ] = [
				'localBasePath' => $dir . 'ext.checkUser.ipInfo.hooks',
				'remoteExtPath' => "CheckUser/modules",
				'scripts' => [
					'infobox.js',
					'init.js'
				],
				'messages' => [
					'ext-ipinfo-global-contributions-url-text',
				]
			];
		}

		if ( count( $modules ) ) {
			$resourceLoader->register( $modules );
		}
	}

}
