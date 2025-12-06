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
use MediaWiki\Minerva\Menu\Entries\IMenuEntry;
use MediaWiki\Minerva\Menu\Entries\LanguageSelectorEntry;
use MediaWiki\Minerva\Menu\Entries\SingleMenuEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Minerva\Skins\SkinUserPageHelper;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\Watchlist\WatchlistManager;
use SpecialMobileHistory;

class ToolbarBuilder {

	/** @var Title Article title user is currently browsing */
	private Title $title;
	/** @var User Currently logged in user */
	private User $user;
	private IContextSource $context;
	private IMinervaPagePermissions $permissions;
	private SkinOptions $skinOptions;
	private SkinUserPageHelper $relevantUserPageHelper;
	private LanguagesHelper $languagesHelper;
	/** @var bool Correlates to $wgWatchlistExpiry feature flag. */
	private bool $watchlistExpiryEnabled;
	private WatchlistManager $watchlistManager;
	private Title $loginTitle;

	/**
	 * ServiceOptions needed.
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'WatchlistExpiry',
	];

	/**
	 * Build Group containing icons for toolbar
	 * @param Title $title Article title user is currently browsing
	 * @param User $user Currently logged in user
	 * @param IContextSource $context
	 * @param IMinervaPagePermissions $permissions Minerva permissions system
	 * @param SkinOptions $skinOptions
	 * @param SkinUserPageHelper $relevantUserPageHelper User Page helper. The
	 * UserPageHelper passed should always be specific to the user page Title. If on a
	 * user talk page, UserPageHelper should be instantiated with the user page
	 * Title and NOT with the user talk page Title.
	 * @param LanguagesHelper $languagesHelper Helper to check title languages/variants
	 * @param ServiceOptions $options
	 * @param WatchlistManager $watchlistManager
	 */
	public function __construct(
		Title $title,
		User $user,
		IContextSource $context,
		IMinervaPagePermissions $permissions,
		SkinOptions $skinOptions,
		SkinUserPageHelper $relevantUserPageHelper,
		LanguagesHelper $languagesHelper,
		ServiceOptions $options,
		WatchlistManager $watchlistManager,
		?Title $loginTitle = null
	) {
		$this->title = $title;
		$this->user = $user;
		$this->context = $context;
		$this->permissions = $permissions;
		$this->skinOptions = $skinOptions;
		$this->relevantUserPageHelper = $relevantUserPageHelper;
		$this->languagesHelper = $languagesHelper;
		$this->watchlistExpiryEnabled = $options->get( 'WatchlistExpiry' );
		$this->watchlistManager = $watchlistManager;
		$this->loginTitle = $loginTitle ?: SpecialPage::getTitleFor( 'Userlogin' );
	}

	/**
	 * @param array $actions
	 * @param array $views
	 * @param bool $isBookmarkEnabled
	 * @return Group
	 */
	public function getGroup( array $actions, array $views, bool $isBookmarkEnabled = false ): Group {
		$group = new Group( 'p-views' );
		$permissions = $this->permissions;
		$userPageOrUserTalkPageWithOverflowMode = $this->skinOptions->get( SkinOptions::TOOLBAR_SUBMENU )
			&& $this->relevantUserPageHelper->isUserPage();

		if ( !$userPageOrUserTalkPageWithOverflowMode && $permissions->isAllowed(
			IMinervaPagePermissions::SWITCH_LANGUAGE ) ) {
			$group->insertEntry( new LanguageSelectorEntry(
				$this->title,
				$this->languagesHelper->doesTitleHasLanguagesOrVariants(
					$this->context->getOutput(),
					$this->title
				),
				$this->context,
				true
			) );
		}
		// Don't render the "view" action.
		unset( $views[  'view' ] );

		$watchKey = $key = isset( $actions['unwatch'] ) ? 'unwatch' : 'watch';
		// The watchstar is typically not shown to anonymous users but it is in Minerva.
		$watchData = $actions[ $watchKey ] ?? null;
		// If the watchstar doesn't exist AND the user is not named (e.g. anonymous )
		// we inject a watchstar icon that links to the login page. This will open a drawer
		// that informs the user that this feature exists.
		// We don't want to do this in the case the user is already logged in (this situation
		// arises when the ReadingList extension is installed which may unset the watchstar icon).
		// If the watchstar is null and the user is logged in, it means the page is not watchable
		// (for example special pages) so we should not worry about this case (L145 will ignore it).
		if ( !$watchData && !$this->user->isNamed() ) {
			$watchData = [
				'icon' => 'star',
				'class' => '',
				'href' => $this->getLoginUrl( [ 'returnto' => $this->title ] ),
				'text' => $this->context->msg( 'watch' ),
			];
		}
		if ( $isBookmarkEnabled ) {
			// Relocate bookmark icon to front so it is consistent with watchstar position
			self::copyItemToGroup( $views['bookmark'], 'bookmark', $group, $this->context );
			unset( $views[ 'bookmark' ] );
		} else {
			// THIS WILL ONLY HAPPEN IF NO BOOKMARK IS DETECTED IN THE VIEWS MENU
			if ( $permissions->isAllowed( IMinervaPagePermissions::WATCHABLE ) && $watchData ) {
				$group->insertEntry( $this->createWatchPageAction( $watchKey, $watchData ) );
			}
		}

		$historyView = $views[ 'history'] ?? [];
		unset( $views[ 'history' ] );

		if ( $historyView && $permissions->isAllowed( IMinervaPagePermissions::HISTORY ) ) {
			$group->insertEntry( $this->getHistoryPageAction( $historyView ) );
		}

		$user = $this->relevantUserPageHelper->getPageUser();
		$isUserPageAccessible = $this->relevantUserPageHelper->isUserPageAccessibleToCurrentUser();
		if ( $user && $isUserPageAccessible ) {
			// T235681: Contributions icon should be added to toolbar on user pages
			// and user talk pages for all users
			$group->insertEntry( $this->createContributionsPageAction( $user ) );
		}

		// T388909: Iterate through all view actions. Known edit actions (edit, ve-edit, viewsource) are
		// always included if the user has edit permissions, even if they do not define an icon.
		// All other actions must define an icon to be included in the mobile toolbar.
		// This ensures compatibility with hook-defined entries (e.g., 'bookmark') while preserving
		// fallback icons for core actions.
		foreach ( $views as $key => $viewData ) {
			$isEditAction = in_array( $key, [ 've-edit', 'viewsource', 'edit' ] );

			if ( $isEditAction ) {
				// Only insert edit actions if user has permission
				if ( $permissions->isAllowed( IMinervaPagePermissions::CONTENT_EDIT ) ) {
					$group->insertEntry( $this->createEditPageAction( $key, $viewData ) );
				}
			} elseif ( isset( $viewData[ 'icon' ] ) ) {
				self::copyItemToGroup( $viewData, $key, $group, $this->context );
			}
		}
		return $group;
	}

	/**
	 * @param array $viewData
	 * @param string $key
	 * @param Group $group
	 * @param IContextSource $context
	 */
	private static function copyItemToGroup( $viewData, $key, $group, $context ) {
		$entry = new SingleMenuEntry(
			'page-actions-' . $key,
			$viewData['text'] ?? $key,
			$viewData['href'] ?? '#',
			$viewData['class'] ?? '',
			true,
			$viewData['array-attributes'] ?? []
		);

		$entry
			->setIcon( $viewData['icon'] )
			->trackClicks( $key )
			->setTitle( $context->msg( 'tooltip-' . $key ) )
			->setNodeID( 'ca-' . $key );

		$group->insertEntry( $entry );
	}

	/**
	 * Create Contributions page action visible on user pages or user talk pages
	 * for given $user
	 *
	 * @param UserIdentity $user Determines what the contribution page action will link to
	 * @return IMenuEntry
	 */
	protected function createContributionsPageAction( UserIdentity $user ): IMenuEntry {
		$label = $this->context->msg( 'mobile-frontend-user-page-contributions' );

		$entry = new SingleMenuEntry(
			'page-actions-contributions',
			$label->escaped(),
			SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getLocalURL() );
		$entry->setTitle( $label )
			->trackClicks( 'contributions' )
			->setIcon( 'userContributions' );

		return $entry;
	}

	/**
	 * Creates the "edit" page action: the well-known pencil icon that, when tapped, will open an
	 * editor.
	 *
	 * @param string $key
	 * @param array $editAction
	 * @return IMenuEntry An edit page actions menu entry
	 */
	protected function createEditPageAction( string $key, array $editAction ): IMenuEntry {
		$title = $this->title;

		$id = $editAction['single-id'] ?? 'ca-edit';
		$entry = new SingleMenuEntry(
			'page-actions-' . $key,
			$editAction['text'],
			$editAction['href'],
			'edit-page'
		);
		$iconFallback = $key === 'viewsource' ? 'editLock' : 'edit';
		$icon = $editAction['icon'] ?? $iconFallback;
		$entry->setIcon( $icon )
			->trackClicks( $key )
			->setTitle( $this->context->msg( 'tooltip-' . $id ) )
			->setNodeID( $id );
		return $entry;
	}

	/**
	 * Creates the "watch" or "unwatch" action: the well-known star icon that, when tapped, will
	 * add the page to or remove the page from the user's watchlist; or, if the user is logged out,
	 * will direct the user's UA to Special:Login.
	 *
	 * @param string $watchKey either watch or unwatch
	 * @param array $watchData
	 * @return IMenuEntry An watch/unwatch page actions menu entry
	 */
	protected function createWatchPageAction( string $watchKey, array $watchData ): IMenuEntry {
		$entry = new SingleMenuEntry(
			'page-actions-watch',
			$watchData['text'],
			$watchData['href'],
			$watchData[ 'class' ],
			$this->permissions->isAllowed( IMinervaPagePermissions::WATCH )
		);
		$icon = $watchData['icon'] ?? '';
		return $entry->trackClicks( $watchKey )
			->setIcon( $icon )
			->setTitle( $this->context->msg( $watchKey ) )
			->setNodeID( 'ca-watch' );
	}

	/**
	 * Creates a history action: An icon that links to the mobile history page.
	 *
	 * @param array $historyAction
	 * @return IMenuEntry A menu entry object that represents a map of HTML attributes
	 * and a 'text' property to be used with the pageActionMenu.mustache template.
	 */
	protected function getHistoryPageAction( array $historyAction ): IMenuEntry {
		$entry = new SingleMenuEntry(
			'page-actions-history',
			$historyAction['text'],
			$historyAction['href'],
		);
		$icon = $historyAction['icon'] ?? 'history';
		$entry->setIcon( $icon )
			->trackClicks( 'history' );
		return $entry;
	}

	/**
	 * Get the URL for the history page for the given title using Special:History
	 * when available.
	 * FIXME: temporary duplicated code, same as SkinMinerva::getHistoryUrl()
	 * @param Title $title The Title object of the page being viewed
	 * @return string
	 */
	protected function getHistoryUrl( Title $title ): string {
		return ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			   SpecialMobileHistory::shouldUseSpecialHistory( $title, $this->user ) ?
			SpecialPage::getTitleFor( 'History', $title )->getLocalURL() :
			$title->getLocalURL( [ 'action' => 'history' ] );
	}

	/**
	 * Prepares a url to the Special:UserLogin with query parameters
	 * @param array $query
	 * @return string
	 */
	private function getLoginUrl( $query ): string {
		return $this->loginTitle->getLocalURL( $query );
	}
}
