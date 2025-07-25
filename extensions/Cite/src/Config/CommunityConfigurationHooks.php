<?php

namespace Cite\Config;

use MediaWiki\Extension\CommunityConfiguration\Hooks\CommunityConfigurationProvider_initListHook;

/**
 * @license GPL-2.0-or-later
 */
class CommunityConfigurationHooks implements CommunityConfigurationProvider_initListHook {

	/**
	 * @inheritDoc
	 */
	public function onCommunityConfigurationProvider_initList( array &$providers ) {
		if ( !CommunityConfigurationUtils::useCommunityConfiguration() ) {
			// Do not show the Cite provider in the dashboard when disabled
			unset( $providers['Cite'] );
		}
	}
}
