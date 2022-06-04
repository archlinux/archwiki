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
 * @author Yaron Koren
 * @author Ankit Garg
 */
namespace MediaWiki\Extension\ReplaceText;

use CommentStoreComment;
use Job as JobParent;
use MediaWiki\MediaWikiServices;
use RecentChange;
use RequestContext;
use TextContent;
use Title;
use User;
use WatchAction;
use Wikimedia\ScopedCallback;
use WikiPage;
use WikitextContent;

/**
 * Background job to replace text in a given page
 * - based on /includes/RefreshLinksJob.php
 */
class Job extends JobParent {
	/**
	 * Constructor.
	 * @param Title $title
	 * @param array|bool $params Cannot be === true
	 */
	function __construct( $title, $params = [] ) {
		parent::__construct( 'replaceText', $title, $params );
	}

	/**
	 * Run a replaceText job
	 * @return bool success
	 */
	function run() {
		// T279090
		$current_user = User::newFromId( $this->params['user_id'] );
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userCan(
			'replacetext', $current_user, $this->title
		) ) {
			$this->error = 'replacetext: permission no longer valid';
			// T279090#6978214
			return true;
		}

		if ( isset( $this->params['session'] ) ) {
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( static function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}

		if ( $this->title === null ) {
			$this->error = "replaceText: Invalid title";
			return false;
		}

		if ( array_key_exists( 'move_page', $this->params ) ) {
			$new_title = Search::getReplacedTitle(
				$this->title,
				$this->params['target_str'],
				$this->params['replacement_str'],
				$this->params['use_regex']
			);

			if ( $new_title === null ) {
				$this->error = "replaceText: Invalid new title - " . $this->params['replacement_str'];
				return false;
			}

			$reason = $this->params['edit_summary'];
			$create_redirect = $this->params['create_redirect'];
			$mvPage = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage( $this->title, $new_title );
			$mvStatus = $mvPage->move( $current_user, $reason, $create_redirect );
			if ( !$mvStatus->isOK() ) {
				$this->error = "replaceText: error while moving: " . $this->title->getPrefixedDBkey() .
					". Errors: " . $mvStatus->getWikiText();
				return false;
			}

			if ( $this->params['watch_page'] ) {
				if ( method_exists( \MediaWiki\Watchlist\WatchlistManager::class, 'addWatch' ) ) {
					// MW 1.37+
					MediaWikiServices::getInstance()->getWatchlistManager()->addWatch( $current_user, $new_title );
				} else {
					// Method was removed, but we only invoke it in versions its
					// still available, suppress phan error
					// @phan-suppress-next-line PhanUndeclaredStaticMethod
					WatchAction::doWatch( $new_title, $current_user );
				}

			}
		} else {
			$wikiPage = new WikiPage( $this->title );
			$latestRevision = $wikiPage->getRevisionRecord();

			if ( $latestRevision === null ) {
				$this->error =
					'replaceText: No revision found for wiki page at "' . $this->title->getPrefixedDBkey() . '".';
				return false;
			}

			if ( isset( $this->params['roles'] ) ) {
				$slotRoles = $this->params['roles'];
			} else {
				$slotRoles = $latestRevision->getSlotRoles();
			}

			$revisionSlots = $latestRevision->getSlots();
			$updater = $wikiPage->newPageUpdater( $current_user );
			$hasMatches = false;

			foreach ( $slotRoles as $role ) {
				if ( !$revisionSlots->hasSlot( $role ) ) {
					$this->error =
						'replaceText: Slot "' . $role .
						'" does not exist for wiki page "' . $this->title->getPrefixedDBkey() . '".';
					return false;
				}

				$slotContent = $revisionSlots->getContent( $role );

				if ( $slotContent->getModel() !== CONTENT_MODEL_WIKITEXT ) {
					// The slot does not contain wikitext, give an error.
					$this->error =
						'replaceText: Slot "' . $role .
						'" does not hold regular wikitext for wiki page "' . $this->title->getPrefixedDBkey() . '".';
					return false;
				}

				if ( !( $slotContent instanceof TextContent ) ) {
					// Sanity check: Does the slot actually contain TextContent?
					$this->error =
						'replaceText: Slot "' . $role .
						'" does not hold regular wikitext for wiki page "' . $this->title->getPrefixedDBkey() . '".';
					return false;
				}

				$slot_text = $slotContent->getText();

				$target_str = $this->params['target_str'];
				$replacement_str = $this->params['replacement_str'];
				$num_matches = 0;

				if ( $this->params['use_regex'] ) {
					$new_text =
						preg_replace( '/' . $target_str . '/Uu', $replacement_str, $slot_text, -1, $num_matches );
				} else {
					$new_text = str_replace( $target_str, $replacement_str, $slot_text, $num_matches );
				}

				// If there's at least one replacement, modify the slot.
				if ( $num_matches > 0 ) {
					$hasMatches = true;
					$updater->setContent( $role, new WikitextContent( $new_text ) );
				}
			}

			// If at least one slot is edited, modify the page,
			// using the passed-in edit summary.
			if ( $hasMatches ) {
				$edit_summary = CommentStoreComment::newUnsavedComment( $this->params['edit_summary'] );
				$flags = EDIT_MINOR;
				if ( $permissionManager->userHasRight( $current_user, 'bot' ) ) {
					$flags |= EDIT_FORCE_BOT;
				}
				if ( isset( $this->params['doAnnounce'] ) &&
					!$this->params['doAnnounce'] ) {
					$flags |= EDIT_SUPPRESS_RC;
					# fixme log this action
				}
				if ( $permissionManager->userHasRight( $current_user, 'patrol' ) ||
					$permissionManager->userHasRight( $current_user, 'autopatrol' ) ) {
					$updater->setRcPatrolStatus( RecentChange::PRC_PATROLLED );
				}
				$updater->saveRevision( $edit_summary, $flags );
			}
		}
		return true;
	}
}
