<?php

namespace MediaWiki\Extension\AbuseFilter;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

/**
 * This class holds constants for service names, so ServiceWiring.php doesn't have to autoload
 * service classes that aren't actually used during the request to access the constants (T426470).
 */
class ServiceNames {

	public const string AbuseFilterHookRunner = 'AbuseFilterHookRunner';
	public const string AbuseLogConditionFactory = 'AbuseLogConditionFactory';
	public const string AbuseLoggerFactory = 'AbuseFilterAbuseLoggerFactory';
	public const string BlockAutopromoteStore = 'AbuseFilterBlockAutopromoteStore';
	public const string BlockedDomainFilter = 'AbuseFilterBlockedDomainFilter';
	public const string BlockedDomainStorage = 'AbuseFilterBlockedDomainStorage';
	public const string BlockedDomainValidator = 'AbuseFilterBlockedDomainValidator';
	public const string CentralDBManager = 'AbuseFilterCentralDBManager';
	public const string ChangeTagger = 'AbuseFilterChangeTagger';
	public const string ChangeTagsManager = 'AbuseFilterChangeTagsManager';
	public const string ChangeTagValidator = 'AbuseFilterChangeTagValidator';
	public const string ConsequencesExecutorFactory = 'AbuseFilterConsequencesExecutorFactory';
	public const string ConsequencesFactory = 'AbuseFilterConsequencesFactory';
	public const string ConsequencesLookup = 'AbuseFilterConsequencesLookup';
	public const string ConsequencesRegistry = 'AbuseFilterConsequencesRegistry';
	public const string EchoNotifier = 'AbuseFilterEchoNotifier';
	public const string EditBoxBuilderFactory = 'AbuseFilterEditBoxBuilderFactory';
	public const string EditRevUpdater = 'AbuseFilterEditRevUpdater';
	public const string EmergencyCache = 'AbuseFilterEmergencyCache';
	public const string EmergencyWatcher = 'AbuseFilterEmergencyWatcher';
	public const string FilterCompare = 'AbuseFilterFilterCompare';
	public const string FilterImporter = 'AbuseFilterFilterImporter';
	public const string FilterLookup = 'AbuseFilterFilterLookup';
	public const string FilterProfiler = 'AbuseFilterFilterProfiler';
	public const string FilterRunnerFactory = 'AbuseFilterFilterRunnerFactory';
	public const string FilterStore = 'AbuseFilterFilterStore';
	public const string FilterUser = 'AbuseFilterFilterUser';
	public const string FilterValidator = 'AbuseFilterFilterValidator';
	public const string KeywordsManager = 'AbuseFilterKeywordsManager';
	public const string LazyVariableComputer = 'AbuseFilterLazyVariableComputer';
	public const string LogDetailsLookup = 'AbuseFilterLogDetailsLookup';
	public const string PermManager = 'AbuseFilterPermissionManager';
	public const string ProtectedVariablesLookup = 'AbuseFilterProtectedVariablesLookup';
	public const string RuleCheckerFactory = 'AbuseFilterRuleCheckerFactory';
	public const string SpecsFormatter = 'AbuseFilterSpecsFormatter';
	public const string TemporaryAccountIPsViewerSpecification = 'TemporaryAccountIPsViewerSpecification';
	public const string TextExtractor = 'AbuseFilterTextExtractor';
	public const string UpdateHitCountWatcher = 'AbuseFilterUpdateHitCountWatcher';
	public const string VariableGeneratorFactory = 'AbuseFilterVariableGeneratorFactory';
	public const string VariablesBlobStore = 'AbuseFilterVariablesBlobStore';
	public const string VariablesFormatter = 'AbuseFilterVariablesFormatter';
	public const string VariablesManager = 'AbuseFilterVariablesManager';

}
