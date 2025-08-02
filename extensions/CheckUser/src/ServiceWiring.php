<?php

use GlobalPreferences\GlobalPreferencesFactory;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\CheckUser\GuidedTour\TourLauncher;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\TimelinePagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatterFactory;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\CheckUser\IPContributions\IPContributionsPagerFactory;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in CheckUserServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'CheckUserLogService' => static function (
		MediaWikiServices $services
	): CheckUserLogService {
		return new CheckUserLogService(
			$services->getDBLoadBalancerFactory(),
			$services->getCommentStore(),
			$services->getCommentFormatter(),
			LoggerFactory::getInstance( 'CheckUser' ),
			$services->getActorStore(),
			$services->getUserIdentityLookup()
		);
	},
	'CheckUserPreliminaryCheckService' => static function (
		MediaWikiServices $services
	): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getDBLoadBalancerFactory(),
			ExtensionRegistry::getInstance(),
			$services->getUserGroupManagerFactory(),
			$services->getDatabaseBlockStoreFactory(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'CheckUserCompareService' => static function ( MediaWikiServices $services ): CompareService {
		return new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' )
		);
	},
	'CheckUserTimelineService' => static function ( MediaWikiServices $services ): TimelineService {
		return new TimelineService(
			$services->getDBLoadBalancerFactory(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' )
		);
	},
	'CheckUserTokenManager' => static function ( MediaWikiServices $services ): TokenManager {
		return new TokenManager(
			$services->getMainConfig()->get( 'SecretKey' )
		);
	},
	'CheckUserTokenQueryManager' => static function ( MediaWikiServices $services ): TokenQueryManager {
		return new TokenQueryManager(
			$services->get( 'CheckUserTokenManager' )
		);
	},
	'CheckUserDurationManager' => static function ( MediaWikiServices $services ): DurationManager {
		return new DurationManager();
	},
	'CheckUserGuidedTourLauncher' => static function ( MediaWikiServices $services ): TourLauncher {
		return new TourLauncher(
			ExtensionRegistry::getInstance(),
			$services->getLinkRenderer()
		);
	},
	'CheckUserPreliminaryCheckPagerFactory' => static function (
		MediaWikiServices $services
	): PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			ExtensionRegistry::getInstance(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' ),
			$services->getUserFactory()
		);
	},
	'CheckUserComparePagerFactory' => static function ( MediaWikiServices $services ): ComparePagerFactory {
		return new ComparePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserCompareService' ),
			$services->getUserFactory(),
			$services->getLinkBatchFactory()
		);
	},
	'CheckUserTimelineRowFormatterFactory' => static function (
		MediaWikiServices $services
	): TimelineRowFormatterFactory {
		return new TimelineRowFormatterFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTitleFormatter(),
			$services->getSpecialPageFactory(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getCommentStore(),
			$services->getLogFormatterFactory()
		);
	},
	'CheckUserTimelinePagerFactory' => static function (
		MediaWikiServices $services
	): TimelinePagerFactory {
		return new TimelinePagerFactory(
			$services->getLinkRenderer(),
			$services->get( 'CheckUserHookRunner' ),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserDurationManager' ),
			$services->get( 'CheckUserTimelineService' ),
			$services->get( 'CheckUserTimelineRowFormatterFactory' ),
			$services->getLinkBatchFactory(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserIPContributionsPagerFactory' => static function (
		MediaWikiServices $services
	): IPContributionsPagerFactory {
		return new IPContributionsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getTempUserConfig(),
			$services->getMainConfig(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getJobQueueGroup()
		);
	},
	'CheckUserGlobalContributionsPagerFactory' => static function (
		MediaWikiServices $services
	): GlobalContributionsPagerFactory {
		$preferencesFactory = $services->getPreferencesFactory();
		if ( !( $preferencesFactory instanceof GlobalPreferencesFactory ) ) {
			throw new LogicException(
				'Cannot instantiate GlobalContributionsPagerFactory without GlobalPreferences'
			);
		}
		return new GlobalContributionsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getTempUserConfig(),
			$services->getMainConfig(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserApiRequestAggregator' ),
			$services->get( 'CheckUserGlobalContributionsLookup' ),
			$services->getPermissionManager(),
			$preferencesFactory,
			$services->getDBLoadBalancerFactory(),
			$services->getJobQueueGroup(),
			$services->getStatsFactory()
		);
	},
	'CheckUserApiRequestAggregator' => static function (
		 MediaWikiServices $services
	): CheckUserApiRequestAggregator {
		$config = $services->getMainConfig();
		return new CheckUserApiRequestAggregator(
			$services->getHttpRequestFactory(),
			$services->getCentralIdLookup(),
			$services->getExtensionRegistry(),
			$services->getSiteLookup(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserGlobalContributionsLookup' => static function (
		MediaWikiServices $services
	): CheckUserGlobalContributionsLookup {
		return new CheckUserGlobalContributionsLookup(
			$services->getDBLoadBalancerFactory(),
			$services->getExtensionRegistry(),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig()
		);
	},
	'CheckUserEventLogger' => static function (
		 MediaWikiServices $services
	): EventLogger {
		return new EventLogger(
			ExtensionRegistry::getInstance()
		);
	},
	'CheckUserHookRunner' => static function (
		MediaWikiServices $services
	): HookRunner {
		return new HookRunner(
			$services->getHookContainer()
		);
	},
	'CheckUserUtilityService' => static function (
		MediaWikiServices $services
	): CheckUserUtilityService {
		return new CheckUserUtilityService(
			$services->getProxyLookup(),
			$services->getMainConfig()->get( 'UsePrivateIPs' )
		);
	},
	'CheckUserLookupUtils' => static function (
		MediaWikiServices $services
	): CheckUserLookupUtils {
		return new CheckUserLookupUtils(
			new ServiceOptions(
				CheckUserLookupUtils::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserInsert' => static function (
		MediaWikiServices $services
	): CheckUserInsert {
		return new CheckUserInsert(
			new ServiceOptions(
				CheckUserInsert::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getActorStore(),
			$services->get( 'CheckUserUtilityService' ),
			$services->getCommentStore(),
			$services->getHookContainer(),
			$services->getDBLoadBalancerFactory(),
			$services->getContentLanguage(),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserCentralIndexManager' ),
			$services->get( 'UserAgentClientHintsManager' ),
			$services->getJobQueueGroup(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserDataPurger' => static function () {
		return new CheckUserDataPurger();
	},
	'CheckUserCentralIndexManager' => static function (
		MediaWikiServices $services
	) {
		return new CheckUserCentralIndexManager(
			new ServiceOptions(
				CheckUserCentralIndexManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getCentralIdLookup(),
			$services->getUserGroupManager(),
			$services->getJobQueueGroup(),
			$services->getTempUserConfig(),
			$services->getUserFactory(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'CheckUserTemporaryAccountLoggerFactory' => static function (
		MediaWikiServices $services
	): TemporaryAccountLoggerFactory {
		return new TemporaryAccountLoggerFactory(
			$services->getActorStore(),
			LoggerFactory::getInstance( 'CheckUser' ),
			$services->getDBLoadBalancerFactory(),
			$services->getTitleFactory()
		);
	},
	'UserAgentClientHintsManager' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsManager {
		return new UserAgentClientHintsManager(
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			new ServiceOptions(
				UserAgentClientHintsManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			LoggerFactory::getInstance( 'CheckUser' )
		);
	},
	'UserAgentClientHintsLookup' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsLookup {
		return new UserAgentClientHintsLookup(
			$services->getDBLoadBalancerFactory()->getReplicaDatabase()
		);
	},
	'UserAgentClientHintsFormatter' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsFormatter {
		return new UserAgentClientHintsFormatter(
			new DerivativeContext( RequestContext::getMain() ),
			new ServiceOptions(
				UserAgentClientHintsFormatter::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'ApiQueryCheckUserResponseFactory' => static function (
		MediaWikiServices $services
	): ApiQueryCheckUserResponseFactory {
		return new ApiQueryCheckUserResponseFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getMainConfig(),
			RequestContext::getMain(),
			$services->get( 'CheckUserLogService' ),
			$services->getUserNameUtils(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getUserIdentityLookup(),
			$services->getCommentStore(),
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
			$services->getUserFactory(),
			$services->getLogFormatterFactory()
		);
	},
	'AccountCreationDetailsLookup' => static function (
		MediaWikiServices $services
	): AccountCreationDetailsLookup {
		return new AccountCreationDetailsLookup(
			LoggerFactory::getInstance( 'CheckUser' ),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'CheckUserTemporaryAccountsByIPLookup' => static function (
		MediaWikiServices $services
	): CheckUserTemporaryAccountsByIPLookup {
		return new CheckUserTemporaryAccountsByIPLookup(
			new ServiceOptions(
				CheckUserTemporaryAccountsByIPLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$services->getJobQueueGroup(),
			$services->getTempUserConfig(),
			$services->getUserFactory(),
			$services->getPermissionManager(),
			$services->getUserOptionsLookup()
		);
	},
	'CheckUserPermissionManager' => static function ( MediaWikiServices $services ): CheckUserPermissionManager {
		return new CheckUserPermissionManager(
			$services->getUserOptionsLookup(),
			$services->getSpecialPageFactory(),
			$services->getCentralIdLookup(),
			$services->getUserFactory()
		);
	},
	'CheckUserUserInfoCardService' => static function (
		MediaWikiServices $services
	): CheckUserUserInfoCardService {
		$userImpactLookup = $services->getService( 'GrowthExperimentsUserImpactLookup' );
		if ( !( $userImpactLookup instanceof UserImpactLookup ) ) {
			throw new LogicException(
				'Cannot instantiate CheckUserUserInfoCardService without UserImpactLookup'
			);
		}
		return new CheckUserUserInfoCardService(
			$userImpactLookup,
			$services->getExtensionRegistry(),
			$services->getUserOptionsLookup(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager()
		);
	},
];
// @codeCoverageIgnoreEnd
