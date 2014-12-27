<?php

/**
 * Directory to store serialized cache files in. Defaults to $wgCacheDirectory.
 * It's OK to share this directory among wikis as long as the wiki you run
 * update.php on has all extensions the other wikis using the same directory
 * have.
 * NOTE: If this variable and $wgCacheDirectory are both false, this extension
 *       WILL NOT WORK.
 */
$wgLocalisationUpdateDirectory = false;

/**
 * Default repository source to use.
 * @since 2014-03
 */
$wgLocalisationUpdateRepository = 'github';

/**
 * Available repository sources.
 * @since 2014-03
 */
$wgLocalisationUpdateRepositories = array();
$wgLocalisationUpdateRepositories['github'] = array(
	'mediawiki' =>
		'https://raw.github.com/wikimedia/mediawiki-core/master/%PATH%',
	'extension' =>
		'https://raw.github.com/wikimedia/mediawiki-extensions-%NAME%/master/%PATH%',
);

// Example for local filesystem configuration
#$wgLocalisationUpdateRepositories['local'] = array(
#	'mediawiki' =>
#		'file:///resources/projects/mediawiki/master/%PATH%',
#	'extension' =>
#		'file:///resources/projects/mediawiki-extensions/extensions/%NAME%/%PATH%',
#);

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'LocalisationUpdate',
	'author' => array( 'Tom Maaswinkel', 'Niklas Laxström', 'Roan Kattouw' ),
	'version' => '1.3.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:LocalisationUpdate',
	'descriptionmsg' => 'localisationupdate-desc',
);

$wgHooks['LocalisationCacheRecache'][] = 'LocalisationUpdate::onRecache';
$wgHooks['LocalisationCacheRecacheFallback'][] = 'LocalisationUpdate::onRecacheFallback';

$dir = __DIR__;
$wgMessagesDirs['LocalisationUpdate'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['LocalisationUpdate'] = "$dir/LocalisationUpdate.i18n.php";

require "$dir/Autoload.php";
