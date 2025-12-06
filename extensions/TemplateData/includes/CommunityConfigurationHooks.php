<?php
namespace MediaWiki\Extension\TemplateData;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Hooks\CommunityConfigurationProvider_initListHook;

/**
 * @license GPL-2.0-or-later
 */
class CommunityConfigurationHooks implements
	CommunityConfigurationProvider_initListHook
{

	public function __construct( private readonly Config $config ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onCommunityConfigurationProvider_initList( array &$providers ) {
		if ( !$this->config->get( 'TemplateDataEnableFeaturedTemplates' ) ) {
			unset( $providers['TemplateData-FeaturedTemplates'] );
		}
	}

}
