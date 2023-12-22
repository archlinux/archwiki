<?php

namespace MediaWiki\Minerva;

use MediaWiki\MediaWikiServices;
use MobileContext;
use MobileFrontend\Features\Feature;
use MobileFrontend\Features\FeaturesManager;
use MobileFrontend\Hooks\MobileFrontendFeaturesRegistrationHook;
use MobileFrontend\Hooks\RequestContextCreateSkinMobileHook;
use RuntimeException;
use Skin;

/**
 * Hooks from MobileFrontend extension,
 * which is optional to use with this skin.
 */
class MobileFrontendHooks implements
	MobileFrontendFeaturesRegistrationHook,
	RequestContextCreateSkinMobileHook
{

	/**
	 * Register mobile web beta features
	 * @see https://www.mediawiki.org/wiki/
	 *  Extension:MobileFrontend/MobileFrontendFeaturesRegistration
	 *
	 * @param FeaturesManager $featureManager
	 */
	public function onMobileFrontendFeaturesRegistration( FeaturesManager $featureManager ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'minerva' );

		try {
			$featureManager->registerFeature(
				new Feature(
					'MinervaShowCategories',
					'skin-minerva',
					$config->get( 'MinervaShowCategories' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaPageIssuesNewTreatment',
					'skin-minerva',
					$config->get( 'MinervaPageIssuesNewTreatment' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaTalkAtTop',
					'skin-minerva',
					$config->get( 'MinervaTalkAtTop' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaDonateLink',
					'skin-minerva',
					$config->get( 'MinervaDonateLink' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaHistoryInPageActions',
					'skin-minerva',
					$config->get( 'MinervaHistoryInPageActions' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					Hooks::FEATURE_OVERFLOW_PAGE_ACTIONS,
					'skin-minerva',
					$config->get( Hooks::FEATURE_OVERFLOW_PAGE_ACTIONS )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaAdvancedMainMenu',
					'skin-minerva',
					$config->get( 'MinervaAdvancedMainMenu' )
				)
			);
			$featureManager->registerFeature(
				new Feature(
					'MinervaPersonalMenu',
					'skin-minerva',
					$config->get( 'MinervaPersonalMenu' )
				)
			);
		} catch ( RuntimeException $e ) {
			// features already registered...
			// due to a bug it's possible for this to run twice
			// https://phabricator.wikimedia.org/T165068
		}
	}

	/**
	 * BeforePageDisplayMobile hook handler.
	 *
	 * @param MobileContext $mobileContext
	 * @param Skin $skin
	 */
	public function onRequestContextCreateSkinMobile(
		MobileContext $mobileContext, Skin $skin
	) {
		Hooks::setMinervaSkinOptions( $mobileContext, $skin );
	}
}
