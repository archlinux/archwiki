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

use MediaWiki\Language\MessageLocalizer;

/**
 * Director responsible for building Page Actions menu.
 * This class is stateless.
 */
final class PageActionsDirector {

	public function __construct(
		private readonly ToolbarBuilder $toolbarBuilder,
		private readonly IOverflowBuilder $overflowBuilder,
		private readonly MessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * Build the menu data array that can be passed to views/javascript
	 * @param array $toolbox An array of common toolbox items from the sidebar menu
	 * @param array $actions An array of actions usually bucketed under the more menu
	 * @param array $views An array of actions usually bucketed under the view menu
	 * @return array
	 */
	public function buildMenu( array $toolbox, array $actions, array $views ): array {
		$isBookmarkEnabled = isset( $views['bookmark'] );
		$toolbar = $this->toolbarBuilder->getGroup( $actions, $views, $isBookmarkEnabled );
		$overflowMenu = $this->overflowBuilder->getGroup( $toolbox, $actions, $isBookmarkEnabled );

		$menu = [
			'toolbar' => $toolbar->getEntries()
		];
		if ( $overflowMenu->hasEntries() ) {
			// See includes/Skins/ToggleList.
			$toggleID = 'page-actions-overflow-toggle';
			$checkboxID = 'page-actions-overflow-checkbox';
			$menu[ 'overflowMenu' ] = [
				'item-id' => 'page-actions-overflow',
				'checkboxID' => $checkboxID,
				'toggleID' => $toggleID,
				'event' => 'ui.overflowmenu',
				'data-btn' => [
					'tag-name' => 'label',
					'data-icon' => [
						'icon' => 'ellipsis',
					],
					'classes' => 'toggle-list__toggle',
					'array-attributes' => [
						[
							'key' => 'id',
							'value' => $toggleID,
						],
						[
							'key' => 'for',
							'value' => $checkboxID,
						],
						[
							'key' => 'aria-hidden',
							'value' => 'true'
						],
					],
					// class = toggle-list__toggle {{toggleClass}}
					// data-event-name="{{analyticsEventName}}">
					'label' => $this->messageLocalizer->msg( 'minerva-page-actions-overflow' )->text(),
				],
				'listID' => $overflowMenu->getId(),
				'listClass' => 'page-actions-overflow-list toggle-list__list--drop-down',
				'items' => $overflowMenu->getEntries()
			];
		}
		return $menu;
	}

}
