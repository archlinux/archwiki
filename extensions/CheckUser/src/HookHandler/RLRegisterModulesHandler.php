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

		$modules['ext.checkUser.tempAccountOnboarding'] = [
			'localBasePath' => $dir . 'ext.checkUser.tempAccountsOnboarding',
			'remoteExtPath' => 'CheckUser/modules/ext.checkUser.tempAccountsOnboarding',
			'packageFiles' => [
				'init.js',
				'components/App.vue',
				'components/TempAccountsOnboardingDialog.vue',
				'components/TempAccountsOnboardingStep.vue',
				'components/TempAccountsOnboardingStepper.vue',
				'components/TempAccountsOnboardingIntroStep.vue',
				'components/TempAccountsOnboardingIPInfoStep.vue',
				'components/TempAccountsOnboardingIPRevealStep.vue',
				[
					'name' => 'components/icons.json',
					'callback' => 'MediaWiki\\ResourceLoader\\CodexModule::getIcons',
					'callbackParam' => [
						'cdxIconNext',
						'cdxIconPrevious'
					],
				]
			],
			'messages' => [
				'checkuser-temporary-accounts-onboarding-dialog-title',
				'checkuser-temporary-accounts-onboarding-dialog-skip-all',
				'checkuser-temporary-accounts-onboarding-dialog-stepper-label',
				'checkuser-temporary-accounts-onboarding-dialog-previous-label',
				'checkuser-temporary-accounts-onboarding-dialog-next-label',
				'checkuser-temporary-accounts-onboarding-dialog-close-label',
				'checkuser-temporary-accounts-onboarding-dialog-temp-accounts-step-title',
				'checkuser-temporary-accounts-onboarding-dialog-temp-accounts-step-content',
				'checkuser-temporary-accounts-onboarding-dialog-temp-accounts-step-image-aria-label',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-title',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-image-aria-label',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-warning',
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-save-preference',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-title',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-image-aria-label',
			],
			'dependencies' => [
				'vue',
				'@wikimedia/codex',
				'mediawiki.api',
				'mediawiki.user',
				'mediawiki.jqueryMsg'
			],
		];

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
			$modules[ 'ext.checkUser.tempAccountOnboarding' ]['messages'][] = 'ipinfo-preference-use-agreement';
		}

		if ( count( $modules ) ) {
			$resourceLoader->register( $modules );
		}
	}

}
