<?php

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\Extension\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\Extension\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\Extension\CheckUser\Hook\HookRunner;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\ComparePagerFactory;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\TimelinePagerFactory;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\TimelineRowFormatterFactory;
use MediaWiki\Extension\CheckUser\Investigate\Services\CompareService;
use MediaWiki\Extension\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\Extension\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\Extension\CheckUser\IPContributions\IPContributionsPagerFactory;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Extension\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Extension\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\Extension\CheckUser\Services\CheckUserCentralIndexLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\Extension\CheckUser\Services\CheckUserExpiredIdsLookupService;
use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\CheckUser\Services\CheckUserIPRevealManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Extension\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\Extension\CheckUser\Services\TokenManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\LocalBlockCheck;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters\StatusReasonFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\ISuggestedInvestigationsInstrumentationClient;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\NoOpSuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeBlockChecker;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsUserRevisionLookup;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use Wikimedia\Codex\Utility\Codex;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in CheckUserServiceWiringTest.php
// @codeCoverageIgnoreStart

/** @phpcs-require-sorted-array */
return [
	'AccountCreationDetailsLookup' => static function (
		MediaWikiServices $services
	): AccountCreationDetailsLookup {
		return new AccountCreationDetailsLookup(
			$services->get( 'CheckUserLogger' ),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	'ApiQueryCheckUserResponseFactory' => static function (
		MediaWikiServices $services
	): ApiQueryCheckUserResponseFactory {
		return new ApiQueryCheckUserResponseFactory(
			$services->getConnectionProvider(),
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
	'CheckUserApiRequestAggregator' => static function (
		MediaWikiServices $services
	): CheckUserApiRequestAggregator {
		return new CheckUserApiRequestAggregator(
			$services->getHttpRequestFactory(),
			$services->getCentralIdLookup(),
			$services->getExtensionRegistry(),
			$services->getSiteLookup(),
			$services->get( 'CheckUserLogger' )
		);
	},
	'CheckUserCentralIndexLookup' => static function (
		MediaWikiServices $services
	): CheckUserCentralIndexLookup {
		return new CheckUserCentralIndexLookup(
			$services->getConnectionProvider()
		);
	},
	'CheckUserCentralIndexManager' => static function (
		MediaWikiServices $services
	): CheckUserCentralIndexManager {
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
			$services->get( 'CheckUserLogger' )
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
	'CheckUserCompareService' => static function ( MediaWikiServices $services ): CompareService {
		return new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTempUserConfig()
		);
	},
	'CheckUserCompositeBlockChecker' => static function (
		MediaWikiServices $services
	): CompositeBlockChecker {
		return new CompositeBlockChecker( $services->get( '_CheckUserBlockChecks' ) );
	},
	'CheckUserCompositeIndefiniteBlockChecker' => static function (
		MediaWikiServices $services
	): CompositeIndefiniteBlockChecker {
		return new CompositeIndefiniteBlockChecker( $services->get( '_CheckUserBlockChecks' ) );
	},
	'CheckUserCrossWikiAutoCloseJobDispatcher' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher {
		return new SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher(
			$services->getJobQueueGroupFactory(),
			$services->get( 'CheckUserLogger' ),
			$services->getExtensionRegistry()->isLoaded( 'CentralAuth' ),
			WikiMap::getCurrentWikiId(),
		);
	},
	'CheckUserDataPurger' => static function (): CheckUserDataPurger {
		return new CheckUserDataPurger();
	},
	'CheckUserDurationManager' => static function (): DurationManager {
		return new DurationManager();
	},
	'CheckUserEventLogger' => static function (
		MediaWikiServices $services
	): EventLogger {
		return new EventLogger(
			$services->getExtensionRegistry()
		);
	},
	'CheckUserExpiredIdsLookupService' => static function (
		MediaWikiServices $services
	): CheckUserExpiredIdsLookupService {
		return new CheckUserExpiredIdsLookupService(
			new ServiceOptions(
				CheckUserExpiredIdsLookupService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->getExtensionRegistry()
		);
	},
	'CheckUserGlobalContributionsLookup' => static function (
		MediaWikiServices $services
	): CheckUserGlobalContributionsLookup {
		return new CheckUserGlobalContributionsLookup(
			$services->getConnectionProvider(),
			$services->getExtensionRegistry(),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$services->get( 'CheckUserApiRequestAggregator' ),
			$services->getMainWANObjectCache(),
			$services->getStatsFactory()
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
			$services->get( 'CheckUserGlobalContributionsLookup' ),
			$services->getPermissionManager(),
			$preferencesFactory,
			$services->getConnectionProvider(),
			$services->getJobQueueGroup(),
			$services->getUserLinkRenderer(),
			$services->getRevisionStoreFactory(),
			$services->getChangeTagsStoreFactory(),
			$services->getSiteLookup(),
			$services->getReadOnlyMode()
		);
	},
	'CheckUserHookRunner' => static function (
		MediaWikiServices $services
	): HookRunner {
		return new HookRunner(
			$services->getHookContainer()
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
			$services->getConnectionProvider(),
			$services->getContentLanguage(),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserCentralIndexManager' ),
			$services->get( 'UserAgentClientHintsManager' ),
			$services->getJobQueueGroup(),
			$services->getRecentChangeLookup(),
			$services->get( 'SuggestedInvestigationsSignalMatchService' ),
			$services->get( 'CheckUserLogger' )
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
	'CheckUserIPRevealManager' => static function (
		MediaWikiServices $services
	): CheckUserIPRevealManager {
		return new CheckUserIPRevealManager(
			new ServiceOptions(
				CheckUserIPRevealManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getTempUserConfig(),
			$services->get( 'CheckUserPermissionManager' )
		);
	},
	'CheckUserLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'CheckUser' );
	},
	'CheckUserLogService' => static function (
		MediaWikiServices $services
	): CheckUserLogService {
		return new CheckUserLogService(
			$services->getConnectionProvider(),
			$services->getCommentStore(),
			$services->getCommentFormatter(),
			$services->get( 'CheckUserLogger' ),
			$services->getActorStore(),
			$services->getUserIdentityLookup(),
			new ServiceOptions(
				CheckUserLogService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
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
			$services->getConnectionProvider(),
			$services->getRevisionStore(),
			$services->getArchivedRevisionLookup(),
			$services->get( 'CheckUserLogger' )
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
	'CheckUserPreliminaryCheckPagerFactory' => static function (
		MediaWikiServices $services
	): PreliminaryCheckPagerFactory {
		return new PreliminaryCheckPagerFactory(
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$services->getExtensionRegistry(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$services->get( 'CheckUserPreliminaryCheckService' ),
			$services->getUserFactory()
		);
	},
	'CheckUserPreliminaryCheckService' => static function (
		MediaWikiServices $services
	): PreliminaryCheckService {
		return new PreliminaryCheckService(
			$services->getConnectionProvider(),
			$services->getExtensionRegistry(),
			$services->getUserGroupManagerFactory(),
			$services->getDatabaseBlockStoreFactory(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'CheckUserStatusReasonFormatter' => static function (
		MediaWikiServices $services
	): StatusReasonFormatter {
		return new StatusReasonFormatter(
			$services->getCommentFormatter(),
			$services->getLinkRenderer(),
			$services->getTitleFactory()
		);
	},
	'CheckUserSuggestedInvestigationsCaseLookup' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsCaseLookupService {
		return new SuggestedInvestigationsCaseLookupService(
			new ServiceOptions(
				SuggestedInvestigationsCaseLookupService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->get( 'CheckUserLogger' ),
		);
	},
	'CheckUserSuggestedInvestigationsCaseManager' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsCaseManagerService {
		return new SuggestedInvestigationsCaseManagerService(
			new ServiceOptions(
				SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' )
		);
	},
	'CheckUserSuggestedInvestigationsInstrumentationClient' => static function (
		MediaWikiServices $services
	): ISuggestedInvestigationsInstrumentationClient {
		// If the EventLogging extension is not installed, then return the
		// no-op instrumentation client to allow callers to call it safely
		if ( !$services->has( 'EventLogging.MetricsClientFactory' ) ) {
			return new NoOpSuggestedInvestigationsInstrumentationClient();
		}

		return new SuggestedInvestigationsInstrumentationClient(
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getUserRegistrationLookup(),
			$services->getUserEditTracker(),
			$services->getUserGroupManager(),
			$services->getCentralIdLookup(),
			$services->getExtensionRegistry(),
			$services->get( 'EventLogging.MetricsClientFactory' )
		);
	},
	'CheckUserSuggestedInvestigationsMessageRenderer' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsMessageRenderer {
		return new SuggestedInvestigationsMessageRenderer(
			$services->get( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			new Codex()
		);
	},
	'CheckUserSuggestedInvestigationsPagerFactory' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsPagerFactory {
		$centralAuthEditCounter = null;
		if ( $services->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$centralAuthEditCounter = CentralAuthServices::getEditCounter( $services );
		}
		return new SuggestedInvestigationsPagerFactory(
			$services->getLinkRenderer(),
			$services->getLinkBatchFactory(),
			$services->getHookContainer(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->get( 'CheckUserStatusReasonFormatter' ),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getConnectionProvider(),
			$services->getUserEditTracker(),
			$services->getSpecialPageFactory(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserCompositeBlockChecker' ),
			$services->get( 'CheckUserLogger' ),
			$services->get( 'CheckUserSuggestedInvestigationsMessageRenderer' ),
			$centralAuthEditCounter
		);
	},
	'CheckUserSuggestedInvestigationsUserRevisionLookup' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsUserRevisionLookup {
		return new SuggestedInvestigationsUserRevisionLookup(
			$services->getDBLoadBalancerFactory(),
		);
	},
	'CheckUserTemporaryAccountAutoRevealLookup' => static function (
		MediaWikiServices $services
	): CheckUserTemporaryAccountAutoRevealLookup {
		return new CheckUserTemporaryAccountAutoRevealLookup(
			new ServiceOptions(
				CheckUserTemporaryAccountAutoRevealLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getPreferencesFactory(),
			$services->get( 'CheckUserPermissionManager' )
		);
	},
	'CheckUserTemporaryAccountLoggerFactory' => static function (
		MediaWikiServices $services
	): TemporaryAccountLoggerFactory {
		return new TemporaryAccountLoggerFactory(
			$services->getActorStore(),
			$services->get( 'CheckUserLogger' ),
			$services->getConnectionProvider(),
			$services->getTitleFactory()
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
			$services->getConnectionProvider(),
			$services->getJobQueueGroup(),
			$services->getTempUserConfig(),
			$services->getUserFactory(),
			$services->getPermissionManager(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserOptionsLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getReadOnlyMode()
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
			$services->get( 'CheckUserLogger' )
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
	'CheckUserTimelineService' => static function ( MediaWikiServices $services ): TimelineService {
		return new TimelineService(
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getTempUserConfig()
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
	'CheckUserUserInfoCardBlockStatusCache' => static function (
		MediaWikiServices $services
	): UserInfoCardBlockStatusCache {
		$globalBlockChecks = [];
		if ( $services->getExtensionRegistry()->isLoaded( 'GlobalBlocking' ) ) {
			$globalBlockingServices = GlobalBlockingServices::wrap( $services );
			$globalBlockChecks[] = new GlobalBlockCheck(
				$globalBlockingServices->getGlobalBlockLookup(),
				$services->getCentralIdLookup(),
				$services->getUserIdentityLookup(),
				$services->getMainConfig()->get( 'ApplyGlobalBlocks' )
			);
		}
		if ( $services->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$globalBlockChecks[] = new CentralAuthLockCheck(
				CentralAuthServices::getGlobalUserSelectQueryBuilderFactory( $services ),
				$services->getUserIdentityLookup()
			);
		}
		return new UserInfoCardBlockStatusCache(
			$services->getMainWANObjectCache(),
			new CompositeIndefiniteBlockChecker(
				[ new LocalBlockCheck( $services->getDatabaseBlockStore() ) ]
			),
			new CompositeIndefiniteBlockChecker( $globalBlockChecks ),
			$services->getUserIdentityLookup(),
			$services->getStatsFactory()
		);
	},
	'CheckUserUserInfoCardService' => static function (
		MediaWikiServices $services
	): CheckUserUserInfoCardService {
		$extensionRegistry = $services->getExtensionRegistry();
		$userImpactLookup = null;
		if ( $extensionRegistry->isLoaded( 'GrowthExperiments' ) ) {
			$userImpactLookup = $services->getService( 'GrowthExperimentsUserImpactLookup' );
		}
		$globalContributionsLookup = null;
		if ( $extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$globalContributionsLookup = $services->get( 'CheckUserGlobalContributionsLookup' );
		}
		return new CheckUserUserInfoCardService(
			$userImpactLookup,
			$extensionRegistry,
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$globalContributionsLookup,
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->get( 'CheckUserTemporaryAccountsByIPLookup' ),
			new DerivativeContext( RequestContext::getMain() ),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			$services->getTempUserConfig(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getCentralIdLookup(),
			$services->get( 'CheckUserUserInfoCardBlockStatusCache' )
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
	'SuggestedInvestigationsSignalMatchService' => static function (
		MediaWikiServices $services
	): SuggestedInvestigationsSignalMatchService {
		return new SuggestedInvestigationsSignalMatchService(
			new ServiceOptions(
				SuggestedInvestigationsSignalMatchService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'CheckUserHookRunner' ),
			$services->get( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$services->get( 'CheckUserSuggestedInvestigationsCaseManager' ),
			$services->getJobQueueGroup(),
			$services->get( 'CheckUserLogger' ),
			$services->get( 'CheckUserSuggestedInvestigationsUserRevisionLookup' ),
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
	'UserAgentClientHintsLookup' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsLookup {
		return new UserAgentClientHintsLookup(
			$services->getConnectionProvider()->getReplicaDatabase()
		);
	},
	'UserAgentClientHintsManager' => static function (
		MediaWikiServices $services
	): UserAgentClientHintsManager {
		return new UserAgentClientHintsManager(
			$services->getConnectionProvider(),
			$services->getRevisionStore(),
			new ServiceOptions(
				UserAgentClientHintsManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'CheckUserLogger' )
		);
	},
	'_CheckUserBlockChecks' => static function ( MediaWikiServices $services ): array {
		$blockChecks = [
			new LocalBlockCheck( $services->getDatabaseBlockStore() ),
		];

		if ( $services->getExtensionRegistry()->isLoaded( 'GlobalBlocking' ) ) {
			$globalBlockingServices = GlobalBlockingServices::wrap( $services );

			$blockChecks[] = new GlobalBlockCheck(
				$globalBlockingServices->getGlobalBlockLookup(),
				$services->getCentralIdLookup(),
				$services->getUserIdentityLookup(),
				$services->getMainConfig()->get( 'ApplyGlobalBlocks' )
			);
		}

		if ( $services->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$blockChecks[] = new CentralAuthLockCheck(
				CentralAuthServices::getGlobalUserSelectQueryBuilderFactory( $services ),
				$services->getUserIdentityLookup()
			);
		}

		return $blockChecks;
	},
];
// @codeCoverageIgnoreEnd
