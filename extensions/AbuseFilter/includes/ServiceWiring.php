<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterLogDetailsLookup;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager as PermManager;
use MediaWiki\Extension\AbuseFilter\AbuseLogConditionFactory;
use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainConfigProvider;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainFilter;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainValidator;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\CustomBlockedDomainStorage;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\IBlockedDomainFilter;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\IBlockedDomainStorage;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\NoopBlockedDomainFilter;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagValidator;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutor;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutorFactory as ConsExecutorFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesLookup;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\EditRevUpdater;
use MediaWiki\Extension\AbuseFilter\EmergencyCache;
use MediaWiki\Extension\AbuseFilter\FilterCompare;
use MediaWiki\Extension\AbuseFilter\FilterImporter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\FilterProfiler;
use MediaWiki\Extension\AbuseFilter\FilterRunner;
use MediaWiki\Extension\AbuseFilter\FilterRunnerFactory;
use MediaWiki\Extension\AbuseFilter\FilterStore;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\FilterValidator;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Extension\AbuseFilter\TemporaryAccountIPsViewerSpecification;
use MediaWiki\Extension\AbuseFilter\TextExtractor;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Extension\AbuseFilter\Variables\AbuseFilterProtectedVariablesLookup;
use MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Extension\AbuseFilter\Watcher\EmergencyWatcher;
use MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Equivset\Equivset;

// This file is actually covered by AbuseFilterServicesTest, but it's not possible to specify a path
// in @covers annotations (https://github.com/sebastianbergmann/phpunit/issues/3794)
// @codeCoverageIgnoreStart

return [
	ServiceNames::AbuseFilterHookRunner => static function ( MediaWikiServices $services ): AbuseFilterHookRunner {
		return new AbuseFilterHookRunner( $services->getHookContainer() );
	},
	ServiceNames::KeywordsManager => static function ( MediaWikiServices $services ): KeywordsManager {
		return new KeywordsManager( $services->get( AbuseFilterHookRunner::SERVICE_NAME ) );
	},
	ServiceNames::FilterProfiler => static function ( MediaWikiServices $services ): FilterProfiler {
		return new FilterProfiler(
			$services->getWRStatsFactory(),
			new ServiceOptions(
				FilterProfiler::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			WikiMap::getCurrentWikiDbDomain()->getId(),
			$services->getStatsdDataFactory(),
			LoggerFactory::getInstance( 'AbuseFilter' )
		);
	},
	ServiceNames::PermManager => static function ( MediaWikiServices $services ): PermManager {
		return new PermManager(
			$services->getTempUserConfig(),
			$services->getExtensionRegistry(),
			$services->get( AbuseFilterProtectedVariablesLookup::SERVICE_NAME ),
			$services->get( RuleCheckerFactory::SERVICE_NAME ),
			$services->get( AbuseFilterHookRunner::SERVICE_NAME )
		);
	},
	ServiceNames::ChangeTagger => static function ( MediaWikiServices $services ): ChangeTagger {
		return new ChangeTagger(
			$services->getService( ChangeTagsManager::SERVICE_NAME )
		);
	},
	ServiceNames::ChangeTagsManager => static function ( MediaWikiServices $services ): ChangeTagsManager {
		return new ChangeTagsManager(
			$services->getChangeTagsStore(),
			$services->getDBLoadBalancerFactory(),
			$services->getMainWANObjectCache(),
			$services->get( CentralDBManager::SERVICE_NAME )
		);
	},
	ServiceNames::ChangeTagValidator => static function ( MediaWikiServices $services ): ChangeTagValidator {
		return new ChangeTagValidator(
			$services->getService( ChangeTagsManager::SERVICE_NAME )
		);
	},
	ServiceNames::CentralDBManager => static function ( MediaWikiServices $services ): CentralDBManager {
		return new CentralDBManager(
			$services->getDBLoadBalancerFactory(),
			$services->getMainConfig()->get( 'AbuseFilterCentralDB' ),
			$services->getMainConfig()->get( 'AbuseFilterIsCentral' )
		);
	},
	ServiceNames::BlockAutopromoteStore => static function ( MediaWikiServices $services ): BlockAutopromoteStore {
		return new BlockAutopromoteStore(
			$services->getMainObjectStash(),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->get( FilterUser::SERVICE_NAME )
		);
	},
	ServiceNames::FilterUser => static function ( MediaWikiServices $services ): FilterUser {
		return new FilterUser(
			// TODO We need a proper MessageLocalizer, see T247127
			RequestContext::getMain(),
			$services->getUserGroupManager(),
			$services->getUserNameUtils(),
			LoggerFactory::getInstance( 'AbuseFilter' )
		);
	},
	ServiceNames::RuleCheckerFactory => static function ( MediaWikiServices $services ): RuleCheckerFactory {
		return new RuleCheckerFactory(
			$services->getContentLanguage(),
			$services->getObjectCacheFactory()->getLocalServerInstance( CACHE_HASH ),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->getService( KeywordsManager::SERVICE_NAME ),
			$services->get( VariablesManager::SERVICE_NAME ),
			$services->getStatsdDataFactory(),
			new Equivset(),
			$services->getMainConfig()->get( 'AbuseFilterConditionLimit' )
		);
	},
	ServiceNames::FilterLookup => static function ( MediaWikiServices $services ): FilterLookup {
		return new FilterLookup(
			$services->getDBLoadBalancer(),
			$services->getMainWANObjectCache(),
			$services->get( CentralDBManager::SERVICE_NAME )
		);
	},
	ServiceNames::EmergencyCache => static function ( MediaWikiServices $services ): EmergencyCache {
		return new EmergencyCache(
			$services->getMainObjectStash(),
			$services->getMainConfig()->get( 'AbuseFilterEmergencyDisableAge' )
		);
	},
	ServiceNames::EmergencyWatcher => static function ( MediaWikiServices $services ): EmergencyWatcher {
		return new EmergencyWatcher(
			$services->getService( EmergencyCache::SERVICE_NAME ),
			$services->getDBLoadBalancerFactory(),
			$services->getService( FilterLookup::SERVICE_NAME ),
			$services->getService( EchoNotifier::SERVICE_NAME ),
			new ServiceOptions(
				EmergencyWatcher::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	ServiceNames::EchoNotifier => static function ( MediaWikiServices $services ): EchoNotifier {
		return new EchoNotifier(
			$services->getService( FilterLookup::SERVICE_NAME ),
			$services->getService( ConsequencesRegistry::SERVICE_NAME ),
			ExtensionRegistry::getInstance()->isLoaded( 'Echo' )
		);
	},
	ServiceNames::FilterValidator => static function ( MediaWikiServices $services ): FilterValidator {
		return new FilterValidator(
			$services->get( ChangeTagValidator::SERVICE_NAME ),
			$services->get( RuleCheckerFactory::SERVICE_NAME ),
			$services->get( PermManager::SERVICE_NAME ),
			new ServiceOptions(
				FilterValidator::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	ServiceNames::FilterCompare => static function ( MediaWikiServices $services ): FilterCompare {
		return new FilterCompare(
			$services->get( ConsequencesRegistry::SERVICE_NAME )
		);
	},
	ServiceNames::FilterImporter => static function ( MediaWikiServices $services ): FilterImporter {
		return new FilterImporter(
			new ServiceOptions(
				FilterImporter::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( ConsequencesRegistry::SERVICE_NAME )
		);
	},
	ServiceNames::FilterStore => static function ( MediaWikiServices $services ): FilterStore {
		return new FilterStore(
			$services->get( ConsequencesRegistry::SERVICE_NAME ),
			$services->getDBLoadBalancerFactory(),
			$services->getActorNormalization(),
			$services->get( FilterProfiler::SERVICE_NAME ),
			$services->get( FilterLookup::SERVICE_NAME ),
			$services->get( ChangeTagsManager::SERVICE_NAME ),
			$services->get( FilterValidator::SERVICE_NAME ),
			$services->get( FilterCompare::SERVICE_NAME ),
			$services->get( EmergencyCache::SERVICE_NAME )
		);
	},
	ServiceNames::ConsequencesFactory => static function ( MediaWikiServices $services ): ConsequencesFactory {
		return new ConsequencesFactory(
			new ServiceOptions(
				ConsequencesFactory::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->getBlockUserFactory(),
			$services->getUnblockUserFactory(),
			$services->getDatabaseBlockStore(),
			$services->getUserGroupManager(),
			$services->getMainObjectStash(),
			$services->get( ChangeTagger::SERVICE_NAME ),
			$services->get( BlockAutopromoteStore::SERVICE_NAME ),
			$services->get( FilterUser::SERVICE_NAME ),
			// TODO: Use a proper MessageLocalizer once available (T247127)
			RequestContext::getMain(),
			$services->getUserEditTracker(),
			$services->getUserRegistrationLookup(),
			$services->getUserIdentityUtils()
		);
	},
	ServiceNames::EditBoxBuilderFactory => static function ( MediaWikiServices $services ): EditBoxBuilderFactory {
		$config = $services->getMainConfig();
		return new EditBoxBuilderFactory(
			$services->get( PermManager::SERVICE_NAME ),
			$services->get( KeywordsManager::SERVICE_NAME ),
			$config->get( 'AbuseFilterUseCodeEditor' ) && ExtensionRegistry::getInstance()->isLoaded( 'CodeEditor' ),
			$config->get( 'AbuseFilterUseCodeMirror' ) && ExtensionRegistry::getInstance()->isLoaded( 'CodeMirror' )
		);
	},
	ServiceNames::ConsequencesLookup => static function ( MediaWikiServices $services ): ConsequencesLookup {
		return new ConsequencesLookup(
			$services->getDBLoadBalancerFactory(),
			$services->get( CentralDBManager::SERVICE_NAME ),
			$services->get( ConsequencesRegistry::SERVICE_NAME ),
			LoggerFactory::getInstance( 'AbuseFilter' )
		);
	},
	ServiceNames::ConsequencesRegistry => static function ( MediaWikiServices $services ): ConsequencesRegistry {
		return new ConsequencesRegistry(
			$services->get( AbuseFilterHookRunner::SERVICE_NAME ),
			$services->getMainConfig()->get( 'AbuseFilterActions' )
		);
	},
	ServiceNames::AbuseLoggerFactory => static function ( MediaWikiServices $services ): AbuseLoggerFactory {
		return new AbuseLoggerFactory(
			$services->get( CentralDBManager::SERVICE_NAME ),
			$services->get( FilterLookup::SERVICE_NAME ),
			$services->get( VariablesBlobStore::SERVICE_NAME ),
			$services->get( VariablesManager::SERVICE_NAME ),
			$services->get( EditRevUpdater::SERVICE_NAME ),
			$services->get( PermManager::SERVICE_NAME ),
			$services->get( RuleCheckerFactory::SERVICE_NAME ),
			$services->getDBLoadBalancerFactory(),
			$services->getActorStore(),
			$services->getTitleFactory(),
			new ServiceOptions(
				AbuseLoggerFactory::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			WikiMap::getCurrentWikiDbDomain()->getId(),
			RequestContext::getMain()->getRequest()->getIP(),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->get( AbuseFilterHookRunner::SERVICE_NAME )
		);
	},
	ServiceNames::UpdateHitCountWatcher => static function ( MediaWikiServices $services ): UpdateHitCountWatcher {
		return new UpdateHitCountWatcher(
			$services->getDBLoadBalancerFactory(),
			$services->get( CentralDBManager::SERVICE_NAME )
		);
	},
	ServiceNames::VariablesBlobStore => static function ( MediaWikiServices $services ): VariablesBlobStore {
		return new VariablesBlobStore(
			$services->get( VariablesManager::SERVICE_NAME ),
			$services->get( PermManager::SERVICE_NAME ),
			$services->getBlobStoreFactory(),
			$services->getBlobStore(),
			$services->getMainConfig()->get( 'AbuseFilterCentralDB' )
		);
	},
	ServiceNames::ConsequencesExecutorFactory => static function ( MediaWikiServices $services ): ConsExecutorFactory {
		return new ConsExecutorFactory(
			$services->get( ConsequencesLookup::SERVICE_NAME ),
			$services->get( ConsequencesFactory::SERVICE_NAME ),
			$services->get( ConsequencesRegistry::SERVICE_NAME ),
			$services->get( FilterLookup::SERVICE_NAME ),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->getUserIdentityUtils(),
			new ServiceOptions(
				ConsequencesExecutor::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	ServiceNames::FilterRunnerFactory => static function ( MediaWikiServices $services ): FilterRunnerFactory {
		return new FilterRunnerFactory(
			$services->get( AbuseFilterHookRunner::SERVICE_NAME ),
			$services->get( FilterProfiler::SERVICE_NAME ),
			$services->get( ChangeTagger::SERVICE_NAME ),
			$services->get( FilterLookup::SERVICE_NAME ),
			$services->get( RuleCheckerFactory::SERVICE_NAME ),
			$services->get( ConsExecutorFactory::SERVICE_NAME ),
			$services->get( AbuseLoggerFactory::SERVICE_NAME ),
			$services->get( VariablesManager::SERVICE_NAME ),
			$services->get( EmergencyCache::SERVICE_NAME ),
			$services->get( UpdateHitCountWatcher::SERVICE_NAME ),
			$services->get( EmergencyWatcher::SERVICE_NAME ),
			$services->getObjectCacheFactory()->getLocalClusterInstance(),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			LoggerFactory::getInstance( 'StashEdit' ),
			$services->getStatsdDataFactory(),
			new ServiceOptions(
				FilterRunner::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},
	ServiceNames::VariablesFormatter => static function ( MediaWikiServices $services ): VariablesFormatter {
		return new VariablesFormatter(
			$services->get( KeywordsManager::SERVICE_NAME ),
			$services->get( VariablesManager::SERVICE_NAME ),
			// TODO: Use a proper MessageLocalizer once available (T247127)
			RequestContext::getMain()
		);
	},
	ServiceNames::SpecsFormatter => static function ( MediaWikiServices $services ): SpecsFormatter {
		return new SpecsFormatter(
			// TODO: Use a proper MessageLocalizer once available (T247127)
			RequestContext::getMain()
		);
	},
	ServiceNames::LazyVariableComputer => static function ( MediaWikiServices $services ): LazyVariableComputer {
		return new LazyVariableComputer(
			$services->get( TextExtractor::SERVICE_NAME ),
			$services->get( AbuseFilterHookRunner::SERVICE_NAME ),
			LoggerFactory::getInstance( 'AbuseFilter' ),
			$services->getDBLoadBalancerFactory(),
			$services->getMainWANObjectCache(),
			$services->getRevisionLookup(),
			$services->getRevisionStore(),
			$services->getContentLanguage(),
			$services->getParserFactory(),
			$services->getUserEditTracker(),
			$services->getUserGroupManager(),
			$services->getPermissionManager(),
			$services->getRestrictionStore(),
			$services->getUserIdentityUtils(),
			$services->getUserNameUtils(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	ServiceNames::TextExtractor => static function ( MediaWikiServices $services ): TextExtractor {
		return new TextExtractor( $services->get( AbuseFilterHookRunner::SERVICE_NAME ) );
	},
	ServiceNames::VariablesManager => static function ( MediaWikiServices $services ): VariablesManager {
		return new VariablesManager(
			$services->get( KeywordsManager::SERVICE_NAME ),
			$services->get( LazyVariableComputer::SERVICE_NAME )
		);
	},
	ServiceNames::VariableGeneratorFactory => static function (
		MediaWikiServices $services
	): VariableGeneratorFactory {
		return new VariableGeneratorFactory(
			$services->get( AbuseFilterHookRunner::SERVICE_NAME ),
			$services->get( TextExtractor::SERVICE_NAME ),
			$services->getMimeAnalyzer(),
			$services->getRepoGroup(),
			$services->getWikiPageFactory(),
			$services->getUserFactory()
		);
	},
	ServiceNames::EditRevUpdater => static function ( MediaWikiServices $services ): EditRevUpdater {
		return new EditRevUpdater(
			$services->get( CentralDBManager::SERVICE_NAME ),
			$services->getRevisionLookup(),
			$services->getDBLoadBalancerFactory(),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	ServiceNames::BlockedDomainStorage => static function (
		MediaWikiServices $services
	): IBlockedDomainStorage {
		if ( $services->getExtensionRegistry()->isLoaded( 'CommunityConfiguration' ) ) {
			$provider = CommunityConfigurationServices::wrap( $services )
				->getConfigurationProviderFactory()
				->newProvider( BlockedDomainConfigProvider::PROVIDER_ID );
			if ( !$provider instanceof BlockedDomainConfigProvider ) {
				throw new LogicException(
					BlockedDomainConfigProvider::PROVIDER_ID . ' is expected to be ' .
					'an instance of ' . BlockedDomainConfigProvider::class
				);
			}
			return $provider;
		} else {
			return new CustomBlockedDomainStorage(
				$services->getLocalServerObjectCache(),
				$services->getRevisionLookup(),
				$services->getWikiPageFactory(),
				$services->get( BlockedDomainValidator::SERVICE_NAME )
			);
		}
	},
	ServiceNames::BlockedDomainValidator => static function (
		MediaWikiServices $services
	): BlockedDomainValidator {
		return new BlockedDomainValidator(
			$services->getUrlUtils()
		);
	},
	ServiceNames::BlockedDomainFilter => static function (
		MediaWikiServices $services
	): IBlockedDomainFilter {
		if (
			$services->getMainConfig()->get( 'AbuseFilterEnableBlockedExternalDomain' )
		) {
			return new BlockedDomainFilter(
				$services->get( VariablesManager::SERVICE_NAME ),
				$services->get( IBlockedDomainStorage::SERVICE_NAME )
			);
		} else {
			return new NoopBlockedDomainFilter();
		}
	},
	ServiceNames::ProtectedVariablesLookup => static function ( MediaWikiServices $services ) {
		return new AbuseFilterProtectedVariablesLookup(
			new ServiceOptions(
				AbuseFilterProtectedVariablesLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( AbuseFilterHookRunner::SERVICE_NAME )
		);
	},
	ServiceNames::LogDetailsLookup => static function ( MediaWikiServices $services ) {
		return new AbuseFilterLogDetailsLookup(
			$services->getConnectionProvider(),
			$services->get( PermManager::SERVICE_NAME ),
			$services->get( FilterLookup::SERVICE_NAME )
		);
	},
	ServiceNames::AbuseLogConditionFactory => static function (
		MediaWikiServices $services
	): AbuseLogConditionFactory {
		return new AbuseLogConditionFactory(
			$services->getConnectionProvider(),
			$services->getTempUserConfig()
		);
	},
	ServiceNames::TemporaryAccountIPsViewerSpecification => static function (
		MediaWikiServices $services
	): TemporaryAccountIPsViewerSpecification {
		return new TemporaryAccountIPsViewerSpecification(
			$services->getTempUserConfig(),
			$services->hasService( 'CheckUserPermissionManager' ) ?
				$services->get( 'CheckUserPermissionManager' ) :
				null
		);
	},
	// b/c for extensions
	'AbuseFilterRunnerFactory' => static function ( MediaWikiServices $services ): FilterRunnerFactory {
		return $services->get( FilterRunnerFactory::SERVICE_NAME );
	},
];

// @codeCoverageIgnoreEnd
