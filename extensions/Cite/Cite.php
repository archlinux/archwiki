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

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Cite',
	'author' => array(
		'Ævar Arnfjörð Bjarmason',
		'Andrew Garrett',
		'Brion Vibber',
		'Marius Hoch',
		'Steve Sanbeg'
	),
	'descriptionmsg' => 'cite-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Cite/Cite.php',
	'license-name' => 'GPLv2',
);

$wgParserTestFiles[] = __DIR__ . "/citeParserTests.txt";
$wgMessagesDirs['Cite'] = __DIR__ . '/i18n/core';
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

/* Add RTL fix for the cite <sup> elements */
$wgResourceModules['ext.rtlcite'] = $citeResourceTemplate + array(
	'styles' => 'ext.rtlcite.css',
	'position' => 'top',
);

/**#@-*/
