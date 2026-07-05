<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\ConfirmEdit\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Registration\ExtensionRegistry;
use MobileContext;

/**
 * Adds a JavaScript configuration variable that indicates what kind of captcha is required to be completed for
 * editing the page that the user is viewing or editing. This is only the generic sense, as the specific content
 * of the edit may cause a captcha to appear at save time.
 *
 * Used by the VisualEditor and MobileFrontend integrations to determine if they need to display hCaptcha to the
 * user.
 */
class MakeGlobalVariablesScriptHookHandler implements MakeGlobalVariablesScriptHook {

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 * @param Config $config
	 * @param VisualEditorAvailabilityLookup|null $visualEditorAvailabilityLookup
	 * @param MobileContext|null $mobileContext
	 */
	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $config,
		private $visualEditorAvailabilityLookup = null,
		private $mobileContext = null
	) {
	}

	/** @inheritDoc */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$mobileFrontendAvailable = $this->extensionRegistry->isLoaded( 'MobileFrontend' );
		$visualEditorAvailable = $this->extensionRegistry->isLoaded( 'VisualEditor' );

		if ( $visualEditorAvailable && $this->visualEditorAvailabilityLookup !== null ) {
			$visualEditorAvailable = $this->visualEditorAvailabilityLookup->isAvailable(
				$out->getTitle(), $out->getRequest(), $out->getUser()
			);
		}

		if ( $mobileFrontendAvailable && $this->mobileContext !== null ) {
			$mobileFrontendAvailable = $this->mobileContext->shouldDisplayMobileView();
		}

		// No code uses the config variables defined here if VisualEditor and
		// MobileFrontend are not loaded or their editors cannot be used for the
		// current request
		if ( !$visualEditorAvailable && !$mobileFrontendAvailable ) {
			return;
		}

		if ( !$out->canUseWikiPage() ) {
			$vars['wgConfirmEditCaptchaNeededForGenericEdit'] = false;
			return;
		}

		$captchaNeededForEdit = false;

		$action = Hooks::getCaptchaTriggerActionFromTitle( $out->getTitle() );
		$captchaInstance = Hooks::getInstance( $action );
		if (
			$captchaInstance->shouldCheck( $out->getWikiPage(), '', '', $out->getContext() )
		) {
			$captchaNeededForEdit = strtolower( $captchaInstance->getName() );
		}

		$vars['wgConfirmEditCaptchaNeededForGenericEdit'] = $captchaNeededForEdit;
		$vars['wgConfirmEditForceShowCaptcha'] = $captchaInstance->shouldForceShowCaptcha();
		if ( $captchaNeededForEdit === 'hcaptcha' ) {
			$vars['wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled'] = $visualEditorAvailable &&
				$this->config->get( 'HCaptchaVisualEditorOnLoadIntegrationEnabled' );

			$vars['wgConfirmEditHCaptchaSiteKey'] = $this->resolveHCaptchaSiteKey( $captchaInstance );
		}

		// We want to load the MobileFrontend hCaptcha module if the user may need
		// to complete hCaptcha for their edit. This is intentionally not based on
		// SimpleCaptcha::shouldCheck because if AbuseFilter is installed then
		// a CAPTCHA may be required based on the content of the edit. Users who
		// can skip captchas are excluded, since they will never be shown one.
		if (
			$mobileFrontendAvailable &&
			$this->config->get( 'HCaptchaEnabledInMobileFrontend' ) &&
			strtolower( $captchaInstance->getName() ) === 'hcaptcha' &&
			!$captchaInstance->canSkipCaptcha( $out->getUser() )
		) {
			$requiredModule = 'ext.confirmEdit.hCaptcha';
			$mfInitModulesKey = 'wgMobileFrontendSourceEditorInitializeModules';
			$modulesToInit = $vars[$mfInitModulesKey] ?? [];

			if ( !in_array( $requiredModule, $modulesToInit ) ) {
				$modulesToInit[] = $requiredModule;
			}

			$vars[$mfInitModulesKey] = $modulesToInit;
			$out->addModules( $requiredModule );

			if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
				$vars['wgConfirmEditMobileHCaptchaAbuseFilterEnabled'] = true;
			}
		}
	}

	private function resolveHCaptchaSiteKey( SimpleCaptcha $captchaInstance ): string {
		$config = $captchaInstance->getConfig();
		$defaultSiteKey = (string)( $config['HCaptchaSiteKey'] ?? $this->config->get( 'HCaptchaSiteKey' ) );
		if ( $captchaInstance->shouldForceShowCaptcha() ) {
			return (string)( $config['HCaptchaAlwaysChallengeSiteKey'] ?? $defaultSiteKey );
		}
		return $defaultSiteKey;
	}

}
