<?php

namespace MediaWiki\Extension\TemplateData;

use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;

/**
 * @license GPL-2.0-or-later
 */
class TemplateDiscoveryConfig {

	public static function getConfig( Context $context, Config $config ): array {
		return [
			'cirrusSearchLoaded' => ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' ),
			'maxFavorites' => $config->get( 'TemplateDataMaxFavorites' ),
		];
	}

}
