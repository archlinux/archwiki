<?php

use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Logger\LegacySpi;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\CookieSessionProvider;

/**
 * Common code for test environment initialisation and teardown
 */
class TestSetup {
	/** @var array */
	public static $bootstrapGlobals;

	/**
	 * For use in MediaWikiUnitTestCase.
	 *
	 * This should be called before default settings are applied or Setup.php loads.
	 */
	public static function snapshotGlobals() {
		self::$bootstrapGlobals = [];
		foreach ( $GLOBALS as $key => $_ ) {
			// Support: HHVM (avoid self-ref)
			if ( $key !== 'GLOBALS' ) {
				self::$bootstrapGlobals[ $key ] =& $GLOBALS[$key];
			}
		}
	}

	/**
	 * Overrides config settings for testing.
	 * This should be called after loading local settings, typically from the finalSetup() method
	 * of a Maintenance subclass which then gets called via MW_SETUP_CALLBACK in Setup.php.
	 */
	public static function applyInitialConfig() {
		global $wgMainCacheType, $wgMessageCacheType, $wgParserCacheType, $wgSessionCacheType;
		global $wgMainStash, $wgChronologyProtectorStash;
		global $wgObjectCaches;
		global $wgLanguageConverterCacheType, $wgUseDatabaseMessages;
		global $wgLocaltimezone, $wgLocalTZoffset, $wgLocalisationCacheConf;
		global $wgSearchType;
		global $wgDevelopmentWarnings;
		global $wgSessionProviders, $wgSessionPbkdf2Iterations;
		global $wgJobTypeConf;
		global $wgMWLoggerDefaultSpi;
		global $wgAuthManagerConfig;
		global $wgShowExceptionDetails, $wgShowHostnames;

		$wgShowExceptionDetails = true;
		$wgShowHostnames = true;

		// wfWarn should cause tests to fail
		$wgDevelopmentWarnings = true;

		// Make sure all caches and stashes are either disabled or use
		// in-process cache only to prevent tests from using any preconfigured
		// cache meant for the local wiki from outside the test run.
		// See also MediaWikiIntegrationTestCase::run() which mocks CACHE_DB and APC.

		// Disabled per default in MainConfigSchema, override local settings
		$wgMainCacheType = CACHE_NONE;
		// Uses CACHE_ANYTHING per default in MainConfigSchema, use hash instead of db
		$wgMessageCacheType =
		$wgParserCacheType =
		$wgSessionCacheType =
		$wgLanguageConverterCacheType = 'hash';
		// Uses db-replicated per default in MainConfigSchema
		$wgMainStash = 'hash';
		$wgChronologyProtectorStash = 'hash';
		// Use hash instead of db
		$wgObjectCaches['db-replicated'] = $wgObjectCaches['hash'];
		// Use memory job queue
		$wgJobTypeConf = [
			'default' => [ 'class' => JobQueueMemory::class, 'order' => 'fifo' ],
		];
		// Always default to LegacySpi and LegacyLogger during test
		// See also MediaWikiIntegrationTestCase::setNullLogger().
		// Note that MediaWikiLoggerPHPUnitTestListener may wrap this in
		// a MediaWiki\Logger\LogCapturingSpi at run-time.
		$wgMWLoggerDefaultSpi = [
			'class' => LegacySpi::class,
		];

		$wgUseDatabaseMessages = false; # Set for future resets

		// Assume UTC for testing purposes
		$wgLocaltimezone = 'UTC';
		$wgLocalTZoffset = 0;

		$wgLocalisationCacheConf['class'] = TestLocalisationCache::class;
		$wgLocalisationCacheConf['storeClass'] = LCStoreNull::class;

		// Do not bother updating search tables
		$wgSearchType = SearchEngineDummy::class;

		// Generic MediaWiki\Session\SessionManager configuration for tests
		// We use CookieSessionProvider because things might be expecting
		// cookies to show up in a MediaWiki\Request\FauxRequest somewhere.
		$wgSessionProviders = [
			[
				'class' => CookieSessionProvider::class,
				'args' => [ [
					'priority' => 30,
				] ],
			],
		];

		// Single-iteration PBKDF2 session secret derivation, for speed.
		$wgSessionPbkdf2Iterations = 1;

		// Generic AuthManager configuration for testing
		$wgAuthManagerConfig = [
			'preauth' => [],
			'primaryauth' => [
				[
					'class' => TemporaryPasswordPrimaryAuthenticationProvider::class,
					'services' => [
						'DBLoadBalancerFactory',
						'UserOptionsLookup',
					],
					'args' => [ [
						'authoritative' => false,
					] ],
				],
				[
					'class' => LocalPasswordPrimaryAuthenticationProvider::class,
					'services' => [
						'DBLoadBalancerFactory',
					],
					'args' => [ [
						'authoritative' => true,
					] ],
				],
			],
			'secondaryauth' => [],
		];

		// xdebug's default of 100 is too low for MediaWiki
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
		ini_set( 'xdebug.max_nesting_level', 1000 );

		// Make sure that serialize_precision is set to its default value
		// so floating-point numbers within serialized or JSON-encoded data
		// will match the expected string representations (T116683).
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
		ini_set( 'serialize_precision', -1 );
	}

	/**
	 * @internal Should only be used in bootstrap.php and boostrap.maintenance.php
	 *
	 * PHPUnit includes the bootstrap file inside a method body, while most MediaWiki startup files
	 * assume to be included in the global scope.
	 * This utility provides a way to include these files: it makes all globals available in the
	 * inclusion scope before including the file, then exports all new or changed globals.
	 *
	 * @param string $fileName the file to include
	 */
	public static function requireOnceInGlobalScope( string $fileName ): void {
		$ignore = [
			'fileName' => true,
			'originalGlobalsMap' => true,
			'key' => true,
			'_' => true,
			'ignore' => true,
			'wgAutoloadClasses' => true,
			'wgWikimediaJenkinsCI' => true,
		];

		// Import $GLOBALS into local scope for the file.
		// Modifications to these from the required file automatically affect the real global.
		foreach ( $GLOBALS as $key => $_ ) {
			$ignore[$key] = true;
			// phpcs:ignore MediaWiki.VariableAnalysis.UnusedGlobalVariables,MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
			global $$key;
		}

		// phpcs:disable MediaWiki.VariableAnalysis.UnusedGlobalVariables
		// Setup.php creates this variable, but we cannot wait for the below code to make it global,
		// because Setup.php (and MW_SETUP_CALLBACK -> TestsAutoLoader.php) needs this to be a
		// global during its execution (not just after).
		global $wgAutoloadClasses;
		// $wgWikimediaJenkinsCI is not a config variable and is therefore not made explicitly global
		// in Setup.php when checking wgScopeTest. Do that here instead, as the variable might be
		// read in an extension before the code below is executed (T341731).
		global $wgWikimediaJenkinsCI;
		// phpcs:enable MediaWiki.VariableAnalysis.UnusedGlobalVariables

		require_once $fileName;

		// Create any new variables as actual globals.
		foreach ( get_defined_vars() as $varName => $value ) {
			// Skip our own internal variables, and variables that were already global.
			if ( array_key_exists( $varName, $ignore ) ) {
				continue;
			}
			$GLOBALS[$varName] = $value;
		}
	}

	/**
	 * Verifies that the composer.lock file is up-to-date, unless this check is disabled.
	 */
	public static function maybeCheckComposerLockUpToDate(): void {
		if ( !getenv( 'MW_SKIP_EXTERNAL_DEPENDENCIES' ) ) {
			$composerLockUpToDate = new CheckComposerLockUpToDate();
			$composerLockUpToDate->loadParamsAndArgs( 'phpunit', [ 'quiet' => true ] );
			$composerLockUpToDate->execute();
		}
	}

	public static function loadSettingsFiles(): void {
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.define
		define( 'MW_SETUP_CALLBACK', [ self::class, 'setupCallback' ] );
		self::requireOnceInGlobalScope( MW_INSTALL_PATH . "/includes/Setup.php" );
	}

	/**
	 * @internal Should only be used in self::loadSettingsFiles
	 */
	public static function setupCallback() {
		global $wgDBadminuser, $wgDBadminpassword;
		global $wgDBuser, $wgDBpassword, $wgDBservers, $wgLBFactoryConf;

		// These are already set in the PHPUnit config, but set them again in case they were changed in a settings file
		ini_set( 'memory_limit', '-1' );
		ini_set( 'max_execution_time', '0' );

		if ( isset( $wgDBadminuser ) ) {
			$wgDBuser = $wgDBadminuser;
			$wgDBpassword = $wgDBadminpassword;

			if ( $wgDBservers ) {
				/**
				 * @var array $wgDBservers
				 */
				foreach ( $wgDBservers as $i => $server ) {
					$wgDBservers[$i]['user'] = $wgDBuser;
					$wgDBservers[$i]['password'] = $wgDBpassword;
				}
			}
			if ( isset( $wgLBFactoryConf['serverTemplate'] ) ) {
				$wgLBFactoryConf['serverTemplate']['user'] = $wgDBuser;
				$wgLBFactoryConf['serverTemplate']['password'] = $wgDBpassword;
			}
			$service = MediaWikiServices::getInstance()->peekService( 'DBLoadBalancerFactory' );
			if ( $service ) {
				$service->destroy();
			}
		}

		self::requireOnceInGlobalScope( __DIR__ . '/TestsAutoLoader.php' );

		self::applyInitialConfig();

		ExtensionRegistry::getInstance()->setLoadTestClassesAndNamespaces( true );
	}
}
