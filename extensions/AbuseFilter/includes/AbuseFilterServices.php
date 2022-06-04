<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagValidator;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutorFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesLookup;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Extension\AbuseFilter\Watcher\EmergencyWatcher;
use MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher;
use MediaWiki\MediaWikiServices;

class AbuseFilterServices {

	/**
	 * @return KeywordsManager
	 */
	public static function getKeywordsManager(): KeywordsManager {
		return MediaWikiServices::getInstance()->getService( KeywordsManager::SERVICE_NAME );
	}

	/**
	 * @return FilterProfiler
	 */
	public static function getFilterProfiler(): FilterProfiler {
		return MediaWikiServices::getInstance()->getService( FilterProfiler::SERVICE_NAME );
	}

	/**
	 * @return AbuseFilterPermissionManager
	 */
	public static function getPermissionManager(): AbuseFilterPermissionManager {
		return MediaWikiServices::getInstance()->getService( AbuseFilterPermissionManager::SERVICE_NAME );
	}

	/**
	 * @return ChangeTagger
	 */
	public static function getChangeTagger(): ChangeTagger {
		return MediaWikiServices::getInstance()->getService( ChangeTagger::SERVICE_NAME );
	}

	/**
	 * @return ChangeTagsManager
	 */
	public static function getChangeTagsManager(): ChangeTagsManager {
		return MediaWikiServices::getInstance()->getService( ChangeTagsManager::SERVICE_NAME );
	}

	/**
	 * @return ChangeTagValidator
	 */
	public static function getChangeTagValidator(): ChangeTagValidator {
		return MediaWikiServices::getInstance()->getService( ChangeTagValidator::SERVICE_NAME );
	}

	/**
	 * @return BlockAutopromoteStore
	 */
	public static function getBlockAutopromoteStore(): BlockAutopromoteStore {
		return MediaWikiServices::getInstance()->getService( BlockAutopromoteStore::SERVICE_NAME );
	}

	/**
	 * @return FilterUser
	 */
	public static function getFilterUser(): FilterUser {
		return MediaWikiServices::getInstance()->getService( FilterUser::SERVICE_NAME );
	}

	/**
	 * @return CentralDBManager
	 */
	public static function getCentralDBManager(): CentralDBManager {
		return MediaWikiServices::getInstance()->getService( CentralDBManager::SERVICE_NAME );
	}

	/**
	 * @return RuleCheckerFactory
	 */
	public static function getRuleCheckerFactory(): RuleCheckerFactory {
		return MediaWikiServices::getInstance()->getService( RuleCheckerFactory::SERVICE_NAME );
	}

	/**
	 * @return FilterLookup
	 */
	public static function getFilterLookup(): FilterLookup {
		return MediaWikiServices::getInstance()->getService( FilterLookup::SERVICE_NAME );
	}

	/**
	 * @return EmergencyCache
	 */
	public static function getEmergencyCache(): EmergencyCache {
		return MediaWikiServices::getInstance()->getService( EmergencyCache::SERVICE_NAME );
	}

	/**
	 * @return EmergencyWatcher
	 */
	public static function getEmergencyWatcher(): EmergencyWatcher {
		return MediaWikiServices::getInstance()->getService( EmergencyWatcher::SERVICE_NAME );
	}

	/**
	 * @return EchoNotifier
	 */
	public static function getEchoNotifier(): EchoNotifier {
		return MediaWikiServices::getInstance()->getService( EchoNotifier::SERVICE_NAME );
	}

	/**
	 * @return FilterValidator
	 */
	public static function getFilterValidator(): FilterValidator {
		return MediaWikiServices::getInstance()->getService( FilterValidator::SERVICE_NAME );
	}

	/**
	 * @return FilterCompare
	 */
	public static function getFilterCompare(): FilterCompare {
		return MediaWikiServices::getInstance()->getService( FilterCompare::SERVICE_NAME );
	}

	/**
	 * @return FilterImporter
	 */
	public static function getFilterImporter(): FilterImporter {
		return MediaWikiServices::getInstance()->getService( FilterImporter::SERVICE_NAME );
	}

	/**
	 * @return FilterStore
	 */
	public static function getFilterStore(): FilterStore {
		return MediaWikiServices::getInstance()->getService( FilterStore::SERVICE_NAME );
	}

	/**
	 * @return ConsequencesFactory
	 */
	public static function getConsequencesFactory(): ConsequencesFactory {
		return MediaWikiServices::getInstance()->getService( ConsequencesFactory::SERVICE_NAME );
	}

	/**
	 * @return EditBoxBuilderFactory
	 */
	public static function getEditBoxBuilderFactory(): EditBoxBuilderFactory {
		return MediaWikiServices::getInstance()->getService( EditBoxBuilderFactory::SERVICE_NAME );
	}

	/**
	 * @return ConsequencesLookup
	 */
	public static function getConsequencesLookup(): ConsequencesLookup {
		return MediaWikiServices::getInstance()->getService( ConsequencesLookup::SERVICE_NAME );
	}

	/**
	 * @return ConsequencesRegistry
	 */
	public static function getConsequencesRegistry(): ConsequencesRegistry {
		return MediaWikiServices::getInstance()->getService( ConsequencesRegistry::SERVICE_NAME );
	}

	/**
	 * @return AbuseLoggerFactory
	 */
	public static function getAbuseLoggerFactory(): AbuseLoggerFactory {
		return MediaWikiServices::getInstance()->getService( AbuseLoggerFactory::SERVICE_NAME );
	}

	/**
	 * @return UpdateHitCountWatcher
	 */
	public static function getUpdateHitCountWatcher(): UpdateHitCountWatcher {
		return MediaWikiServices::getInstance()->getService( UpdateHitCountWatcher::SERVICE_NAME );
	}

	/**
	 * @return VariablesBlobStore
	 */
	public static function getVariablesBlobStore(): VariablesBlobStore {
		return MediaWikiServices::getInstance()->getService( VariablesBlobStore::SERVICE_NAME );
	}

	/**
	 * @return ConsequencesExecutorFactory
	 */
	public static function getConsequencesExecutorFactory(): ConsequencesExecutorFactory {
		return MediaWikiServices::getInstance()->getService( ConsequencesExecutorFactory::SERVICE_NAME );
	}

	/**
	 * @return FilterRunnerFactory
	 */
	public static function getFilterRunnerFactory(): FilterRunnerFactory {
		return MediaWikiServices::getInstance()->getService( FilterRunnerFactory::SERVICE_NAME );
	}

	/**
	 * @return SpecsFormatter
	 */
	public static function getSpecsFormatter(): SpecsFormatter {
		return MediaWikiServices::getInstance()->getService( SpecsFormatter::SERVICE_NAME );
	}

	/**
	 * @return VariablesFormatter
	 */
	public static function getVariablesFormatter(): VariablesFormatter {
		return MediaWikiServices::getInstance()->getService( VariablesFormatter::SERVICE_NAME );
	}

	/**
	 * @return LazyVariableComputer
	 */
	public static function getLazyVariableComputer(): LazyVariableComputer {
		return MediaWikiServices::getInstance()->getService( LazyVariableComputer::SERVICE_NAME );
	}

	/**
	 * @return TextExtractor
	 */
	public static function getTextExtractor(): TextExtractor {
		return MediaWikiServices::getInstance()->getService( TextExtractor::SERVICE_NAME );
	}

	/**
	 * @return VariablesManager
	 */
	public static function getVariablesManager(): VariablesManager {
		return MediaWikiServices::getInstance()->getService( VariablesManager::SERVICE_NAME );
	}

	/**
	 * @return VariableGeneratorFactory
	 */
	public static function getVariableGeneratorFactory(): VariableGeneratorFactory {
		return MediaWikiServices::getInstance()->getService( VariableGeneratorFactory::SERVICE_NAME );
	}

	/**
	 * @return EditRevUpdater
	 */
	public static function getEditRevUpdater(): EditRevUpdater {
		return MediaWikiServices::getInstance()->getService( EditRevUpdater::SERVICE_NAME );
	}
}
