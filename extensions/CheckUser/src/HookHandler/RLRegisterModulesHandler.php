<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly HookRunner $hookRunner,
		private readonly Config $config,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$dir = dirname( __DIR__, 2 ) . '/modules/';
		$modules = [];

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
				'components/TempAccountsOnboardingPreference.vue',
				[
					'name' => 'components/icons.json',
					'callback' => 'MediaWiki\\ResourceLoader\\CodexModule::getIcons',
					'callbackParam' => [
						'cdxIconNext',
						'cdxIconPrevious',
					],
				],
				[
					'name' => 'defaultAutoRevealDuration.json',
					'callback' => 'MediaWiki\\CheckUser\\HookHandler\\DurationMessages::getAutoRevealMaximumExpiry',
				],
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
				'checkuser-temporary-accounts-onboarding-dialog-preference-error',
				'checkuser-temporary-accounts-onboarding-dialog-preference-success',
				'checkuser-temporary-accounts-onboarding-dialog-preference-warning',
				'checkuser-temporary-accounts-onboarding-dialog-save-preference',
				'checkuser-temporary-accounts-onboarding-dialog-save-global-preference',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-title',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-base',
				'checkuser-tempaccount-reveal-ip-button-label',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-with-global-preferences',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-image-aria-label',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title-with-global-preferences',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text',
				'checkuser-temporary-accounts-onboarding-dialog-ip-autoreveal-preference-checkbox-text',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text-with-global-preferences',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-locally-enabled',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-globally-enabled',
				'checkuser-tempaccount-enable-preference',
				'checkuser-tempaccount-enable-preference-description',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text',
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text-with-global-preferences',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text-with-global-preferences-with-autoreveal',
				'checkuser-ip-auto-reveal-link-sidebar',
			],
			'dependencies' => [
				'vue',
				'@wikimedia/codex',
				'mediawiki.api',
				'mediawiki.user',
				'mediawiki.jqueryMsg',
			],
		];

		if ( $this->extensionRegistry->isLoaded( 'IPInfo' ) ) {
			$modules[ 'ext.checkUser.ipInfo.hooks' ] = [
				'localBasePath' => $dir . 'ext.checkUser.ipInfo.hooks',
				'remoteExtPath' => "CheckUser/modules",
				'scripts' => [
					'infobox.js',
					'init.js',
				],
				'messages' => [
					'checkuser-ipinfo-global-contributions-label',
					'checkuser-ipinfo-global-contributions-tooltip',
					'checkuser-ipinfo-global-contributions-value',
					'checkuser-ipinfo-global-contributions-url-text',
				],
			];
			$modules[ 'ext.checkUser.tempAccountOnboarding' ]['messages'][] = 'ipinfo-preference-use-agreement';
		}

		$messages = [
			'checkuser-suggestedinvestigations-change-status-dialog-cancel-btn',
			'checkuser-suggestedinvestigations-change-status-dialog-submit-btn',
			'checkuser-suggestedinvestigations-change-status-dialog-close-label',
			'checkuser-suggestedinvestigations-change-status-dialog-text',
			'checkuser-suggestedinvestigations-change-status-dialog-title',
			'checkuser-suggestedinvestigations-change-status-dialog-status-list-header',
			'checkuser-suggestedinvestigations-change-status-dialog-status-reason-header',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-description-resolved',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-description-invalid',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-resolved',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-invalid',
			'checkuser-suggestedinvestigations-status-open',
			'checkuser-suggestedinvestigations-status-resolved',
			'checkuser-suggestedinvestigations-status-invalid',
			'checkuser-suggestedinvestigations-status-description-invalid',
			'checkuser-suggestedinvestigations-status-reason-default-invalid',
			'checkuser-suggestedinvestigations-user-showmore',
			'checkuser-suggestedinvestigations-user-showless',
			'checkuser-suggestedinvestigations-risk-signals-popover-title',
			'checkuser-suggestedinvestigations-risk-signals-popover-body-intro',
			'checkuser-suggestedinvestigations-risk-signals-popover-close-label',
			'checkuser-suggestedinvestigations-risk-signals-popover-open-label',
		];

		if ( $this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			$signals = [];
			$this->hookRunner->onCheckUserSuggestedInvestigationsGetSignals( $signals );
			foreach ( $signals as $signal ) {
				$messages[] = 'checkuser-suggestedinvestigations-risk-signals-popover-body-' . $signal;
				$messages[] = 'checkuser-suggestedinvestigations-signal-' . $signal;
			}
		}

		// We have to define ext.checkUser.suggestedInvestigations unconditionally as it's used by QUnit tests
		// where we cannot enable the feature before the tests run
		$modules['ext.checkUser.suggestedInvestigations'] = [
			'localBasePath' => $dir . 'ext.checkUser.suggestedInvestigations',
			'remoteExtPath' => 'CheckUser/modules/ext.checkUser.suggestedInvestigations',
			'packageFiles' => [
				'index.js',
				'Constants.js',
				'rest.js',
				'utils.js',
				'components/ChangeInvestigationStatusDialog.vue',
				'components/CharacterLimitedTextInput.vue',
				'components/SignalsPopover.vue',
			],
			'messages' => $messages,
			'dependencies' => [
				'@wikimedia/codex',
				'jquery.lengthLimit',
				'mediawiki.api',
				'mediawiki.base',
				'mediawiki.jqueryMsg',
				'mediawiki.language',
				'mediawiki.String',
				'vue',
			],
		];

		if ( count( $modules ) ) {
			$resourceLoader->register( $modules );
		}
	}

}
