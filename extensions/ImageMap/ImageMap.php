<?php
/**
 * ImageMap extension - Allows clickable HTML image maps.
 *
 * @link https://www.mediawiki.org/wiki/Extension:ImageMap Documentation
 *
 * @file
 * @ingroup Extensions
 * @package MediaWiki
 * @author Tim Starling
 * @copyright (C) 2007 Tim Starling
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
   die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

$dir = __DIR__ . '/';
$wgExtensionMessagesFiles['ImageMap'] = $dir . 'ImageMap.i18n.php';
$wgAutoloadClasses['ImageMap'] = $dir . 'ImageMap_body.php';
$wgHooks['ParserFirstCallInit'][] = 'wfSetupImageMap';

$wgExtensionCredits['parserhook']['ImageMap'] = array(
	'path'           => __FILE__,
	'name'           => 'ImageMap',
	'author'         => 'Tim Starling',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:ImageMap',
	'descriptionmsg' => 'imagemap_desc',
);

/**
 * @param $parser Parser
 * @return bool
 */
function wfSetupImageMap( &$parser ) {
	$parser->setHook( 'imagemap', array( 'ImageMap', 'render' ) );
	return true;
}
