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

namespace MediaWiki\Minerva\Menu\PageActions;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Minerva\LanguagesHelper;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\Permissions\MinervaPagePermissions;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Minerva\Skins\SkinUserPageHelper;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Watchlist\WatchlistManager;

class PageActions {

	private LanguagesHelper $languagesHelper;
	private IMinervaPagePermissions $permissions;
	private SkinOptions $skinOptions;
	private SkinUserPageHelper $skinUserPageHelper;
	private WatchlistManager $watchlistManager;

	public function __construct(
		LanguagesHelper $languagesHelper,
		MinervaPagePermissions $permissions,
		SkinOptions $skinOptions,
		SkinUserPageHelper $skinUserPageHelper,
		WatchlistManager $watchlistManager
	) {
		$this->languagesHelper = $languagesHelper;
		$this->permissions = $permissions;
		$this->skinOptions = $skinOptions;
		$this->skinUserPageHelper = $skinUserPageHelper;
		$this->watchlistManager = $watchlistManager;
	}

	public function getPageActionsDirector( IContextSource $context ): PageActionsDirector {
		$title = $context->getTitle();
		if ( !$title ) {
			$title = SpecialPage::getTitleFor( 'Badtitle' );
		}
		$user = $context->getUser();

		$this->skinUserPageHelper
			->setContext( $context )
			->setTitle( $title->inNamespace( NS_USER_TALK ) ?
				$context->getSkin()->getRelevantTitle()->getSubjectPage() :
				$title
			);

		$toolbarBuilder = new ToolbarBuilder(
			$title,
			$user,
			$context,
			$this->permissions,
			$this->skinOptions,
			$this->skinUserPageHelper,
			$this->languagesHelper,
			new ServiceOptions( ToolbarBuilder::CONSTRUCTOR_OPTIONS,
				$context->getConfig() ),
			$this->watchlistManager
		);
		if ( $this->skinOptions->get( SkinOptions::TOOLBAR_SUBMENU ) ) {
			$overflowBuilder = $this->skinUserPageHelper->isUserPage() ?
				new UserNamespaceOverflowBuilder(
					$title,
					$context,
					$this->permissions,
					$this->languagesHelper
				) :
				new DefaultOverflowBuilder(
					$title,
					$context,
					$this->permissions
				);
		} else {
			$overflowBuilder = new EmptyOverflowBuilder();
		}

		return new PageActionsDirector(
			$toolbarBuilder,
			$overflowBuilder,
			$context
		);
	}

}
