<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @file
 * @ingroup Extensions
 * @version 3.0
 * @author Stephanie Amanda Stevens <phroziac@gmail.com>
 * @author Robin Pepermans (SPQRobin) <robinp.1273@gmail.com>
 * @copyright Copyright © 2005-2007 Stephanie Amanda Stevens
 * @copyright Copyright © 2007-2011 Robin Pepermans (SPQRobin)
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link http://www.mediawiki.org/wiki/Extension:SpecialInterwiki Documentation
 * Formatting improvements Stephen Kennedy, 2006.
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Set this value to true in LocalSettings.php if you will not use this
// extension to actually change any interwiki table entries. It will suppress
// the addition of a log for interwiki link changes.
$wgInterwikiViewOnly = false;

// Extension credits for Special:Version
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Interwiki',
	'author' => array( 'Stephanie Amanda Stevens', 'Alexandre Emsenhuber', 'Robin Pepermans', 'Siebrand Mazeland', 'Platonides', 'Raimond Spekking', 'Sam Reed', '...' ),
	'version' => '2.2 20120425',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Interwiki',
	'descriptionmsg' => 'interwiki-desc',
);

$wgExtensionFunctions[] = 'setupInterwikiExtension';

$wgResourceModules['ext.interwiki.specialpage'] = array(
	'styles' => 'Interwiki.css',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'Interwiki',
	'dependencies' => array(
		'jquery.makeCollapsible',
	),
);

// Set up the new special page
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['Interwiki'] = $dir . 'Interwiki.i18n.php';
$wgExtensionMessagesFiles['InterwikiAlias'] = $dir . 'Interwiki.alias.php';
$wgAutoloadClasses['SpecialInterwiki'] = $dir . 'Interwiki_body.php';
$wgAutoloadClasses['InterwikiLogFormatter'] = $dir . 'Interwiki_body.php';
$wgSpecialPages['Interwiki'] = 'SpecialInterwiki';
$wgSpecialPageGroups['Interwiki'] = 'wiki';

function setupInterwikiExtension() {
	global $wgInterwikiViewOnly;

	if ( $wgInterwikiViewOnly === false ) {
		global $wgAvailableRights, $wgLogTypes, $wgLogActionsHandlers;

		// New user right, required to modify the interwiki table through Special:Interwiki
		$wgAvailableRights[] = 'interwiki';

		// Set up the new log type - interwiki actions are logged to this new log
		$wgLogTypes[] = 'interwiki';
		# interwiki, iw_add, iw_delete, iw_edit
		$wgLogActionsHandlers['interwiki/*']  = 'InterwikiLogFormatter';
	}

	return true;
}
