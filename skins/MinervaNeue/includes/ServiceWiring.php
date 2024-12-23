<?php

/**
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
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\LanguagesHelper;
use MediaWiki\Minerva\Menu\Definitions;
use MediaWiki\Minerva\Menu\PageActions\PageActions;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\Permissions\MinervaPagePermissions;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Minerva\Skins\SkinUserPageHelper;

return [
	'Minerva.Menu.Definitions' => static function ( MediaWikiServices $services ): Definitions {
		return new Definitions(
			$services->getSpecialPageFactory()
		);
	},
	'Minerva.Menu.PageActions' => static function ( MediaWikiServices $services ): PageActions {
		return new PageActions(
			$services->getService( 'Minerva.LanguagesHelper' ),
			$services->getService( 'Minerva.Permissions' ),
			$services->getService( 'Minerva.SkinOptions' ),
			$services->getService( 'Minerva.SkinUserPageHelper' ),
			$services->getWatchlistManager()
		);
	},
	'Minerva.SkinUserPageHelper' => static function ( MediaWikiServices $services ): SkinUserPageHelper {
		return new SkinUserPageHelper(
			$services->getUserFactory(),
			$services->getUserNameUtils()
		);
	},
	'Minerva.LanguagesHelper' => static function ( MediaWikiServices $services ): LanguagesHelper {
		return new LanguagesHelper(
			$services->getLanguageConverterFactory()
		);
	},
	'Minerva.SkinOptions' => static function ( MediaWikiServices $services ): SkinOptions {
		return new SkinOptions(
			$services->getHookContainer(),
			$services->getService( 'Minerva.SkinUserPageHelper' )
		);
	},
	'Minerva.Permissions' => static function ( MediaWikiServices $services ): IMinervaPagePermissions {
		return new MinervaPagePermissions(
			$services->getService( 'Minerva.SkinOptions' ),
			$services->getService( 'Minerva.LanguagesHelper' ),
			$services->getPermissionManager(),
			$services->getContentHandlerFactory(),
			$services->getUserFactory(),
			$services->getWatchlistManager()
		);
	}
];
