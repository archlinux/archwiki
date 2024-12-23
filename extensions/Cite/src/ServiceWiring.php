<?php

use Cite\ReferencePreviews\ReferencePreviewsContext;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * @codeCoverageIgnore
 */
return [
	'Cite.GadgetsIntegration' => static function ( MediaWikiServices $services ): ReferencePreviewsGadgetsIntegration {
		return new ReferencePreviewsGadgetsIntegration(
			$services->getMainConfig(),
			ExtensionRegistry::getInstance()->isLoaded( 'Gadgets' ) ?
				$services->getService( 'GadgetsRepo' ) :
				null
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
