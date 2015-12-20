<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'LocalisationUpdate' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$GLOBALS['wgMessagesDirs']['LocalisationUpdate'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for LocalisationUpdate extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
}
/**
 * Setup for pre-1.25 wikis. Make sure this is kept in sync with extension.json
 */

/**
 * Directory to store serialized cache files in. Defaults to $wgCacheDirectory.
 * It's OK to share this directory among wikis as long as the wiki you run
 * update.php on has all extensions the other wikis using the same directory
 * have.
 * NOTE: If this variable and $wgCacheDirectory are both false, this extension
 *       WILL NOT WORK.
 */
$GLOBALS['wgLocalisationUpdateDirectory'] = false;

/**
 * Default repository source to use.
 * @since 2014-03
 */
$GLOBALS['wgLocalisationUpdateRepository'] = 'github';

/**
 * Available repository sources.
 * @since 2014-03
 */
$GLOBALS['wgLocalisationUpdateRepositories'] = array();
$GLOBALS['wgLocalisationUpdateRepositories']['github'] = array(
	'mediawiki' =>
		'https://raw.github.com/wikimedia/mediawiki/master/%PATH%',
	'extension' =>
		'https://raw.github.com/wikimedia/mediawiki-extensions-%NAME%/master/%PATH%',
	'skin' =>
		'https://raw.github.com/wikimedia/mediawiki-skins-%NAME%/master/%PATH%',
);

// Example for local filesystem configuration
#$wgLocalisationUpdateRepositories['local'] = array(
#	'mediawiki' =>
#		'file:///resources/projects/mediawiki/master/%PATH%',
#	'extension' =>
#		'file:///resources/projects/mediawiki-extensions/extensions/%NAME%/%PATH%',
#	'skin' =>
#		'file:///resources/projects/mediawiki-skins/skins/%NAME%/%PATH%',
#);

$GLOBALS['wgExtensionCredits']['other'][] = array(
	'path' => __FILE__,
	'name' => 'LocalisationUpdate',
	'author' => array( 'Tom Maaswinkel', 'Niklas Laxström', 'Roan Kattouw' ),
	'version' => '1.3.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:LocalisationUpdate',
	'descriptionmsg' => 'localisationupdate-desc',
	'license-name' => 'GPL-2.0+',
);

$GLOBALS['wgHooks']['UnitTestsList'][] = 'LocalisationUpdate::setupUnitTests';
$GLOBALS['wgHooks']['LocalisationCacheRecache'][] = 'LocalisationUpdate::onRecache';
$GLOBALS['wgHooks']['LocalisationCacheRecacheFallback'][] = 'LocalisationUpdate::onRecacheFallback';

$dir = __DIR__;
$GLOBALS['wgMessagesDirs']['LocalisationUpdate'] = __DIR__ . '/i18n';
$GLOBALS['wgExtensionMessagesFiles']['LocalisationUpdate'] = "$dir/LocalisationUpdate.i18n.php";

require "$dir/Autoload.php";
