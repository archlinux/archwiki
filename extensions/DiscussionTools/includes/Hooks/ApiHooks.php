<?php
/**
 * DiscussionTools API hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Api\ApiModuleManager;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Extension\DiscussionTools\ApiDiscussionToolsThank;
use MediaWiki\Registration\ExtensionRegistry;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

class ApiHooks implements
	ApiMain__moduleManagerHook
{
	/**
	 * @param ApiModuleManager $moduleManager
	 * @return bool|void
	 */
	public function onApiMain__moduleManager( $moduleManager ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Thanks' ) ) {
			$moduleManager->addModule(
				'discussiontoolsthank',
				'action',
				[
					'class' => ApiDiscussionToolsThank::class,
					'services' => [
						'PermissionManager',
						'ThanksLogStore',
						'RevisionLookup',
						'UserFactory',
					]
				]
			);
		}
	}
}
