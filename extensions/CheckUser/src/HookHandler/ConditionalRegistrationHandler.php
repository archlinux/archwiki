<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Api\Hook\ApiQuery__moduleManagerHook;
use MediaWiki\CheckUser\Api\GlobalContributions\ApiQueryGlobalContributions;
use MediaWiki\CheckUser\GlobalContributions\SpecialGlobalContributions;
use MediaWiki\CheckUser\IPContributions\SpecialIPContributions;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\WikiMap\WikiMap;

// The name of onSpecialPage_initList raises the following phpcs error. As the
// name is defined in core, this is an unavoidable issue and therefore the check
// is disabled.
//
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

/**
 * Conditionally register special pages and API modules that have additional dependencies
 * or require extra configuration.
 */
class ConditionalRegistrationHandler implements SpecialPage_initListHook, ApiQuery__moduleManagerHook {

	private Config $config;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;

	public function __construct(
		Config $config,
		TempUserConfig $tempUserConfig,
		ExtensionRegistry $extensionRegistry
	) {
		$this->config = $config;
		$this->tempUserConfig = $tempUserConfig;
		$this->extensionRegistry = $extensionRegistry;
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->tempUserConfig->isKnown() ) {
			$list['IPContributions'] = [
				'class' => SpecialIPContributions::class,
				'services' => [
					'PermissionManager',
					'ConnectionProvider',
					'NamespaceInfo',
					'UserNameUtils',
					'UserNamePrefixSearch',
					'UserOptionsLookup',
					'UserFactory',
					'UserIdentityLookup',
					'DatabaseBlockStore',
					'CheckUserIPContributionsPagerFactory',
					'CheckUserPermissionManager',
				],
			];
		}

		// Use of Special:GlobalContributions depends on:
		// - the user enabling IP reveal globally via GlobalPreferences
		// - CentralAuth being enabled to support cross-wiki lookups
		// It also requires temp users to be known to this wiki, or for there
		// to be a central wiki that Special:GlobalContributions redirects to.
		if (
			(
				$this->tempUserConfig->isKnown() ||
				$this->config->get( 'CheckUserGlobalContributionsCentralWikiId' )
			) &&
			$this->areGlobalContributionsDependenciesMet()
		) {
			$list['GlobalContributions'] = [
				'class' => SpecialGlobalContributions::class,
				'services' => [
					'PermissionManager',
					'ConnectionProvider',
					'NamespaceInfo',
					'UserNameUtils',
					'UserNamePrefixSearch',
					'UserOptionsLookup',
					'UserFactory',
					'UserIdentityLookup',
					'DatabaseBlockStore',
					'CentralIdLookup',
					'CheckUserGlobalContributionsPagerFactory',
					'StatsFactory',
				],
			];
		}

		return true;
	}

	/** @inheritDoc */
	public function onApiQuery__moduleManager( $moduleManager ) {
		$wikiId = WikiMap::getCurrentWikiId();
		// The GlobalContributions API should only be available on the central wiki
		// and only if all extension dependencies are met.
		if (
			$wikiId === $this->config->get( 'CheckUserGlobalContributionsCentralWikiId' ) &&
			$this->areGlobalContributionsDependenciesMet()
		) {
			$moduleManager->addModule( 'globalcontributions', 'list', [
				'class' => ApiQueryGlobalContributions::class,
				'services' => [
					'CheckUserGlobalContributionsPagerFactory',
					'UserNameUtils',
				],
			] );
		}
	}

	/**
	 * Are all extension dependencies of Special:GlobalContributions available?
	 * @return bool
	 */
	private function areGlobalContributionsDependenciesMet(): bool {
		return $this->extensionRegistry->isLoaded( 'GlobalPreferences' ) &&
			$this->extensionRegistry->isLoaded( 'CentralAuth' );
	}
}
