<?php

use Cite\AlphabetsProvider;
use Cite\BacklinkMarkRenderer;
use Cite\MarkSymbolRenderer;
use Cite\ReferenceMessageLocalizer;
use Cite\ReferencePreviews\ReferencePreviewsContext;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * @codeCoverageIgnore
 */
return [
	'Cite.AlphabetsProvider' => static function (): AlphabetsProvider {
		return new AlphabetsProvider();
	},

	'Cite.BacklinkMarkRenderer' => static function ( MediaWikiServices $services ): BacklinkMarkRenderer {
		$contentLanguage = $services->getContentLanguage();
		return new BacklinkMarkRenderer(
			$contentLanguage->getCode(),
			new ReferenceMessageLocalizer(
				$contentLanguage
			),
			$services->getService( 'Cite.AlphabetsProvider' ),
			ExtensionRegistry::getInstance()->isLoaded( 'CommunityConfiguration' ) ?
				$services->getService( 'CommunityConfiguration.ProviderFactory' ) : null,
			$services->getMainConfig()
		);
	},

	'Cite.GadgetsIntegration' => static function ( MediaWikiServices $services ): ReferencePreviewsGadgetsIntegration {
		return new ReferencePreviewsGadgetsIntegration(
			$services->getMainConfig(),
			ExtensionRegistry::getInstance()->isLoaded( 'Gadgets' ) ?
				$services->getService( 'GadgetsRepo' ) :
				null
		);
	},
	'Cite.MarkSymbolRenderer' => static function ( MediaWikiServices $services ): MarkSymbolRenderer {
		return new MarkSymbolRenderer(
			new ReferenceMessageLocalizer(
				$services->getContentLanguage()
			)
		);
	},
	'Cite.ReferencePreviewsContext' => static function ( MediaWikiServices $services ): ReferencePreviewsContext {
		return new ReferencePreviewsContext(
			$services->getMainConfig(),
			$services->getService( 'Cite.GadgetsIntegration' ),
			$services->getUserOptionsLookup()
		);
	},
];
