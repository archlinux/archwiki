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

use MediaWiki\Minerva\MinervaUI;
use MessageLocalizer;
use MWException;

/**
 * Director responsible for building Page Actions menu.
 * This class is stateless.
 */
final class PageActionsDirector {

	/**
	 * @var ToolbarBuilder
	 */
	private $toolbarBuilder;

	/**
	 * @var IOverflowBuilder
	 */
	private $overflowBuilder;

	/**
	 * @var MessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * Director responsible for Page Actions menu building
	 *
	 * @param ToolbarBuilder $toolbarBuilder
	 * @param IOverflowBuilder $overflowBuilder The overflow menu builder
	 * @param MessageLocalizer $messageLocalizer Message localizer used to translate texts
	 */
	public function __construct(
		ToolbarBuilder $toolbarBuilder,
		IOverflowBuilder $overflowBuilder,
		MessageLocalizer $messageLocalizer
	) {
		$this->toolbarBuilder = $toolbarBuilder;
		$this->overflowBuilder = $overflowBuilder;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Build the menu data array that can be passed to views/javascript
	 * @param array $toolbox An array of common toolbox items from the sidebar menu
	 * @param array $actions An array of actions usually bucketed under the more menu
	 * @return array
	 * @throws MWException
	 */
	public function buildMenu( array $toolbox, array $actions ): array {
		$toolbar = $this->toolbarBuilder->getGroup();
		$overflowMenu = $this->overflowBuilder->getGroup( $toolbox, $actions );

		$menu = [
			'toolbar' => $toolbar->getEntries()
		];
		if ( $overflowMenu->hasEntries() ) {
			// See includes/Skins/ToggleList.
			$menu[ 'overflowMenu' ] = [
				'item-id' => 'page-actions-overflow',
				'checkboxID' => 'page-actions-overflow-checkbox',
				'toggleID' => 'page-actions-overflow-toggle',
				'listID' => $overflowMenu->getId(),
				'toggleClass' => MinervaUI::iconClass(
					'ellipsis',
					'element',
					'mw-ui-icon-with-label-desktop' ),
				'listClass' => 'page-actions-overflow-list toggle-list__list--drop-down',
				'text' => $this->messageLocalizer->msg( 'minerva-page-actions-overflow' ),
				'analyticsEventName' => 'ui.overflowmenu',
				'items' => $overflowMenu->getEntries()
			];
		}
		return $menu;
	}

}
