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
 * These should point to either an HTTP-accessible file or local file system.
 * $1 is the name of the repo (for extensions) and $2 is the name of file in the repo.
 */
$wgLocalisationUpdateCoreURL = "https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/core.git;a=blob_plain;f=$2;hb=HEAD";
$wgLocalisationUpdateExtensionURL = "https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/extensions/$1.git;a=blob_plain;f=$2;hb=HEAD";

/// Deprecated
$wgLocalisationUpdateSVNURL = false;

$wgLocalisationUpdateRetryAttempts = 5;

// Info about me!
$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'LocalisationUpdate',
	'author'         => array( 'Tom Maaswinkel', 'Niklas Laxström', 'Roan Kattouw' ),
	'version'        => '1.0',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:LocalisationUpdate',
	'descriptionmsg' => 'localisationupdate-desc',
);

$wgHooks['LocalisationCacheRecache'][] = 'LocalisationUpdate::onRecache';

$dir = __DIR__ . '/';
$wgExtensionMessagesFiles['LocalisationUpdate'] = $dir . 'LocalisationUpdate.i18n.php';
$wgAutoloadClasses['LocalisationUpdate'] = $dir . 'LocalisationUpdate.class.php';
$wgAutoloadClasses['QuickArrayReader'] = $dir . 'QuickArrayReader.php';
