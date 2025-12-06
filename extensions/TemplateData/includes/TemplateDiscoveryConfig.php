<?php

namespace MediaWiki\Extension\TemplateData;

use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @license GPL-2.0-or-later
 */
class TemplateDiscoveryConfig {

	public static function getConfig( Context $context, Config $config ): array {
		$extRegistry = ExtensionRegistry::getInstance();

		$categoryRootCat = null;
		if ( $config->get( 'TemplateDataEnableCategoryBrowser' ) ) {
			$catBrowserItemId = $config->get( 'TemplateDataCategoryBrowserItem' );
			if ( $catBrowserItemId && $extRegistry->isLoaded( 'WikibaseClient' ) ) {
				$entityLookup = WikibaseClient::getStore()->getEntityLookup();
				$entity = $entityLookup->getEntity( new ItemId( $catBrowserItemId ) );
				if ( $entity instanceof Item ) {
					// If Wikibase is installed, and the item exists, try to get the sitelink's page title.
					try {
						$pageName = $entity->getSiteLink( WikiMap::getCurrentWikiId() )->getPageName();
						$page = Title::newFromText( $pageName );
						if ( $page && $page->inNamespaces( NS_CATEGORY ) ) {
							$categoryRootCat = $page->getBaseText();
						}
					} catch ( Exception ) {
						// e.g. if the wiki ID doesn't exist.
					}
				}
			}
		}
		if ( $categoryRootCat === null ) {
			// If Wikibase isn't installed, or no root cat was configured or able to be retrieved.
			$categoryRootCat = $context->msg( 'templatedata-category-rootcat' )->inContentLanguage()->text();
		}

		return [
			'cirrusSearchLoaded' => $extRegistry->isLoaded( 'CirrusSearch' ),
			'communityConfigurationLoaded' => $extRegistry->isLoaded( 'CommunityConfiguration' ),
			'maxFavorites' => $config->get( 'TemplateDataMaxFavorites' ),
			'categoryRootCat' => $categoryRootCat,
		];
	}

}
