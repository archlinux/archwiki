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

use MediaWiki\Minerva\Menu\Definitions;
use MediaWiki\Minerva\Menu\Entries\SingleMenuEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityUtils;

/**
 * Used to build default (available for everyone by default) main menu
 */
final class DefaultMainMenuBuilder implements IMainMenuBuilder {

	/**
	 * Initialize the Default Main Menu builder
	 *
	 * @param bool $showMobileOptions Show MobileOptions instead of Preferences
	 * @param bool $showDonateLink whether to show the donate link
	 * @param User $user The current user
	 * @param Definitions $definitions A menu items definitions set
	 * @param UserIdentityUtils $userIdentityUtils
	 * @param bool $isPersonalModeEnabled whether the rendering of personal links (e.g. login)
	 *  is handled outside this menu. This corresponds to $wgMinervaPersonalMenu feature value.
	 */
	public function __construct(
		private readonly bool $showMobileOptions,
		private readonly bool $showDonateLink,
		private readonly User $user,
		private readonly Definitions $definitions,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly bool $isPersonalModeEnabled,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getDiscoveryGroup( array $navigationTools ): Group {
		return BuilderUtil::getDiscoveryTools( $this->definitions, $navigationTools );
	}

	/**
	 * @inheritDoc
	 */
	public function getDonateGroup(): Group {
		return BuilderUtil::getDonateGroup( $this->definitions, $this->showDonateLink );
	}

	/**
	 * @inheritDoc
	 */
	public function getInteractionToolsGroup(): Group {
		return new Group( 'p-interaction' );
	}

	/**
	 * @inheritDoc
	 */
	public function getSiteLinks(): Group {
		return BuilderUtil::getSiteLinks( $this->definitions );
	}

	/**
	 * Builds the anonymous settings group.
	 *
	 * @inheritDoc
	 */
	public function getSettingsGroup(): Group {
		$group = new Group( 'pt-preferences' );
		// Show settings group for anon and temp users
		$isTemp = $this->userIdentityUtils->isTemp( $this->user );

		$settingsGroupNeeded = $this->isPersonalModeEnabled || (
			( !$this->user->isRegistered() || $isTemp )
		);

		if ( $this->showMobileOptions && $settingsGroupNeeded ) {
			$this->definitions->insertMobileOptionsItem( $group );
		}
		return $group;
	}

	/**
	 * Builds the personal tools menu item group.
	 *
	 * ... by adding the Watchlist, Settings, and Log{in,out} menu items in the given order.
	 *
	 * @inheritDoc
	 */
	public function getPersonalToolsGroup( array $personalTools, bool $shouldShowAccountMenuItems ): Group {
		$group = new Group( 'p-personal' );
		$excludeKeyList = [ 'betafeatures', 'mytalk', 'sandbox' ];

		// If personal tools are handled elsewhere, no need to output them in this menu.
		if ( $this->isPersonalModeEnabled ) {
			return $group;
		}

		$isTemp = $this->userIdentityUtils->isTemp( $this->user );
		if ( $isTemp ) {
			$excludeKeyList[] = 'mycontris';
		}

		if ( !$this->user->isRegistered() ) {
			// For anonymous users exclude all links except login.
			$keysToNotExclude = [ 'login', 'login-private' ];

			// TODO remove after experiment concludes, T418053
			// Display the Create Account button as well when
			// a logged out user is in the we-1-8-mobile-account-menu treatment group
			if ( $shouldShowAccountMenuItems ) {
				$keysToNotExclude[] = 'createaccount';
			}

			$excludeKeyList = array_diff(
				array_keys( $personalTools ),
				$keysToNotExclude
			);
		}
		foreach ( $personalTools as $key => $item ) {
			// Default to EditWatchlist if $user has no edits
			// Many users use the watchlist like a favorites list without ever editing.
			// [T88270].
			if ( $key === 'watchlist' && $this->user->getEditCount() === 0 ) {
				$item['href'] = Title::newFromText( 'Special:EditWatchlist' )->getLocalUrl();
			}
			// TODO remove after experiment concludes, T418053
			// Display a different icon for the create account button when
			// a logged out user is in the we-1-8-mobile-account-menu treatment group
			if ( $key === 'createaccount' && $shouldShowAccountMenuItems ) {
				$item['icon'] = 'userAvatar';
			}
			$href = $item['href'] ?? null;
			if ( $href && !in_array( $key, $excludeKeyList ) ) {
				// Substitute preference if $showMobileOptions is set.
				if ( $this->showMobileOptions && $key === 'preferences' ) {
					$this->definitions->insertMobileOptionsItem( $group );
				} else {
					$icon = $item['icon'] ?? null;
					$entry = SingleMenuEntry::create(
						$key,
						$item['text'],
						$href,
						$item['class'] ?? '',
						$icon
					);

					$entry->trackClicks( $key );
					$group->insertEntry( $entry );
				}
			}
		}
		return $group;
	}
}
