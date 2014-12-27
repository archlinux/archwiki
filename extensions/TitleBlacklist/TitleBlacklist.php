<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	exit( 1 );
}

// @{
/**
 * @file
 * @ingroup Extensions
 */

$wgExtensionCredits['antispam'][] = array(
	'path'           => __FILE__,
	'name'           => 'Title Blacklist',
	'author'         => array( 'Victor Vasiliev', 'Fran Rogers' ),
	'version'        => '1.5.0',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Title_Blacklist',
	'descriptionmsg' => 'titleblacklist-desc',
);

$dir = __DIR__;
$wgMessagesDirs['TitleBlacklist'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['TitleBlacklist'] = $dir . '/TitleBlacklist.i18n.php';
$wgAutoloadClasses['TitleBlacklist'] = $dir . '/TitleBlacklist.list.php';
$wgAutoloadClasses['TitleBlacklistHooks'] = $dir . '/TitleBlacklist.hooks.php';
$wgAutoloadClasses['Scribunto_LuaTitleBlacklistLibrary'] = $dir . '/TitleBlacklist.library.php';

/** @defgroup Title blacklist source types
 *  @{
 */
define( 'TBLSRC_MSG', 0 ); ///< For internal usage
define( 'TBLSRC_LOCALPAGE', 1 ); ///< Local wiki page
define( 'TBLSRC_URL', 2 ); ///< Load blacklist from URL
define( 'TBLSRC_FILE', 3 ); ///< Load from file
/** @} */

/**
 * Array of title blacklist sources.
 *
 * Should be in array( name => source description ) format.
 * See extension documentation for details of source description.
 */
$wgTitleBlacklistSources = array();

/**
 * Sets the sources which may work as a username filter.
 *
 * '*' is for all; false disables all.
 *
 * If you want to limit it to particular sources, use
 * array( source name 1, source name 2 ).
 * This may be useful when you have shared account creation system
 * in order to avoid blacklist fragmentation.
 */
$wgTitleBlacklistUsernameSources = '*';

/**
 * Log blacklist hits to Special:Log
 */
$wgTitleBlacklistLogHits = false;

$wgTitleBlacklistCaching = array(
	'warningchance' => 100,
	'expiry' => 900,
	'warningexpiry' => 600,
);

$dir = dirname( __FILE__ );

// Register the API method
$wgAutoloadClasses['ApiQueryTitleBlacklist'] = "$dir/api/ApiQueryTitleBlacklist.php";
$wgAPIModules['titleblacklist'] = 'ApiQueryTitleBlacklist';

$wgAvailableRights[] = 'tboverride'; // Implies tboverride-account
$wgAvailableRights[] = 'tboverride-account'; // For account creation
$wgGroupPermissions['sysop']['tboverride'] = true;

$wgHooks['getUserPermissionsErrorsExpensive'][] = 'TitleBlacklistHooks::userCan';
$wgHooks['TitleGetEditNotices'][] = 'TitleBlacklistHooks::displayBlacklistOverrideNotice';
$wgHooks['AbortMove'][] = 'TitleBlacklistHooks::abortMove';
$wgHooks['AbortNewAccount'][] = 'TitleBlacklistHooks::abortNewAccount';
$wgHooks['AbortAutoAccount'][] = 'TitleBlacklistHooks::abortNewAccount';
$wgHooks['EditFilter'][] = 'TitleBlacklistHooks::validateBlacklist';
$wgHooks['ArticleSaveComplete'][] = 'TitleBlacklistHooks::clearBlacklist';
$wgHooks['UserCreateForm'][] = 'TitleBlacklistHooks::addOverrideCheckbox';
$wgHooks['UnitTestsList'][] = function( &$files ) {
	$files += glob( __DIR__ . '/tests/*Test.php' );
	return true;
};
$wgHooks['ScribuntoExternalLibraries'][] = function( $engine, array &$extraLibraries ) {
	if( $engine == 'lua' ) {
		$extraLibraries['mw.ext.TitleBlacklist'] = 'Scribunto_LuaTitleBlacklistLibrary';
	}
	return true;
};

$wgLogTypes[] = 'titleblacklist';
$wgLogActionsHandlers['titleblacklist/*'] = 'LogFormatter';
$wgLogRestrictions['titleblacklist'] = 'titleblacklistlog';
$wgGroupPermissions['sysop']['titleblacklistlog'] = true;
$wgAvailableRights[] = 'titleblacklistlog';

$wgResourceModules['mediawiki.api.titleblacklist'] = array(
	'scripts' => 'mediawiki.api.titleblacklist.js',
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'TitleBlacklist/modules',
	'dependencies' => array( 'mediawiki.api' ),
);
// @}
