<?php
/**
 * The setup for all MediaWiki processes (both web-based and CLI).
 *
 * This file must be included by all entry points (such as WebStart.php and doMaintenance.php).
 * - The entry point MUST do these:
 *   - define the 'MEDIAWIKI' constant.
 *   - define the $IP global variable.
 * - The entry point SHOULD do these:
 *    - define the 'MW_ENTRY_POINT' constant.
 *    - display an error if MW_CONFIG_CALLBACK is not defined and the
 *      file specified in MW_CONFIG_FILE (or the $IP/LocalSettings.php default)
 *      does not exist. The error should either be sent before and instead
 *      of the Setup.php inclusion, or (if it needs classes and dependencies
 *      from core) the error can be displayed via a MW_CONFIG_CALLBACK,
 *      which must then abort the process to prevent the rest of Setup.php
 *      from executing.
 *
 * It does:
 * - run-time environment checks,
 * - load autoloaders, constants, default settings, and global functions,
 * - load the site configuration (e.g. LocalSettings.php),
 * - load the enabled extensions (via ExtensionRegistry),
 * - trivial expansion of site configuration defaults and shortcuts
 *   (no calls to MediaWikiServices or other parts of MediaWiki),
 * - initialization of:
 *   - PHP run-time (setlocale, memory limit, default date timezone)
 *   - the debug logger (MWDebug)
 *   - the service container (MediaWikiServices)
 *   - the exception handler (MWExceptionHandler)
 *   - the session manager (SessionManager)
 * - complex expansion of site configuration defaults (those that require
 *   calling into MediaWikiServices, global functions, or other classes.).
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

// phpcs:disable MediaWiki.Usage.DeprecatedGlobalVariables
use MediaWiki\HeaderCallback;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Settings\Config\GlobalConfigBuilder;
use MediaWiki\Settings\Config\PhpIniSink;
use MediaWiki\Settings\LocalSettingsLoader;
use MediaWiki\Settings\SettingsBuilder;
use MediaWiki\Settings\Source\PhpSettingsSource;
use MediaWiki\Settings\WikiFarmSettingsLoader;
use Psr\Log\LoggerInterface;
use Wikimedia\AtEase\AtEase;
use Wikimedia\RequestTimeout\RequestTimeout;

/**
 * Environment checks
 *
 * These are inline checks done before we include any source files,
 * and thus these conditions may be assumed by all source code.
 */

// This file must be included from a valid entry point (e.g. WebStart.php, Maintenance.php)
if ( !defined( 'MEDIAWIKI' ) ) {
	exit( 1 );
}

// PHP must not be configured to overload mbstring functions. (T5782, T122807)
// This was deprecated by upstream in PHP 7.2, likely to be removed in PHP 8.0.
if ( ini_get( 'mbstring.func_overload' ) ) {
	die( 'MediaWiki does not support installations where mbstring.func_overload is non-zero.' );
}

// The MW_ENTRY_POINT constant must always exists, to make it safe to access.
// For compat, we do support older and custom MW entrypoints that don't set this,
// in which case we assign a default here.
if ( !defined( 'MW_ENTRY_POINT' ) ) {
	/**
	 * The entry point, which may be either the script filename without the
	 * file extension, or "cli" for maintenance scripts, or "unknown" for any
	 * entry point that does not set the constant.
	 */
	define( 'MW_ENTRY_POINT', 'unknown' );
}

if ( !defined( 'MW_INSTALL_PATH' ) ) {
	define( 'MW_INSTALL_PATH', $IP );
} else {
	// enforce consistency
	$IP = MW_INSTALL_PATH;
}

/**
 * Pre-config setup: Before loading LocalSettings.php
 *
 * These are changes and additions to runtime that don't vary on site configuration.
 */
require_once "$IP/includes/AutoLoader.php";
require_once "$IP/includes/Defines.php";
require_once "$IP/includes/BootstrapHelperFunctions.php";

// Load composer's autoloader if present
if ( is_readable( "$IP/vendor/autoload.php" ) ) {
	require_once "$IP/vendor/autoload.php";
} elseif ( file_exists( "$IP/vendor/autoload.php" ) ) {
	die( "$IP/vendor/autoload.php exists but is not readable" );
}

// Assert that composer dependencies were successfully loaded
if ( !interface_exists( LoggerInterface::class ) ) {
	$message = (
		'MediaWiki requires the <a href="https://github.com/php-fig/log">PSR-3 logging ' .
		"library</a> to be present. This library is not embedded directly in MediaWiki's " .
		"git repository and must be installed separately by the end user.\n\n" .
		'Please see the <a href="https://www.mediawiki.org/wiki/Download_from_Git' .
		'#Fetch_external_libraries">instructions for installing libraries</a> on mediawiki.org ' .
		'for help on installing the required components.'
	);
	echo $message;
	trigger_error( $message, E_USER_ERROR );
}

// explicitly global, so it works with wfRequireOnceInGlobalScope()
global $wgSettings, $wgConf, $wgAutoloadClasses, $wgCommandLineMode;

// Set $wgCommandLineMode to false if it wasn't set to true.
$wgCommandLineMode = $wgCommandLineMode ?: false;

/**
 * $wgConf hold the site configuration.
 * Not used for much in a default install.
 * @since 1.5
 */
$wgConf = new SiteConfiguration;

$wgAutoloadClasses = $wgAutoloadClasses ?? [];

$wgSettings = new SettingsBuilder(
	$IP,
	ExtensionRegistry::getInstance(),
	new GlobalConfigBuilder( 'wg' ),
	new PhpIniSink()
);

if ( getenv( 'MW_USE_LEGACY_DEFAULT_SETTINGS' ) || defined( 'MW_USE_LEGACY_DEFAULT_SETTINGS' ) ) {
	// Load the old DefaultSettings.php file. Should be removed in 1.39. See T300129.
	require_once "$IP/includes/DefaultSettings.php";

	// This is temporary until we no longer need this mode.
	$wgSettings->load( new PhpSettingsSource( "$IP/includes/config-merge-strategies.php" ) );
} else {
	$wgSettings->load( new PhpSettingsSource( "$IP/includes/config-schema.php" ) );
}

require_once "$IP/includes/GlobalFunctions.php";

HeaderCallback::register();

// Set the encoding used by PHP for reading HTTP input, and writing output.
// This is also the default for mbstring functions.
mb_internal_encoding( 'UTF-8' );

/**
 * Load LocalSettings.php
 */

// Initialize some config settings with dynamic defaults, and
// make default settings available in globals for use in LocalSettings.php.
$wgSettings->putConfigValues( [
	'BaseDirectory' => $IP,
	'ExtensionDirectory' => "{$IP}/extensions",
	'StyleDirectory' => "{$IP}/skins",
	'ServiceWiringFiles' => [ "{$IP}/includes/ServiceWiring.php" ],
	'Version' => MW_VERSION,
] );
$wgSettings->apply();

if ( defined( 'MW_CONFIG_CALLBACK' ) ) {
	call_user_func( MW_CONFIG_CALLBACK, $wgSettings );
} else {
	wfDetectLocalSettingsFile( $IP );

	if ( getenv( 'MW_USE_LOCAL_SETTINGS_LOADER' ) ) {
		// NOTE: This will not work for configuration variables that use a prefix
		//       other than "wg".
		$localSettingsLoader = new LocalSettingsLoader( $wgSettings, $IP );
		$localSettingsLoader->loadLocalSettingsFile( MW_CONFIG_FILE );
		unset( $localSettingsLoader );
	} else {
		if ( str_ends_with( MW_CONFIG_FILE, '.php' ) ) {
			// make defaults available as globals
			$wgSettings->apply();
			require_once MW_CONFIG_FILE;
		} else {
			$wgSettings->loadFile( MW_CONFIG_FILE );
		}
	}
}

// Make settings loaded by LocalSettings.php available in globals for use here
$wgSettings->apply();

/**
 * Customization point after all loading (constants, functions, classes,
 * DefaultSettings, LocalSettings). Specifically, this is before usage of
 * settings, before instantiation of Profiler (and other singletons), and
 * before any setup functions or hooks run.
 */

if ( defined( 'MW_SETUP_CALLBACK' ) ) {
	call_user_func( MW_SETUP_CALLBACK, $wgSettings );
	// Make any additional settings available in globals for use here
	$wgSettings->apply();
}

// If in a wiki-farm, load site-specific settings
if ( $wgSettings->getConfig()->get( 'WikiFarmSettingsDirectory' ) ) {
	$wikiFarmSettingsLoader = new WikiFarmSettingsLoader( $wgSettings );
	$wikiFarmSettingsLoader->loadWikiFarmSettings();
	unset( $wikiFarmSettingsLoader );
}

// All settings should be loaded now.
$wgSettings->finalize();
if ( $wgBaseDirectory !== MW_INSTALL_PATH ) {
	throw new FatalError(
		'$wgBaseDirectory must not be modified in settings files! ' .
		'Use the MW_INSTALL_PATH environment variable to override the installation root directory.'
	);
}

// Start time limit
if ( $wgRequestTimeLimit && !$wgCommandLineMode ) {
	RequestTimeout::singleton()->setWallTimeLimit( $wgRequestTimeLimit );
}

/**
 * Load queued extensions
 */

ExtensionRegistry::getInstance()->loadFromQueue();
// Don't let any other extensions load
ExtensionRegistry::getInstance()->finish();

// Set an appropriate locale (T291234)
// setlocale() will return the locale name actually set.
// The putenv() is meant to propagate the choice of locale to shell commands
// so that they will interpret UTF-8 correctly. If you have a problem with a
// shell command and need to send a special locale, you can override the locale
// with Command::environment().
putenv( "LC_ALL=" . setlocale( LC_ALL, 'C.UTF-8', 'C' ) );

/**
 * Expand dynamic defaults and shortcuts
 */

if ( $wgScript === false ) {
	$wgScript = "$wgScriptPath/index.php";
}
if ( $wgLoadScript === false ) {
	$wgLoadScript = "$wgScriptPath/load.php";
}
if ( $wgRestPath === false ) {
	$wgRestPath = "$wgScriptPath/rest.php";
}
if ( $wgUsePathInfo === null ) {
	// These often break when PHP is set up in CGI mode.
	// PATH_INFO *may* be correct if cgi.fix_pathinfo is set, but then again it may not;
	// lighttpd converts incoming path data to lowercase on systems
	// with case-insensitive filesystems, and there have been reports of
	// problems on Apache as well.
	$wgUsePathInfo = ( strpos( PHP_SAPI, 'cgi' ) === false ) &&
		( strpos( PHP_SAPI, 'apache2filter' ) === false ) &&
		( strpos( PHP_SAPI, 'isapi' ) === false );
}
if ( $wgArticlePath === false ) {
	if ( $wgUsePathInfo ) {
		$wgArticlePath = "$wgScript/$1";
	} else {
		$wgArticlePath = "$wgScript?title=$1";
	}
}
if ( $wgResourceBasePath === null ) {
	$wgResourceBasePath = $wgScriptPath;
}
if ( $wgStylePath === false ) {
	$wgStylePath = "$wgResourceBasePath/skins";
}
if ( $wgLocalStylePath === false ) {
	// Avoid wgResourceBasePath here since that may point to a different domain (e.g. CDN)
	$wgLocalStylePath = "$wgScriptPath/skins";
}
if ( $wgExtensionAssetsPath === false ) {
	$wgExtensionAssetsPath = "$wgResourceBasePath/extensions";
}

// For backwards compatibility, the value of wgLogos is copied to wgLogo.
// This is because some extensions/skins may be using $config->get('Logo')
// to access the value.
if ( $wgLogos !== false && isset( $wgLogos['1x'] ) ) {
	$wgLogo = $wgLogos['1x'];
}
if ( $wgLogo === false ) {
	$wgLogo = "$wgResourceBasePath/resources/assets/change-your-logo.svg";
}

if ( $wgUploadPath === false ) {
	$wgUploadPath = "$wgScriptPath/images";
}
if ( $wgUploadDirectory === false ) {
	$wgUploadDirectory = "$IP/images";
}
if ( $wgReadOnlyFile === false ) {
	$wgReadOnlyFile = "{$wgUploadDirectory}/lock_yBgMBwiR";
}
if ( $wgFileCacheDirectory === false ) {
	$wgFileCacheDirectory = "{$wgUploadDirectory}/cache";
}
if ( $wgDeletedDirectory === false ) {
	$wgDeletedDirectory = "{$wgUploadDirectory}/deleted";
}
if ( $wgGitInfoCacheDirectory === false && $wgCacheDirectory !== false ) {
	$wgGitInfoCacheDirectory = "{$wgCacheDirectory}/gitinfo";
}
if ( $wgSharedPrefix === false ) {
	$wgSharedPrefix = $wgDBprefix;
}
if ( $wgSharedSchema === false ) {
	$wgSharedSchema = $wgDBmwschema;
}
if ( $wgMetaNamespace === false ) {
	$wgMetaNamespace = str_replace( ' ', '_', $wgSitename );
}

if ( $wgMainWANCache === false ) {
	// Create a WAN cache from $wgMainCacheType
	$wgMainWANCache = 'mediawiki-main-default';
	$wgWANObjectCaches[$wgMainWANCache] = [
		'class'    => WANObjectCache::class,
		'cacheId'  => $wgMainCacheType,
	];
}

// Back-compat
if ( isset( $wgFileBlacklist ) ) {
	$wgProhibitedFileExtensions = array_merge( $wgProhibitedFileExtensions, $wgFileBlacklist );
} else {
	$wgFileBlacklist = $wgProhibitedFileExtensions;
}
if ( isset( $wgMimeTypeBlacklist ) ) {
	$wgMimeTypeExclusions = array_merge( $wgMimeTypeExclusions, $wgMimeTypeBlacklist );
} else {
	$wgMimeTypeBlacklist = $wgMimeTypeExclusions;
}
if ( isset( $wgEnableUserEmailBlacklist ) ) {
	$wgEnableUserEmailMuteList = $wgEnableUserEmailBlacklist;
} else {
	$wgEnableUserEmailBlacklist = $wgEnableUserEmailMuteList;
}
if ( isset( $wgShortPagesNamespaceBlacklist ) ) {
	$wgShortPagesNamespaceExclusions = $wgShortPagesNamespaceBlacklist;
} else {
	$wgShortPagesNamespaceBlacklist = $wgShortPagesNamespaceExclusions;
}

// Prohibited file extensions shouldn't appear on the "allowed" list
$wgFileExtensions = array_values( array_diff( $wgFileExtensions, $wgProhibitedFileExtensions ) );

// Fix path to icon images after they were moved in 1.24
if ( $wgRightsIcon ) {
	$wgRightsIcon = str_replace(
		"{$wgStylePath}/common/images/",
		"{$wgResourceBasePath}/resources/assets/licenses/",
		$wgRightsIcon
	);
}

if ( isset( $wgFooterIcons['copyright']['copyright'] )
	&& $wgFooterIcons['copyright']['copyright'] === []
) {
	if ( $wgRightsIcon || $wgRightsText ) {
		$wgFooterIcons['copyright']['copyright'] = [
			'url' => $wgRightsUrl,
			'src' => $wgRightsIcon,
			'alt' => $wgRightsText,
		];
	}
}

if ( isset( $wgFooterIcons['poweredby'] )
	&& isset( $wgFooterIcons['poweredby']['mediawiki'] )
	&& $wgFooterIcons['poweredby']['mediawiki']['src'] === null
) {
	$wgFooterIcons['poweredby']['mediawiki']['src'] =
		"$wgResourceBasePath/resources/assets/poweredby_mediawiki_88x31.png";
	$wgFooterIcons['poweredby']['mediawiki']['srcset'] =
		"$wgResourceBasePath/resources/assets/poweredby_mediawiki_132x47.png 1.5x, " .
		"$wgResourceBasePath/resources/assets/poweredby_mediawiki_176x62.png 2x";
}

/**
 * Unconditional protection for NS_MEDIAWIKI since otherwise it's too easy for a
 * sysadmin to set $wgNamespaceProtection incorrectly and leave the wiki insecure.
 *
 * Note that this is the definition of editinterface and it can be granted to
 * all users if desired.
 */
$wgNamespaceProtection[NS_MEDIAWIKI] = 'editinterface';

/**
 * Initialise $wgLockManagers to include basic FS version
 */
$wgLockManagers[] = [
	'name' => 'fsLockManager',
	'class' => FSLockManager::class,
	'lockDirectory' => "{$wgUploadDirectory}/lockdir",
];
$wgLockManagers[] = [
	'name' => 'nullLockManager',
	'class' => NullLockManager::class,
];

/**
 * Determine whether EXIF info can be shown
 */
if ( $wgShowEXIF === null ) {
	$wgShowEXIF = function_exists( 'exif_read_data' );
}

/**
 * Default parameters for the "<gallery>" tag.
 * @see docs/Configuration.md for description of the fields.
 */
$wgGalleryOptions += [
	'imagesPerRow' => 0,
	'imageWidth' => 120,
	'imageHeight' => 120,
	'captionLength' => true,
	'showBytes' => true,
	'showDimensions' => true,
	'mode' => 'traditional',
];

/**
 * Shortcuts for $wgLocalFileRepo
 */
if ( !$wgLocalFileRepo ) {
	$wgLocalFileRepo = [
		'class' => LocalRepo::class,
		'name' => 'local',
		'directory' => $wgUploadDirectory,
		'scriptDirUrl' => $wgScriptPath,
		'favicon' => $wgFavicon,
		'url' => $wgUploadBaseUrl ? $wgUploadBaseUrl . $wgUploadPath : $wgUploadPath,
		'hashLevels' => $wgHashedUploadDirectory ? 2 : 0,
		'thumbScriptUrl' => $wgThumbnailScriptPath,
		'transformVia404' => !$wgGenerateThumbnailOnParse,
		'deletedDir' => $wgDeletedDirectory,
		'deletedHashLevels' => $wgHashedUploadDirectory ? 3 : 0,
		'updateCompatibleMetadata' => $wgUpdateCompatibleMetadata,
		'reserializeMetadata' => $wgUpdateCompatibleMetadata,
	];
}

if ( !isset( $wgLocalFileRepo['backend'] ) ) {
	// Create a default FileBackend name.
	// FileBackendGroup will register a default, if absent from $wgFileBackends.
	$wgLocalFileRepo['backend'] = $wgLocalFileRepo['name'] . '-backend';
}

/**
 * Shortcuts for $wgForeignFileRepos
 */
if ( $wgUseSharedUploads ) {
	if ( $wgSharedUploadDBname ) {
		$wgForeignFileRepos[] = [
			'class' => ForeignDBRepo::class,
			'name' => 'shared',
			'directory' => $wgSharedUploadDirectory,
			'url' => $wgSharedUploadPath,
			'hashLevels' => $wgHashedSharedUploadDirectory ? 2 : 0,
			'thumbScriptUrl' => $wgSharedThumbnailScriptPath,
			'transformVia404' => !$wgGenerateThumbnailOnParse,
			'dbType' => $wgDBtype,
			'dbServer' => $wgDBserver,
			'dbUser' => $wgDBuser,
			'dbPassword' => $wgDBpassword,
			'dbName' => $wgSharedUploadDBname,
			'dbFlags' => ( $wgDebugDumpSql ? DBO_DEBUG : 0 ) | DBO_DEFAULT,
			'tablePrefix' => $wgSharedUploadDBprefix,
			'hasSharedCache' => $wgCacheSharedUploads,
			'descBaseUrl' => $wgRepositoryBaseUrl,
			'fetchDescription' => $wgFetchCommonsDescriptions,
		];
	} else {
		$wgForeignFileRepos[] = [
			'class' => FileRepo::class,
			'name' => 'shared',
			'directory' => $wgSharedUploadDirectory,
			'url' => $wgSharedUploadPath,
			'hashLevels' => $wgHashedSharedUploadDirectory ? 2 : 0,
			'thumbScriptUrl' => $wgSharedThumbnailScriptPath,
			'transformVia404' => !$wgGenerateThumbnailOnParse,
			'descBaseUrl' => $wgRepositoryBaseUrl,
			'fetchDescription' => $wgFetchCommonsDescriptions,
		];
	}
}
if ( $wgUseInstantCommons ) {
	$wgForeignFileRepos[] = [
		'class' => ForeignAPIRepo::class,
		'name' => 'wikimediacommons',
		'apibase' => 'https://commons.wikimedia.org/w/api.php',
		'url' => 'https://upload.wikimedia.org/wikipedia/commons',
		'thumbUrl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb',
		'hashLevels' => 2,
		'transformVia404' => true,
		'fetchDescription' => true,
		'descriptionCacheExpiry' => 43200,
		'apiThumbCacheExpiry' => 0,
	];
}
foreach ( $wgForeignFileRepos as &$repo ) {
	if ( !isset( $repo['directory'] ) && $repo['class'] === ForeignAPIRepo::class ) {
		$repo['directory'] = $wgUploadDirectory; // b/c
	}
	if ( !isset( $repo['backend'] ) ) {
		$repo['backend'] = $repo['name'] . '-backend';
	}
}
unset( $repo ); // no global pollution; destroy reference

$rcMaxAgeDays = $wgRCMaxAge / ( 3600 * 24 );
// Ensure that default user options are not invalid, since that breaks Special:Preferences
$wgDefaultUserOptions['rcdays'] = min(
	$wgDefaultUserOptions['rcdays'],
	ceil( $rcMaxAgeDays )
);
$wgDefaultUserOptions['watchlistdays'] = min(
	$wgDefaultUserOptions['watchlistdays'],
	ceil( $rcMaxAgeDays )
);
unset( $rcMaxAgeDays );

if ( !$wgCookiePrefix ) {
	if ( $wgSharedDB && $wgSharedPrefix && in_array( 'user', $wgSharedTables ) ) {
		$wgCookiePrefix = $wgSharedDB . '_' . $wgSharedPrefix;
	} elseif ( $wgSharedDB && in_array( 'user', $wgSharedTables ) ) {
		$wgCookiePrefix = $wgSharedDB;
	} elseif ( $wgDBprefix ) {
		$wgCookiePrefix = $wgDBname . '_' . $wgDBprefix;
	} else {
		$wgCookiePrefix = $wgDBname;
	}
}
$wgCookiePrefix = strtr( $wgCookiePrefix, '=,; +."\'\\[', '__________' );

if ( $wgEnableEmail ) {
	$wgUseEnotif = $wgEnotifUserTalk || $wgEnotifWatchlist;
} else {
	// Disable all other email settings automatically if $wgEnableEmail
	// is set to false. - T65678
	$wgAllowHTMLEmail = false;
	$wgEmailAuthentication = false; // do not require auth if you're not sending email anyway
	$wgEnableUserEmail = false;
	$wgEnotifFromEditor = false;
	$wgEnotifImpersonal = false;
	$wgEnotifMaxRecips = 0;
	$wgEnotifMinorEdits = false;
	$wgEnotifRevealEditorAddress = false;
	$wgEnotifUseRealName = false;
	$wgEnotifUserTalk = false;
	$wgEnotifWatchlist = false;
	unset( $wgGroupPermissions['user']['sendemail'] );
	$wgUseEnotif = false;
	$wgUserEmailUseReplyTo = false;
	$wgUsersNotifiedOnAllChanges = [];
}

if ( $wgLocaltimezone === null ) {
	// This defaults to the `date.timezone` value of the PHP INI option. If this option is not set,
	// it falls back to UTC. Prior to PHP 7.0, this fallback produced a warning.
	$wgLocaltimezone = date_default_timezone_get();
}
date_default_timezone_set( $wgLocaltimezone );
if ( $wgLocalTZoffset === null ) {
	$wgLocalTZoffset = (int)date( 'Z' ) / 60;
}
// The part after the System| is ignored, but rest of MW fills it out as the local offset.
$wgDefaultUserOptions['timecorrection'] = "System|$wgLocalTZoffset";

if ( !$wgDBerrorLogTZ ) {
	$wgDBerrorLogTZ = $wgLocaltimezone;
}

/**
 * Definitions of the NS_ constants are in Defines.php
 * @internal
 */
$wgCanonicalNamespaceNames = NamespaceInfo::CANONICAL_NAMES;

// @todo UGLY UGLY
if ( is_array( $wgExtraNamespaces ) ) {
	$wgCanonicalNamespaceNames += $wgExtraNamespaces;
}

// Hard-deprecate setting $wgDummyLanguageCodes in LocalSettings.php
if ( count( $wgDummyLanguageCodes ) !== 0 ) {
	wfDeprecated( '$wgDummyLanguageCodes', '1.29' );
}
// Merge in the legacy language codes, incorporating overrides from the config
$wgDummyLanguageCodes += [
	// Internal language codes of the private-use area which get mapped to
	// themselves.
	'qqq' => 'qqq', // Used for message documentation
	'qqx' => 'qqx', // Used for viewing message keys
] + $wgExtraLanguageCodes + LanguageCode::getDeprecatedCodeMapping();
// Merge in (inverted) BCP 47 mappings
foreach ( LanguageCode::getNonstandardLanguageCodeMapping() as $code => $bcp47 ) {
	$bcp47 = strtolower( $bcp47 ); // force case-insensitivity
	if ( !isset( $wgDummyLanguageCodes[$bcp47] ) ) {
		$wgDummyLanguageCodes[$bcp47] = $wgDummyLanguageCodes[$code] ?? $code;
	}
}
unset( $code ); // no global pollution; destroy reference
unset( $bcp47 ); // no global pollution; destroy reference

// Temporary backwards-compatibility reading of old replica lag settings as of MediaWiki 1.36,
// to support sysadmins who fail to update their settings immediately:

if ( isset( $wgSlaveLagWarning ) ) {
	// If the old value is set to something other than the default, use it.
	if ( $wgDatabaseReplicaLagWarning === 10 && $wgSlaveLagWarning !== 10 ) {
		$wgDatabaseReplicaLagWarning = $wgSlaveLagWarning;
		wfDeprecated(
			'$wgSlaveLagWarning set but $wgDatabaseReplicaLagWarning unchanged; using $wgSlaveLagWarning',
			'1.36'
		);
	}
} else {
	// Backwards-compatibility for extensions that read this value.
	$wgSlaveLagWarning = $wgDatabaseReplicaLagWarning;
}

if ( isset( $wgSlaveLagCritical ) ) {
	// If the old value is set to something other than the default, use it.
	if ( $wgDatabaseReplicaLagCritical === 30 && $wgSlaveLagCritical !== 30 ) {
		$wgDatabaseReplicaLagCritical = $wgSlaveLagCritical;
		wfDeprecated(
			'$wgSlaveLagCritical set but $wgDatabaseReplicaLagCritical unchanged; using $wgSlaveLagCritical',
			'1.36'
		);
	}
} else {
	// Backwards-compatibility for extensions that read this value.
	$wgSlaveLagCritical = $wgDatabaseReplicaLagCritical;
}

if ( $wgInvalidateCacheOnLocalSettingsChange && defined( 'MW_CONFIG_FILE' ) ) {
	AtEase::suppressWarnings();
	$wgCacheEpoch = max( $wgCacheEpoch, gmdate( 'YmdHis', filemtime( MW_CONFIG_FILE ) ) );
	AtEase::restoreWarnings();
}

if ( $wgNewUserLog ) {
	// Add new user log type
	$wgLogTypes[] = 'newusers';
	$wgLogNames['newusers'] = 'newuserlogpage';
	$wgLogHeaders['newusers'] = 'newuserlogpagetext';
	$wgLogActionsHandlers['newusers/newusers'] = NewUsersLogFormatter::class;
	$wgLogActionsHandlers['newusers/create'] = NewUsersLogFormatter::class;
	$wgLogActionsHandlers['newusers/create2'] = NewUsersLogFormatter::class;
	$wgLogActionsHandlers['newusers/byemail'] = NewUsersLogFormatter::class;
	$wgLogActionsHandlers['newusers/autocreate'] = NewUsersLogFormatter::class;
}

if ( $wgPageCreationLog ) {
	// Add page creation log type
	$wgLogTypes[] = 'create';
	$wgLogActionsHandlers['create/create'] = LogFormatter::class;
}

if ( $wgPageLanguageUseDB ) {
	$wgLogTypes[] = 'pagelang';
	$wgLogActionsHandlers['pagelang/pagelang'] = PageLangLogFormatter::class;
}

if ( $wgCookieSecure === 'detect' ) {
	$wgCookieSecure = $wgForceHTTPS || ( WebRequest::detectProtocol() === 'https' );
}

// Backwards compatibility with old password limits
if ( $wgMinimalPasswordLength !== false ) {
	$wgPasswordPolicy['policies']['default']['MinimalPasswordLength'] = $wgMinimalPasswordLength;
}

if ( $wgMaximalPasswordLength !== false ) {
	$wgPasswordPolicy['policies']['default']['MaximalPasswordLength'] = $wgMaximalPasswordLength;
}

if ( $wgPHPSessionHandling !== 'enable' &&
	$wgPHPSessionHandling !== 'warn' &&
	$wgPHPSessionHandling !== 'disable'
) {
	$wgPHPSessionHandling = 'warn';
}
if ( defined( 'MW_NO_SESSION' ) ) {
	// If the entry point wants no session, force 'disable' here unless they
	// specifically set it to the (undocumented) 'warn'.
	$wgPHPSessionHandling = MW_NO_SESSION === 'warn' ? 'warn' : 'disable';
}

MWDebug::setup();

// Enable the global service locator.
// Trivial expansion of site configuration should go before this point.
// Any non-trivial expansion that requires calling into MediaWikiServices or other parts of MW.
MediaWikiServices::allowGlobalInstance();

// Define a constant that indicates that the bootstrapping of the service locator
// is complete.
define( 'MW_SERVICE_BOOTSTRAP_COMPLETE', 1 );

MWExceptionHandler::installHandler();

// Non-trivial validation of: $wgServer
// The FatalError page only renders cleanly after MWExceptionHandler is installed.
if ( $wgServer === false ) {
	// T30798: $wgServer must be explicitly set
	throw new FatalError(
		'$wgServer must be set in LocalSettings.php. ' .
		'See <a href="https://www.mediawiki.org/wiki/Manual:$wgServer">' .
		'https://www.mediawiki.org/wiki/Manual:$wgServer</a>.'
	);
}

// Non-trivial expansion of: $wgCanonicalServer, $wgServerName.
// These require calling global functions.
// Also here are other settings that further depend on these two.
if ( $wgCanonicalServer === false ) {
	$wgCanonicalServer = wfExpandUrl( $wgServer, PROTO_HTTP );
}
$wgVirtualRestConfig['global']['domain'] = $wgCanonicalServer;

$serverParts = wfParseUrl( $wgCanonicalServer );
if ( $wgServerName !== false ) {
	wfWarn( '$wgServerName should be derived from $wgCanonicalServer, '
		. 'not customized. Overwriting $wgServerName.' );
}
$wgServerName = $serverParts['host'];
unset( $serverParts );

// $wgEmergencyContact and $wgPasswordSender may be false or empty string (T104142)
if ( !$wgEmergencyContact ) {
	$wgEmergencyContact = 'wikiadmin@' . $wgServerName;
}
if ( !$wgPasswordSender ) {
	$wgPasswordSender = 'apache@' . $wgServerName;
}
if ( !$wgNoReplyAddress ) {
	$wgNoReplyAddress = $wgPasswordSender;
}

// Non-trivial expansion of: $wgSecureLogin
// (due to calling wfWarn).
if ( $wgSecureLogin && substr( $wgServer, 0, 2 ) !== '//' ) {
	$wgSecureLogin = false;
	wfWarn( 'Secure login was enabled on a server that only supports '
		. 'HTTP or HTTPS. Disabling secure login.' );
}

// Now that GlobalFunctions is loaded, set defaults that depend on it.
if ( $wgTmpDirectory === false ) {
	$wgTmpDirectory = wfTempDir();
}

if ( $wgSharedDB && $wgSharedTables ) {
	// Apply $wgSharedDB table aliases for the local LB (all non-foreign DB connections)
	MediaWikiServices::getInstance()->getDBLoadBalancer()->setTableAliases(
		array_fill_keys(
			$wgSharedTables,
			[
				'dbname' => $wgSharedDB,
				'schema' => $wgSharedSchema,
				'prefix' => $wgSharedPrefix
			]
		)
	);
}

// Raise the memory limit if it's too low
// NOTE: This use wfDebug, and must remain after the MWDebug::setup() call.
wfMemoryLimit( $wgMemoryLimit );

// Explicit globals, so this works with bootstrap.php
global $wgRequest, $wgInitialSessionId;

// Initialize the request object in $wgRequest
$wgRequest = RequestContext::getMain()->getRequest(); // BackCompat

// Make sure that object caching does not undermine the ChronologyProtector improvements
if ( $wgRequest->getCookie( 'UseDC', '' ) === 'master' ) {
	// The user is pinned to the primary DC, meaning that they made recent changes which should
	// be reflected in their subsequent web requests. Avoid the use of interim cache keys because
	// they use a blind TTL and could be stale if an object changes twice in a short time span.
	MediaWikiServices::getInstance()->getMainWANObjectCache()->useInterimHoldOffCaching( false );
}

// Useful debug output
( static function () {
	global $wgCommandLineMode, $wgRequest;
	$logger = LoggerFactory::getInstance( 'wfDebug' );
	if ( $wgCommandLineMode ) {
		$self = $_SERVER['PHP_SELF'] ?? '';
		$logger->debug( "\n\nStart command line script $self" );
	} else {
		$debug = "\n\nStart request {$wgRequest->getMethod()} {$wgRequest->getRequestURL()}\n";
		$debug .= "IP: " . $wgRequest->getIP() . "\n";
		$debug .= "HTTP HEADERS:\n";
		foreach ( $wgRequest->getAllHeaders() as $name => $value ) {
			$debug .= "$name: $value\n";
		}
		$debug .= "(end headers)";
		$logger->debug( $debug );
	}
} )();

// Most of the config is out, some might want to run hooks here.
Hooks::runner()->onSetupAfterCache();

// Now that variant lists may be available, parse any action paths and article paths
// as query parameters.
//
// Skip title interpolation on API queries where it is useless and sometimes harmful (T18019).
//
// Optimization: Skip on load.php and all other entrypoints besides index.php to save time.
//
// TODO: Figure out if this can be safely done after everything else in Setup.php (e.g. any
// hooks or other state that would miss this?). If so, move to wfIndexMain or MediaWiki::run.
if ( MW_ENTRY_POINT === 'index' ) {
	$wgRequest->interpolateTitle();
}

/**
 * @var MediaWiki\Session\SessionId|null The persistent session ID (if any) loaded at startup
 */
$wgInitialSessionId = null;
if ( !defined( 'MW_NO_SESSION' ) && !$wgCommandLineMode ) {
	// If session.auto_start is there, we can't touch session name
	if ( $wgPHPSessionHandling !== 'disable' && !wfIniGetBool( 'session.auto_start' ) ) {
		HeaderCallback::warnIfHeadersSent();
		session_name( $wgSessionName ?: $wgCookiePrefix . '_session' );
	}

	// Create the SessionManager singleton and set up our session handler,
	// unless we're specifically asked not to.
	if ( !defined( 'MW_NO_SESSION_HANDLER' ) ) {
		MediaWiki\Session\PHPSessionHandler::install(
			MediaWiki\Session\SessionManager::singleton()
		);
	}

	$contLang = MediaWikiServices::getInstance()->getContentLanguage();

	// Initialize the session
	try {
		$session = MediaWiki\Session\SessionManager::getGlobalSession();
	} catch ( MediaWiki\Session\SessionOverflowException $ex ) {
		// The exception is because the request had multiple possible
		// sessions tied for top priority. Report this to the user.
		$list = [];
		foreach ( $ex->getSessionInfos() as $info ) {
			$list[] = $info->getProvider()->describe( $contLang );
		}
		$list = $contLang->listToText( $list );
		throw new HttpError( 400,
			Message::newFromKey( 'sessionmanager-tie', $list )->inLanguage( $contLang )
		);
	}

	unset( $contLang );

	if ( $session->isPersistent() ) {
		$wgInitialSessionId = $session->getSessionId();
	}

	$session->renew();
	if ( MediaWiki\Session\PHPSessionHandler::isEnabled() &&
		( $session->isPersistent() || $session->shouldRememberUser() ) &&
		session_id() !== $session->getId()
	) {
		// Start the PHP-session for backwards compatibility
		if ( session_id() !== '' ) {
			wfDebugLog( 'session', 'PHP session {old_id} was already started, changing to {new_id}', 'all', [
				'old_id' => session_id(),
				'new_id' => $session->getId(),
			] );
			session_write_close();
		}
		session_id( $session->getId() );
		session_start();
	}

	unset( $session );
} else {
	// Even if we didn't set up a global Session, still install our session
	// handler unless specifically requested not to.
	if ( !defined( 'MW_NO_SESSION_HANDLER' ) ) {
		MediaWiki\Session\PHPSessionHandler::install(
			MediaWiki\Session\SessionManager::singleton()
		);
	}
}

// Explicit globals, so this works with bootstrap.php
global $wgUser, $wgLang, $wgOut, $wgParser, $wgTitle;

/**
 * @var User $wgUser
 * @deprecated since 1.35, use an available context source when possible, or, as a backup,
 * RequestContext::getMain()
 */
$wgUser = new StubGlobalUser( RequestContext::getMain()->getUser() ); // BackCompat
register_shutdown_function( static function () {
	StubGlobalUser::$destructorDeprecationDisarmed = true;
} );

/**
 * @var Language|StubUserLang $wgLang
 */
$wgLang = new StubUserLang;

/**
 * @var OutputPage $wgOut
 */
$wgOut = RequestContext::getMain()->getOutput(); // BackCompat

/**
 * @var Parser $wgParser
 * @deprecated since 1.32, use MediaWikiServices::getInstance()->getParser() instead
 */
$wgParser = new DeprecatedGlobal( 'wgParser', static function () {
	return MediaWikiServices::getInstance()->getParser();
}, '1.32' );

/**
 * @var Title|null $wgTitle
 */
$wgTitle = null;

// Explicit globals, so this works with bootstrap.php
global $wgFullyInitialised, $wgExtensionFunctions;

// Extension setup functions
// Entries should be added to this variable during the inclusion
// of the extension file. This allows the extension to perform
// any necessary initialisation in the fully initialised environment
foreach ( $wgExtensionFunctions as $func ) {
	call_user_func( $func );
}
unset( $func ); // no global pollution; destroy reference

// If the session user has a 0 id but a valid name, that means we need to
// autocreate it.
if ( !defined( 'MW_NO_SESSION' ) && !$wgCommandLineMode ) {
	$sessionUser = MediaWiki\Session\SessionManager::getGlobalSession()->getUser();
	if ( $sessionUser->getId() === 0 &&
		MediaWikiServices::getInstance()->getUserNameUtils()->isValid( $sessionUser->getName() )
	) {
		$res = MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
			$sessionUser,
			MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_SESSION,
			true
		);
		\MediaWiki\Logger\LoggerFactory::getInstance( 'authevents' )->info( 'Autocreation attempt', [
			'event' => 'autocreate',
			'status' => strval( $res ),
		] );
		unset( $res );
	}
	unset( $sessionUser );
}

if ( !$wgCommandLineMode ) {
	Pingback::schedulePingback();
}

$wgFullyInitialised = true;

// T264370
if ( !defined( 'MW_NO_SESSION' ) && !$wgCommandLineMode ) {
	MediaWiki\Session\SessionManager::singleton()->logPotentialSessionLeakage();
}
