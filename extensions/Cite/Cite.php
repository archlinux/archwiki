<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
/**#@+
 * A parser extension that adds two tags, <ref> and <references> for adding
 * citations to pages
 *
 * @file
 * @ingroup Extensions
 *
 * @link http://www.mediawiki.org/wiki/Extension:Cite/Cite.php Documentation
 *
 * @bug 4579
 *
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgHooks['ParserFirstCallInit'][] = 'wfCite';
$wgHooks['BeforePageDisplay'][] = 'wfCiteBeforePageDisplay';


$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Cite',
	'author' => array( 'Ævar Arnfjörð Bjarmason', 'Marius Hoch' ),
	'descriptionmsg' => 'cite-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Cite/Cite.php'
);
$wgParserTestFiles[] = __DIR__ . "/citeParserTests.txt";
$wgExtensionMessagesFiles['Cite'] = __DIR__ . "/Cite.i18n.php";
$wgAutoloadClasses['Cite'] = __DIR__ . "/Cite_body.php";
$wgSpecialPageGroups['Cite'] = 'pagetools';

define( 'CITE_DEFAULT_GROUP', '' );
/**
 * The emergency shut-off switch.  Override in local settings to disable
 * groups; or remove all references from this file to enable unconditionally
 */
$wgAllowCiteGroups = true;

/**
 * An emergency optimisation measure for caching cite <references /> output.
 */
$wgCiteCacheReferences = false;

/**
 * Enables experimental popups
 */
$wgCiteEnablePopups = false;

/**
 * Performs the hook registration.
 * Note that several extensions (and even core!) try to detect if Cite is
 * installed by looking for wfCite().
 *
 * @param $parser Parser
 *
 * @return bool
 */
function wfCite( $parser ) {
	return Cite::setHooks( $parser );
}

// Resources
$citeResourceTemplate = array(
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => 'Cite/modules'
);

$wgResourceModules['ext.cite'] = $citeResourceTemplate + array(
	'scripts' => 'ext.cite.js',
	'styles' => 'ext.cite.css',
	'messages' => array(
		'cite_references_link_accessibility_label',
		'cite_references_link_many_accessibility_label',
	),
);

$wgResourceModules['ext.cite.popups'] = $citeResourceTemplate + array(
	'scripts' => 'ext.cite.popups.js',
	'position' => 'bottom',
	'dependencies' => array(
		'jquery.tooltip',
	),
);

$wgResourceModules['jquery.tooltip'] = $citeResourceTemplate + array(
	'styles' => 'jquery.tooltip/jquery.tooltip.css',
	'scripts' => 'jquery.tooltip/jquery.tooltip.js',
	'position' => 'bottom',
);

/* Add RTL fix for the cite <sup> elements */
$wgResourceModules['ext.rtlcite'] = $citeResourceTemplate + array(
	'styles' => 'ext.rtlcite.css',
	'position' => 'top',
);

/**
 * @param $out OutputPage
 * @param $sk Skin
 * @return bool
 */
function wfCiteBeforePageDisplay( $out, &$sk ) {
	global $wgCiteEnablePopups;

	$out->addModules( 'ext.cite' );
	if ( $wgCiteEnablePopups ) {
		$out->addModules( 'ext.cite.popups' );
	}

	/* RTL support quick-fix module */
	$out->addModuleStyles( 'ext.rtlcite' );
	return true;
}

/**#@-*/
