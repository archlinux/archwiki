<?php

namespace Cite\Config;

use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * @license GPL-2.0-or-later
 */
class CommunityConfigurationUtils {

	public static function useCommunityConfiguration(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'CommunityConfiguration' ) &&
			MediaWikiServices::getInstance()->getMainConfig()->get( 'CiteBacklinkCommunityConfiguration' );
	}
}
