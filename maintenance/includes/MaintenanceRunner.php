<?php

namespace MediaWiki\Maintenance;

use Exception;
use LCStoreNull;
use LogicException;
use Maintenance;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Settings\SettingsBuilder;
use Profiler;
use ReflectionClass;
use Throwable;

/**
 * A runner for maintenance scripts.
 *
 * @since 1.39
 * @unstable
 */
class MaintenanceRunner {

	/**
	 * Identifies the script to execute. This may be a class name, the relative or absolute
	 * path of a script file, a plain name with or without an extension prefix, etc.
	 *
	 * @var ?string
	 */
	private $script = null;

	/**
	 * The class name of the script to execute.
	 *
	 * @var ?string
	 */
	private $scriptClass = null;

	/** @var string[]|null */
	private $scriptArgv = null;

	/** @var Maintenance|null */
	private $scriptObject = null;

	/** @var MaintenanceParameters */
	private $parameters;

	/** @var bool */
	private $runFromWrapper = false;

	/** @var bool */
	private bool $withoutLocalSettings = false;

	/**
	 * Default constructor. Children should call this *first* if implementing
	 * their own constructors
	 *
	 * @stable to call
	 */
	public function __construct() {
		$this->parameters = new MaintenanceParameters();
		$this->addDefaultParams();
	}

	/**
	 * Add the default parameters to the scripts
	 */
	protected function addDefaultParams() {
		// Generic (non-script-dependent) options:

		$this->parameters->addOption( 'conf', 'Location of LocalSettings.php, if not default', false, true );
		$this->parameters->addOption( 'wiki', 'For specifying the wiki ID', false, true );
		$this->parameters->addOption( 'globals', 'Output globals at the end of processing for debugging' );
		$this->parameters->addOption(
			'memory-limit',
			'Set a specific memory limit for the script, '
			. '"max" for no limit or "default" to avoid changing it',
			false,
			true
		);
		$this->parameters->addOption( 'server', "The protocol and server name to use in URLs, e.g. " .
			"https://en.wikipedia.org. This is sometimes necessary because " .
			"server name detection may fail in command line scripts.", false, true );
		$this->parameters->addOption( 'profiler', 'Profiler output format (usually "text")', false, true );

		// Save generic options to display them separately in help
		$generic = $this->parameters->getOptionNames();
		$this->parameters->assignGroup( Maintenance::GENERIC_MAINTENANCE_PARAMETERS, $generic );
	}

	/**
	 * @param int $code
	 *
	 * @return never
	 */
	private function showHelpAndExit( $code = 0 ) {
		foreach ( $this->parameters->getErrors() as $error ) {
			$this->error( "$error\n" );
			$code = 1;
		}

		$this->parameters->setDescription( 'Runner for maintenance scripts' );

		$help = $this->parameters->getHelp();
		echo $help;
		exit( $code );
	}

	/**
	 * Initialize the runner from the given command line arguments
	 * as passed to a wrapper script.
	 *
	 * @note Called before Setup.php
	 *
	 * @param string[] $argv The arguments passed from the command line,
	 *        including the wrapper script at index 0, and usually
	 *        the script to run at index 1.
	 */
	public function initFromWrapper( array $argv ) {
		$script = null;

		$this->parameters->setName( $argv[0] );
		$this->parameters->setAllowUnregisteredOptions( true );
		$this->parameters->addArg(
			'script',
			'The name of the maintenance script to run. ' .
				'Can be given as a class name or file path. The `.php` suffix is optional. ' .
				'Paths starting with `./` or `../` are interpreted to be relative to the current working directory. ' .
				'Other relative paths are interpreted relative to the maintenance script directory. ' .
				'Dots (.) are supported as namespace separators in class names. ' .
				'An extension name may be provided as a prefix, followed by a colon, e.g. "MyExtension:...", ' .
				'to indicate that the path or class name should be interpreted relative to the extension.'
		);

		$this->runFromWrapper = true;
		$this->parameters->loadWithArgv( $argv, 1 );

		// script params
		$argv = array_slice( $argv, 2 );

		if ( $this->parameters->validate() ) {
			$script = $this->parameters->getArg( 0 );

			// Special handling for the 'help' command
			if ( $script === 'help' ) {
				if ( $this->parameters->hasArg( 1 ) ) {
					$script = $this->parameters->getArg( 1 );

					// turn <help> <command> into <command> --help
					$this->parameters->loadWithArgv( [ $script ] );
					$argv = [ '--help' ];
				} else {
					// same as no command
					$script = null;
				}
			}
		}

		if ( $script ) {
			// Strip another argument from $argv!
			$this->initInternal( $script, $argv );
		} else {
			$this->showHelpAndExit();
		}
	}

	/**
	 * Initialize the runner for the given class.
	 * This is used when running scripts directly, without a wrapper.
	 *
	 * @note Called before Setup.php
	 *
	 * @param string $scriptClass The script class to run
	 * @param string[] $argv The arguments to passed to the script, including
	 *        the script itself at index 0.
	 */
	public function initForClass( string $scriptClass, $argv ) {
		$this->runFromWrapper = false;
		$this->script = $scriptClass;
		$this->scriptClass = $scriptClass;
		$this->parameters->setName( $argv[0] );
		$this->parameters->loadWithArgv( $argv );
		$this->initInternal( $scriptClass, array_slice( $argv, 1 ) );
	}

	/**
	 * Initialize the runner.
	 *
	 * @note Called before Setup.php
	 *
	 * @param string $script The script to run
	 * @param string[] $scriptArgv The arguments to pass to the maintenance script,
	 *        not including the script itself.
	 */
	private function initInternal( string $script, array $scriptArgv ) {
		global $wgCommandLineMode;

		$this->script = $script;
		$this->scriptArgv = $scriptArgv;

		// Send PHP warnings and errors to stderr instead of stdout.
		// This aids in diagnosing problems, while keeping messages
		// out of redirected output.
		if ( ini_get( 'display_errors' ) ) {
			ini_set( 'display_errors', 'stderr' );
		}

		// make sure we clean up after ourselves.
		register_shutdown_function( [ $this, 'cleanup' ] );

		$wgCommandLineMode = true;

		// Turn off output buffering if it's on
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	private static function isAbsolutePath( $path ) {
		if ( str_starts_with( $path, '/' ) ) {
			return true;
		}

		if ( wfIsWindows() ) {
			if ( str_starts_with( $path, '\\' ) ) {
				return true;
			}
			if ( preg_match( '!^[a-zA-Z]:[/\\\\]!', $path ) ) {
				return true;
			}
		}

		return false;
	}

	protected function getExtensionInfo( string $extName ): ?array {
		// NOTE: Don't go by the extension registry, since some extensions
		//       register under a name different from what is used in wfLoadExtension.
		//       E.g. AbuseFilter is registered as "Abuse Filter" with a space.

		$config = SettingsBuilder::getInstance()->getConfig();
		$extDir = $config->get( MainConfigNames::ExtensionDirectory );
		$skinDir = $config->get( MainConfigNames::StyleDirectory );

		$extension = [];
		if ( file_exists( "$extDir/$extName/extension.json" ) ) {
			$extension['path'] = "$extDir/$extName/extension.json";
			$extension['namespace'] = "MediaWiki\\Extension\\$extName";
		} elseif ( file_exists( "$skinDir/$extName/skin.json" ) ) {
			$extension['path'] = "$skinDir/$extName/skin.json";
			$extension['namespace'] = "MediaWiki\\Skins\\$extName";
		} else {
			return null;
		}

		return $extension;
	}

	private function loadScriptFile( string $scriptFile ): string {
		$maintClass = null;

		// It's a file, include it
		// If it returns something, it should be the name of the maintenance class.
		$scriptClass = include $scriptFile;

		// Traditional script files set the $maintClass variable
		// at the end of the file.
		// @phan-suppress-next-line PhanImpossibleCondition Phan doesn't understand includes.
		if ( $maintClass ) {
			$scriptClass = $maintClass;
		}

		if ( !is_string( $scriptClass ) ) {
			$this->error( "ERROR: The script file '{$scriptFile}' cannot be executed using MaintenanceRunner.\n" );
			$this->error( "It does not set \$maintClass and does not return a class name.\n" );
			$this->fatalError( "Try running it directly as a php script: php $scriptFile\n" );
		}

		return $scriptClass;
	}

	private function splitScript( string $script ): array {
		// Support "$ext:$script" format for extensions
		if ( preg_match( '!^(\w+):(.*)$!', $script, $m ) ) {
			return [ $m[1], $m[2] ];
		}

		return [ null, $script ];
	}

	private function expandScriptFile( string $scriptName, ?array $extension ): string {
		// Append ".php" if not present
		$scriptFile = $scriptName;
		if ( !str_ends_with( $scriptFile, '.php' ) ) {
			$scriptFile .= '.php';
		}

		// If the path is not explicitly relative (starting with "./" or "../") and not absolute,
		// then look in the maintenance dir.
		if ( !preg_match( '!^\.\.?[/\\\\]!', $scriptFile ) && !self::isAbsolutePath( $scriptFile ) ) {
			if ( $extension !== null ) {
				// Look in the extension's maintenance dir
				$scriptFile = dirname( $extension['path'] ) . "/maintenance/{$scriptFile}";
			} else {
				// It's a core script.
				$scriptFile = MW_INSTALL_PATH . "/maintenance/{$scriptFile}";
			}
		}

		return $scriptFile;
	}

	private function expandScriptClass( string $scriptName, ?array $extension ): string {
		$scriptClass = $scriptName;

		// Support "$ext:$script" format
		if ( $extension ) {
			$scriptClass = "{$extension['namespace']}\\Maintenance\\$scriptClass";
		}

		// Accept dot (.) as namespace separators as well.
		// Backslashes are just annoying on the command line.
		$scriptClass = strtr( $scriptClass, '.', '\\' );

		return $scriptClass;
	}

	/**
	 * Preload the script file, so any defines in file level code are executed.
	 * This way, scripts can control what Setup.php does.
	 *
	 * @internal
	 * @param string $script
	 */
	protected function preloadScriptFile( string $script ): void {
		if ( $this->scriptClass !== null && class_exists( $this->scriptClass ) ) {
			// We know the script class, and file-level code was executed because class_exists triggers auto-loading.
			return;
		}

		[ $extName, $scriptName ] = $this->splitScript( $script );

		if ( $extName !== null ) {
			// Preloading is not supported. findScriptClass() will try to find the script later.
			return;
		}

		$scriptFile = $this->expandScriptFile( $scriptName, null );

		$scriptClass = null;
		if ( file_exists( $scriptFile ) ) {
			$scriptClass = $this->loadScriptFile( $scriptFile );
		}

		if ( !$scriptClass ) {
			$scriptClass = $this->expandScriptClass( $scriptName, null );
		}

		// NOTE: class_exists will trigger auto-loading, so file-level code in the script file will run.
		if ( class_exists( $scriptClass ) ) {
			// Set the script class name we found, so we don't try to load the file again!
			$this->scriptClass = $scriptClass;
		}

		// Preloading failed. Let findScriptClass() try to find the script later.
	}

	protected function getScriptClass(): string {
		if ( $this->scriptClass === null ) {
			if ( $this->runFromWrapper ) {
				$this->scriptClass = $this->findScriptClass( $this->script );
			} else {
				$this->scriptClass = $this->script;
			}
		}

		if ( !class_exists( $this->scriptClass ) ) {
			$this->fatalError( "Script class {$this->scriptClass} not found.\n" );
		}

		return $this->scriptClass;
	}

	/**
	 * @internal
	 * @param string $script
	 *
	 * @return string
	 */
	protected function findScriptClass( string $script ): string {
		[ $extName, $scriptName ] = $this->splitScript( $script );

		if ( $extName !== null ) {
			$extension = $this->getExtensionInfo( $extName );

			if ( !$extension ) {
				$this->fatalError( "Extension '{$extName}' not found.\n" );
			}
		} else {
			$extension = null;
		}

		$scriptFile = $this->expandScriptFile( $scriptName, $extension );

		$scriptClass = null;
		if ( file_exists( $scriptFile ) ) {
			$scriptClass = $this->loadScriptFile( $scriptFile );
		}

		if ( !$scriptClass ) {
			$scriptClass = $this->expandScriptClass( $scriptName, $extension );
		}

		if ( !class_exists( $scriptClass ) ) {
			$this->fatalError( "Script '{$script}' not found (tried path '$scriptFile' and class '$scriptClass').\n" );
		}

		return $scriptClass;
	}

	/**
	 * MW_FINAL_SETUP_CALLBACK handler, for setting up the Maintenance object.
	 *
	 * @param SettingsBuilder $settings
	 */
	public function setup( SettingsBuilder $settings ) {
		// NOTE: this has to happen after the autoloader has been initialized.
		$scriptClass = $this->getScriptClass();

		$cls = new ReflectionClass( $scriptClass );
		if ( !$cls->isSubclassOf( Maintenance::class ) ) {
			$this->fatalError( "Class {$this->script} is not a subclass of Maintenance.\n" );
		}

		// Initialize the actual Maintenance object
		try {
			$this->scriptObject = new $scriptClass;
			$this->scriptObject->setName( $this->getName() );
		} catch ( Throwable $ex ) {
			$this->fatalError(
				"Failed to initialize Maintenance object.\n" .
				"(Did you forget to call parent::__construct() in your maintenance script?)\n" .
				"$ex\n"
			);
		}

		if ( !$this->scriptObject instanceof Maintenance ) {
			// This should never happen, we already checked if the class is a subclass of Maintenance!
			throw new LogicException( 'Incompatible script object' );
		}

		// Inject runner stuff into the script's parameter definitions.
		// This is mainly used when printing help.
		$scriptParameters = $this->scriptObject->getParameters();

		if ( $this->runFromWrapper ) {
			$scriptParameters->setUsagePrefix( 'php ' . $this->parameters->getName() );
		}

		$scriptParameters->mergeOptions( $this->parameters );
		$this->parameters = $scriptParameters;

		// Ingest argv
		$this->scriptObject->loadWithArgv( $this->scriptArgv );

		// Basic checks and such
		$this->scriptObject->setup();

		// Set the memory limit
		$this->adjustMemoryLimit();

		// Override any config settings
		$this->overrideConfig( $settings );
	}

	/**
	 * Returns the maintenance script name to show in the help message.
	 *
	 * @return string
	 */
	public function getName(): string {
		// Once one of the init methods was called, getArg( 0 ) should always
		// return something.
		return $this->parameters->getArg( 0 ) ?? 'UNKNOWN';
	}

	/**
	 * Normally we disable the memory_limit when running admin scripts.
	 * Some scripts may wish to actually set a limit, however, to avoid
	 * blowing up unexpectedly.
	 * @see Maintenance::memoryLimit()
	 * @return string
	 */
	private function memoryLimit() {
		if ( $this->parameters->hasOption( 'memory-limit' ) ) {
			$limit = $this->parameters->getOption( 'memory-limit', 'max' );
			$limit = trim( $limit, "\" '" ); // trim quotes in case someone misunderstood
			return $limit;
		}

		$limit = $this->scriptObject->memoryLimit();
		return $limit ?: 'max';
	}

	/**
	 * Adjusts PHP's memory limit to better suit our needs, if needed.
	 */
	private function adjustMemoryLimit() {
		$limit = $this->memoryLimit();
		if ( $limit == 'max' ) {
			$limit = -1; // no memory limit
		}
		if ( $limit != 'default' ) {
			ini_set( 'memory_limit', $limit );
		}
	}

	/**
	 * Define how settings are loaded (e.g. LocalSettings.php)
	 * @note Called before Setup.php
	 *
	 * @internal
	 * @return void
	 */
	public function defineSettings() {
		global $wgCommandLineMode, $IP;

		if ( $this->parameters->hasOption( 'conf' ) ) {
			// Define the constant instead of directly setting $settingsFile
			// to ensure consistency. wfDetectLocalSettingsFile() will return
			// MW_CONFIG_FILE if it is defined.
			define( 'MW_CONFIG_FILE', $this->parameters->getOption( 'conf' ) );

			if ( !is_readable( MW_CONFIG_FILE ) ) {
				$this->fatalError( "\nConfig file " . MW_CONFIG_FILE . " was not found or is not readable.\n\n" );
			}
		}
		$settingsFile = wfDetectLocalSettingsFile( $IP );

		if ( $this->parameters->hasOption( 'wiki' ) ) {
			$wikiName = $this->parameters->getOption( 'wiki' );
			$bits = explode( '-', $wikiName, 2 );
			define( 'MW_DB', $bits[0] );
			define( 'MW_PREFIX', $bits[1] ?? '' );
			define( 'MW_WIKI_NAME', $wikiName );
		} elseif ( $this->parameters->hasOption( 'server' ) ) {
			// Provide the option for site admins to detect and configure
			// multiple wikis based on server names. This offers --server
			// as alternative to --wiki.
			// See https://www.mediawiki.org/wiki/Manual:Wiki_family
			$_SERVER['SERVER_NAME'] = $this->parameters->getOption( 'server' );
		}

		// Try to load the script file before running Setup.php if possible.
		// This allows the script file to define constants that change the behavior
		// of Setup.php.
		// Note that this will only work reliably for core scripts.
		if ( $this->runFromWrapper ) {
			$this->preloadScriptFile( $this->script );
		}

		if ( !is_readable( $settingsFile ) ) {
			// NOTE: Some maintenance scripts can (and need to) run without LocalSettings.
			//       But we only know that once we have instantiated the Maintenance object.
			//       So go into no-settings mode for now, and fail later of the script doesn't support it.
			if ( !defined( 'MW_CONFIG_CALLBACK' ) ) {
				define( 'MW_CONFIG_CALLBACK', __CLASS__ . '::emulateConfig' );
			}
			$this->withoutLocalSettings = true;
		}
		$wgCommandLineMode = true;
	}

	/**
	 * @param SettingsBuilder $settings
	 *
	 * @internal Handler for MW_CONFIG_CALLBACK, used when no LocalSettings.php was found.
	 */
	public static function emulateConfig( SettingsBuilder $settings ) {
		// NOTE: The config schema is already loaded at this point, so default values are known.

		// Server must be set, but we don't care to what
		$settings->overrideConfigValue( 'Server', 'https://unknown.invalid' );

		// If InvalidateCacheOnLocalSettingsChange is enabled, filemtime( MW_CONFIG_FILE ),
		// which will produce a warning if there is no settings file.
		$settings->overrideConfigValue( 'InvalidateCacheOnLocalSettingsChange', false );
	}

	/**
	 * @param SettingsBuilder $settingsBuilder
	 *
	 * @return void
	 */
	private function overrideConfig( SettingsBuilder $settingsBuilder ) {
		$config = $settingsBuilder->getConfig();

		if ( $this->scriptObject->getDbType() === Maintenance::DB_NONE ) {
			$cacheConf = $config->get( MainConfigNames::LocalisationCacheConf );
			if ( $cacheConf['storeClass'] === false
				&& ( $cacheConf['store'] == 'db'
					|| ( $cacheConf['store'] == 'detect'
						&& !$config->get( MainConfigNames::CacheDirectory ) ) )
			) {
				$cacheConf['storeClass'] = LCStoreNull::class;
				$settingsBuilder->putConfigValue( MainConfigNames::LocalisationCacheConf, $cacheConf );
			}
		}

		$output = $this->parameters->getOption( 'profiler' );
		if ( $output ) {
			// Per-script profiling; useful for debugging
			$profilerConf = $config->get( MainConfigNames::Profiler );
			if ( isset( $profilerConf['class'] ) ) {
				$profilerConf = [
					'sampling' => 1,
					'output' => [ $output ],
					'cliEnable' => true,
				] + $profilerConf;
				// Override $wgProfiler. This is passed to Profiler::init() by Setup.php.
				$settingsBuilder->putConfigValue( MainConfigNames::Profiler, $profilerConf );
			}
		}

		$this->scriptObject->finalSetup( $settingsBuilder );
	}

	/**
	 * Run the maintenance script.
	 *
	 * @note The process should exit immediately after this method returns.
	 * At that point, MediaWiki will already have been shut down.
	 * It is no longer safe to perform any write operations on the database.
	 *
	 * @note Any exceptions thrown by the maintenance script will cause this
	 * method to terminate the process after reporting the error to the user,
	 * without shutdown and cleanup.
	 *
	 * @return bool true on success, false on failure,
	 *         passed through from Maintenance::execute().
	 */
	public function run(): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// Apply warning thresholds and output mode to Profiler.
		// This MUST happen after Setup.php calls MaintenanceRunner::setup,
		// $wgSettings->apply(), and Profiler::init(). Otherwise, calling
		// Profiler::instance() would create a ProfilerStub even when $wgProfiler
		// and --profiler are set.
		$limits = $config->get( MainConfigNames::TrxProfilerLimits );
		$trxProfiler = Profiler::instance()->getTransactionProfiler();
		$trxProfiler->setLogger( LoggerFactory::getInstance( 'rdbms' ) );
		$trxProfiler->setExpectations( $limits['Maintenance'], __METHOD__ );
		Profiler::instance()->setAllowOutput();

		// Initialize main config instance
		$this->scriptObject->setConfig( $config );

		// Double check required extensions are installed
		$this->scriptObject->checkRequiredExtensions();

		if ( $this->withoutLocalSettings && !$this->scriptObject->canExecuteWithoutLocalSettings() ) {
			$this->fatalError(
				"\nThe LocalSettings.php file was not found or is not readable.\n" .
				"Use --conf to specify an alternative config file.\n\n"
			);
		}

		if ( $this->scriptObject->getDbType() == Maintenance::DB_NONE || $this->withoutLocalSettings ) {
			// Be strict with maintenance tasks that claim to not need a database by
			// disabling the storage backend.
			MediaWikiServices::resetGlobalInstance( $config );
			MediaWikiServices::getInstance()->disableStorage();
		}

		$this->scriptObject->validateParamsAndArgs();

		// Do the work
		try {
			$success = $this->scriptObject->execute() !== false;

			// Potentially debug globals
			if ( $this->parameters->hasOption( 'globals' ) ) {
				print_r( $GLOBALS );
			}

			$this->scriptObject->shutdown();

			return $success;
		} catch ( Exception $ex ) {
			$exReportMessage = '';
			while ( $ex ) {
				$cls = get_class( $ex );
				$exReportMessage .= "$cls from line {$ex->getLine()} of {$ex->getFile()}: {$ex->getMessage()}\n";
				$exReportMessage .= $ex->getTraceAsString() . "\n";
				$ex = $ex->getPrevious();
			}
			$this->error( $exReportMessage );

			// Exit now because process is in an unsafe state.
			// Also to avoid DBTransactionError (T305730).
			// Do not commit database writes, do not run deferreds, do not pass Go.
			exit( 1 );
		}
	}

	/**
	 * Output a message and terminate the current script.
	 *
	 * @param string $msg Error message
	 * @param int $exitCode PHP exit status. Should be in range 1-254.
	 * @return never
	 */
	protected function fatalError( $msg, $exitCode = 1 ) {
		$this->error( $msg );
		exit( $exitCode );
	}

	/**
	 * @param string $msg
	 */
	protected function error( string $msg ) {
		// Print to stderr if possible, don't mix it in with stdout output.
		if ( defined( 'STDERR' ) ) {
			fwrite( STDERR, $msg );
		} else {
			echo $msg;
		}
	}

	/**
	 * Should we execute the maintenance script, or just allow it to be included
	 * as a standalone class? It checks that the call stack only includes this
	 * function and "requires" (meaning was called from the file scope)
	 *
	 * @return bool
	 */
	public static function shouldExecute() {
		global $wgCommandLineMode;

		if ( !function_exists( 'debug_backtrace' ) ) {
			// If someone has a better idea...
			return $wgCommandLineMode;
		}

		$bt = debug_backtrace();
		$count = count( $bt );
		if ( $bt[0]['class'] !== self::class || $bt[0]['function'] !== 'shouldExecute' ) {
			return false; // last call should be to this function
		}
		$includeFuncs = [ 'require_once', 'require', 'include', 'include_once' ];
		for ( $i = 1; $i < $count; $i++ ) {
			if ( !in_array( $bt[$i]['function'], $includeFuncs ) ) {
				return false; // previous calls should all be "requires"
			}
		}

		return true;
	}

	/**
	 * Handler for register_shutdown_function
	 * @internal
	 * @return void
	 */
	public function cleanup() {
		if ( $this->scriptObject ) {
			$this->scriptObject->cleanupChanneled();
		}
	}

}
