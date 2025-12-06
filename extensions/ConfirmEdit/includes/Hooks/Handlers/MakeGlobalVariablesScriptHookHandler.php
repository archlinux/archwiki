<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\ConfirmEdit\Hooks\Handlers;

use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\Hooks;
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
	 * @param VisualEditorAvailabilityLookup|null $visualEditorAvailabilityLookup
	 * @param MobileContext|null $mobileContext
	 */
	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
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

		$captchaNeededForEdit = false;

		$action = $out->getTitle()->exists() ?
			CaptchaTriggers::EDIT :
			CaptchaTriggers::CREATE;
		$captchaInstance = Hooks::getInstance( $action );
		if (
			$out->canUseWikiPage() &&
			$captchaInstance->shouldCheck( $out->getWikiPage(), '', '', $out->getContext() )
		) {
			$captchaNeededForEdit = strtolower( $captchaInstance->getName() );
		}

		$vars['wgConfirmEditCaptchaNeededForGenericEdit'] = $captchaNeededForEdit;
		if ( $captchaNeededForEdit ) {
			$vars['wgConfirmEditHCaptchaSiteKey'] =
				$captchaInstance->getConfig()['HCaptchaSiteKey'] ??
				$out->getConfig()->get( 'HCaptchaSiteKey' );
		}
	}
}
