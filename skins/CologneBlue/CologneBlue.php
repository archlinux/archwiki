<?php
/**
 * CologneBlue skin
 *
 * @file
 * @ingroup Extensions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

$wgExtensionCredits['skin'][] = array(
	'path' => __FILE__,
	'name' => 'Cologne Blue',
	'namemsg' => 'skinname-cologneblue',
	'descriptionmsg' => 'cologneblue-desc',
	'url' => 'https://www.mediawiki.org/wiki/Skin:Cologne_Blue',
	'author' => array( 'Lee Daniel Crocker', '...' ),
	'license-name' => 'GPLv2+',
);

// Register files
$wgAutoloadClasses['SkinCologneBlue'] = __DIR__ . '/SkinCologneBlue.php';
$wgAutoloadClasses['CologneBlueTemplate'] = __DIR__ . '/SkinCologneBlue.php';
$wgMessagesDirs['CologneBlue'] = __DIR__ . '/i18n';

// Register skin
$wgValidSkinNames['cologneblue'] = 'CologneBlue';

// Register modules
$wgResourceModules['skins.cologneblue'] = array(
	'styles' => array(
		'resources/screen.css' => array( 'media' => 'screen' ),
		'resources/print.css' => array( 'media' => 'print' ),
	),
	'remoteBasePath' => $GLOBALS['wgStylePath'] . '/CologneBlue',
	'localBasePath' => __DIR__,
);
