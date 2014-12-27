<?php
/**
 * ArchLinux skin (based on MonoBook)
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
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
 *
 * @file
 * @ingroup Skins
 */

$wgExtensionCredits['skin'][] = array(
	'path' => __FILE__,
	'name' => 'ArchLinux',
	'description' => 'MediaWiki skin based on MonoBook',
	'url' => 'https://www.archlinux.org/',
	'author' => array( 'Pierre Schmitz' ),
	'license-name' => 'GPLv2+',
);

// Register files
$wgAutoloadClasses['SkinArchLinux'] = __DIR__ . '/SkinArchLinux.php';
$wgAutoloadClasses['ArchLinuxTemplate'] = __DIR__ . '/ArchLinuxTemplate.php';

// Register skin
$wgValidSkinNames['archlinux'] = 'ArchLinux';

// Register modules
$wgResourceModules['skins.archlinux.styles'] = array(
	'styles' => array(
		'main.css' => array( 'media' => 'screen' ),
		'archnavbar.css' => array( 'media' => 'screen' ),
		'arch.css' => array( 'media' => 'screen' ),
		'print.css' => array( 'media' => 'print' )
	),
	'remoteSkinPath' => 'ArchLinux',
	'localBasePath' => __DIR__,
);
