<?php
/**
 * InputBox extension
 *
 * @file
 * @ingroup Extensions
 *
 * This file contains the main include file for the Inputbox extension of
 * MediaWiki.
 *
 * Usage: Add the following line in LocalSettings.php:
 * require_once( "$IP/extensions/InputBox/InputBox.php" );
 *
 * @author Erik Moeller <moeller@scireview.de>
 *  namespaces search improvements partially by
 *  Leonardo Pimenta <leo.lns@gmail.com>
 *	Cleaned up by Trevor Parscal <tparscal@wikimedia.org>
 * @copyright Public domain
 * @license Public domain
 * @version 0.1.4
 */

// Check environment
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is an extension to the MediaWiki package and cannot be run standalone.\n";
	die( -1 );
}

/* Configuration */

// Credits
$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'InputBox',
	'author'         => array( 'Erik Moeller', 'Leonardo Pimenta', 'Rob Church', 'Trevor Parscal', 'DaSch' ),
	'version'        => '0.2.0',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:InputBox',
	'description'    => 'Allow inclusion of predefined HTML forms.',
	'descriptionmsg' => 'inputbox-desc',
);

// Shortcut to this extension directory
$dir = __DIR__ . '/';

// Internationalization
$wgMessagesDirs['InputBox'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['InputBox'] = $dir . 'InputBox.i18n.php';

// Register auto load for the special page class
$wgAutoloadClasses['InputBoxHooks'] = $dir . 'InputBox.hooks.php';
$wgAutoloadClasses['InputBox'] = $dir . 'InputBox.classes.php';

// Register parser hook
$wgHooks['ParserFirstCallInit'][] = 'InputBoxHooks::register';
$wgHooks['MediaWikiPerformAction'][] = 'InputBoxHooks::onMediaWikiPerformAction';
$wgHooks['SpecialPageBeforeExecute'][] = 'InputBoxHooks::onSpecialPageBeforeExecute';

$wgResourceModules['ext.inputBox.styles'] = array(
	'localBasePath' => dirname( __FILE__ ) . '/resources',
	'remoteExtPath' => 'InputBox/resources',
	'styles' => 'ext.inputBox.styles.css',
);
