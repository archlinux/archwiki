<?php
/**
 * DiscussionTools API hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiEditPage;
use MediaWiki\Api\ApiModuleManager;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Extension\DiscussionTools\ApiDiscussionToolsThank;
use MediaWiki\Extension\DiscussionTools\Notifications\EventDispatcher;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ParamValidator\ParamValidator;

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

	/**
	 * @param ApiBase $module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags
	 * @return bool
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if ( $module instanceof ApiEditPage ) {
			$params['discussiontoolsautosubscribe'] = [
				ParamValidator::PARAM_TYPE => [
					'yes',
					'no',
					'preferences',
				],
				ParamValidator::PARAM_DEFAULT => 'preferences',
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-discussiontoolsautosubscribe',
			];
		}
		return true;
	}

	/**
	 * @param ApiBase $module
	 */
	public function onAPIAfterExecute( $module ) {
		if ( $module instanceof ApiEditPage ) {
			$dtAutoSubscribe = $module->extractRequestParams( [
				'safeMode' => true,
			] )['discussiontoolsautosubscribe'];

			// HACK: Ideally we would pass this through properly in the edit result,
			// rather than setting a static property.
			EventDispatcher::setAutosubscribe( $dtAutoSubscribe );
		}
	}
}
