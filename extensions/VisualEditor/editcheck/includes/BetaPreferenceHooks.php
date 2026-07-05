<?php

namespace MediaWiki\Extension\VisualEditor\EditCheck;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\BetaFeatures\Hooks\GetBetaFeaturePreferencesHook;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;

/**
 * Hooks from BetaFeatures extension,
 * which is optional to use with this extension.
 */
class BetaPreferenceHooks implements GetBetaFeaturePreferencesHook {

	private readonly Config $config;

	public function __construct(
		private readonly Config $coreConfig,
		ConfigFactory $configFactory
	) {
		$this->config = $configFactory->makeConfig( 'visualeditor' );
	}

	/**
	 * Handler for the GetBetaFeaturePreferences hook, to add and hide user beta preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetBetaFeaturePreferences( User $user, array &$preferences ) {
		if ( $this->config->get( 'VisualEditorEnableEditCheckSuggestionsBeta' ) ) {
			$iconpath = $this->coreConfig->get( MainConfigNames::ExtensionAssetsPath ) .
				'/VisualEditor/editcheck/images';

			$preferences['visualeditor-editcheck-suggestions'] = [
				'version' => '1.0',
				'label-message' => 'editcheck-preference-suggestions-label',
				'desc-message' => 'editcheck-preference-suggestions-description',
				'screenshot' => [
					'ltr' => "$iconpath/betafeatures-icon-editcheck-suggestions-ltr.svg",
					'rtl' => "$iconpath/betafeatures-icon-editcheck-suggestions-rtl.svg",
				],
				'info-message' => 'editcheck-preference-suggestions-info-link',
				'discussion-message' => 'editcheck-preference-suggestions-discussion-link',
				'requirements' => [
					'javascript' => true
				]
			];
		}
	}

}
