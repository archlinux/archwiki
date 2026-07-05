<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Api\Hook\ApiQuery__moduleManagerHook;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Api\GlobalContributions\ApiQueryGlobalContributions;
use MediaWiki\Extension\CheckUser\GlobalContributions\SpecialGlobalContributions;
use MediaWiki\Extension\CheckUser\IPContributions\SpecialIPContributions;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\WikiMap\WikiMap;

/**
 * Conditionally register special pages and API modules that have additional dependencies
 * or require extra configuration.
 */
class ConditionalRegistrationHandler implements SpecialPage_initListHook, ApiQuery__moduleManagerHook {

	public function __construct(
		private readonly Config $config,
		private readonly TempUserConfig $tempUserConfig,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
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
					'UserGroupAssignmentService',
					'CheckUserIPContributionsPagerFactory',
					'CheckUserPermissionManager',
				],
			];
		}

		// Use of Special:GlobalContributions depends on:
		// - the user enabling IP reveal globally via GlobalPreferences
		// - CentralAuth being enabled to support cross-wiki lookups
		// It also requires temp users to be known to this wiki, or for there
		// to be a (remote) central wiki that Special:GlobalContributions redirects to.
		$gcCentralWiki = $this->config->get( 'CheckUserGlobalContributionsCentralWikiId' );
		if (
			(
				$this->tempUserConfig->isKnown() ||
				( $gcCentralWiki && $gcCentralWiki !== WikiMap::getCurrentWikiId() )
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
					'UserGroupAssignmentService',
					'CentralIdLookup',
					'CheckUserGlobalContributionsPagerFactory',
					'StatsFactory',
				],
			];
		}

		if (
			$this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ) &&
			!$this->config->get( 'CheckUserSuggestedInvestigationsHidden' )
		) {
			$list['SuggestedInvestigations'] = [
				'class' => SpecialSuggestedInvestigations::class,
				'services' => [
					'CheckUserHookRunner',
					'CheckUserSuggestedInvestigationsCaseLookup',
					'CheckUserSuggestedInvestigationsInstrumentationClient',
					'CheckUserSuggestedInvestigationsPagerFactory',
					'CheckUserSuggestedInvestigationsMessageRenderer',
					'UserOptionsLookup',
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
