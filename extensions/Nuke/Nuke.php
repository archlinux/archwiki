<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

define( 'Nuke_VERSION', '1.2.0' );

$dir = dirname( __FILE__ ) . '/';

$wgMessagesDirs['Nuke'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Nuke'] = $dir . 'Nuke.i18n.php';
$wgExtensionMessagesFiles['NukeAlias'] = $dir . 'Nuke.alias.php';

$wgExtensionCredits['specialpage'][] = array(
	'path'           => __FILE__,
	'name'           => 'Nuke',
	'descriptionmsg' => 'nuke-desc',
	'author'         => array( 'Brion Vibber', 'Jeroen De Dauw' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Nuke',
	'version'        => Nuke_VERSION,
);

$wgGroupPermissions['sysop']['nuke'] = true;
$wgAvailableRights[] = 'nuke';

$wgAutoloadClasses['SpecialNuke'] = $dir . 'Nuke_body.php';
$wgAutoloadClasses['NukeHooks'] = $dir . 'Nuke.hooks.php';
$wgSpecialPages['Nuke'] = 'SpecialNuke';
$wgSpecialPageGroups['Nuke'] = 'pagetools';

$wgHooks['ContributionsToolLinks'][] = 'NukeHooks::nukeContributionsLinks';

// Resource loader modules
$moduleTemplate = array(
	'localBasePath' => dirname( __FILE__ ) . '/',
	'remoteExtPath' => 'Nuke/'
);

$wgResourceModules['ext.nuke'] = $moduleTemplate + array(
	'scripts' => array(
		'ext.nuke.js'
	),
	'messages' => array(
	)
);

unset( $moduleTemplate );
