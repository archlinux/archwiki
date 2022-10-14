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
namespace MediaWiki\Minerva\Menu\Main;

use MediaWiki\SpecialPage\SpecialPageFactory;
use MessageLocalizer;

/**
 * Director responsible for building Main Menu
 */
final class MainMenuDirector {

	/**
	 * @var IMainMenuBuilder
	 */
	private $builder;

	/**
	 * @var array
	 */
	private $menuData;

	/**
	 * @var MessageLocalizer
	 */
	private $msgLocalizer;

	/**
	 * @var SpecialPageFactory
	 */
	private $specialPageFactory;

	/**
	 * Director responsible for Main Menu building
	 *
	 * @param IMainMenuBuilder $builder
	 * @param MessageLocalizer $messageLocalizer Used for translating texts in menu toggle
	 * @param SpecialPageFactory $specialPageFactory Used to check for MobileMenu special page
	 * existence
	 */
	public function __construct(
		IMainMenuBuilder $builder,
		MessageLocalizer $messageLocalizer,
		SpecialPageFactory $specialPageFactory
	) {
		$this->builder = $builder;
		$this->msgLocalizer = $messageLocalizer;
		$this->specialPageFactory = $specialPageFactory;
	}

	/**
	 * Returns a data representation of the main menus
	 *
	 * @param array $contentNavUrls result of buildContentNavigationUrls
	 * @param array $sidebar
	 * @return array
	 */
	public function getMenuData( array $contentNavUrls, array $sidebar ): array {
		if ( $this->menuData === null ) {
			$this->menuData = $this->buildMenu(
				$contentNavUrls,
				$sidebar
			);
		}
		return $this->menuData;
	}

	/**
	 * Build the menu data array that can be passed to views/javascript
	 *
	 * @param array $contentNavUrls
	 * @param array $sidebar
	 * @return array
	 */
	private function buildMenu( array $contentNavUrls, array $sidebar ): array {
		$builder = $this->builder;
		$menuData = [
			'items' => [
				'groups' => [],
				'sitelinks' => $this->builder->getSiteLinks()->getEntries()
			]
		];
		$groups = [
			// sidebar comes from MediaWiki:Sidebar so we can't assume it doesn't exist.
			$builder->getDiscoveryGroup( $sidebar['navigation'] ?? [] ),
			$builder->getInteractionToolsGroup(),
			$builder->getPersonalToolsGroup( $contentNavUrls['user-menu'] ),
			$builder->getDonateGroup(),
		];
		foreach ( $groups as $group ) {
			if ( $group->hasEntries() ) {
				$menuData['items']['groups'][] = $group->serialize();
			}
		}
		return $menuData;
	}
}
