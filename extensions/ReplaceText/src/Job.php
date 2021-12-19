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
use MediaWiki\Revision\SlotRecord;
use MovePage;
use RequestContext;
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
			$mvPage = new MovePage( $this->title, $new_title );
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
					WatchAction::doWatch( $new_title, $current_user );
				}

			}
		} else {
			if ( $this->title->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
				$this->error = 'replaceText: Wiki page "' .
					$this->title->getPrefixedDBkey() . '" does not hold regular wikitext.';
				return false;
			}
			$wikiPage = new WikiPage( $this->title );
			$wikiPageContent = $wikiPage->getContent();
			if ( $wikiPageContent === null ) {
				$this->error =
					'replaceText: No contents found for wiki page at "' . $this->title->getPrefixedDBkey() . '."';
				return false;
			}
			$article_text = $wikiPageContent->getNativeData();

			$target_str = $this->params['target_str'];
			$replacement_str = $this->params['replacement_str'];
			$num_matches = 0;

			if ( $this->params['use_regex'] ) {
				$new_text =
					preg_replace( '/' . $target_str . '/Uu', $replacement_str, $article_text, -1, $num_matches );
			} else {
				$new_text = str_replace( $target_str, $replacement_str, $article_text, $num_matches );
			}

			// If there's at least one replacement, modify the page,
			// using the passed-in edit summary.
			if ( $num_matches > 0 ) {
				$updater = $wikiPage->newPageUpdater( $current_user );
				$updater->setContent( SlotRecord::MAIN, new WikitextContent( $new_text ) );
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
				$updater->saveRevision( $edit_summary, $flags );
			}
		}
		return true;
	}
}
