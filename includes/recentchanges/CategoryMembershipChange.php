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

use MediaWiki\Cache\BacklinkCache;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Helper class for category membership changes
 *
 * @since 1.27
 * @ingroup RecentChanges
 * @author Kai Nissen
 * @author Addshore
 */
class CategoryMembershipChange {

	private const CATEGORY_ADDITION = 1;
	private const CATEGORY_REMOVAL = -1;

	/**
	 * @var string Current timestamp, set during CategoryMembershipChange::__construct()
	 */
	private $timestamp;

	/**
	 * @var Title Title instance of the categorized page
	 */
	private $pageTitle;

	/**
	 * @var RevisionRecord|null Latest revision of the categorized page
	 */
	private $revision;

	/** @var bool Whether this was caused by an import */
	private $forImport;

	/**
	 * @var int
	 * Number of pages this WikiPage is embedded by
	 * Set by CategoryMembershipChange::checkTemplateLinks()
	 */
	private $numTemplateLinks = 0;

	/**
	 * @var callable|null
	 */
	private $newForCategorizationCallback = null;

	/** @var BacklinkCache */
	private $backlinkCache;

	/**
	 * @param Title $pageTitle Title instance of the categorized page
	 * @param BacklinkCache $backlinkCache
	 * @param RevisionRecord|null $revision Latest revision of the categorized page.
	 * @param bool $forImport Whether this was caused by a import
	 */
	public function __construct(
		Title $pageTitle, BacklinkCache $backlinkCache, ?RevisionRecord $revision = null, bool $forImport = false
	) {
		// TODO: Update callers of this method to pass for import
		$this->pageTitle = $pageTitle;
		$this->revision = $revision;

		// Use the current timestamp for creating the RC entry when dealing with imported revisions,
		// since their timestamp may be significantly older than the current time.
		// This ensures the resulting RC entry won't be immediately reaped by probabilistic RC purging if
		// the imported revision is older than $wgRCMaxAge (T377392).
		if ( $revision === null || $forImport ) {
			$this->timestamp = wfTimestampNow();
		} else {
			$this->timestamp = $revision->getTimestamp();
		}
		$this->newForCategorizationCallback = [ RecentChange::class, 'newForCategorization' ];
		$this->backlinkCache = $backlinkCache;
		$this->forImport = $forImport;
	}

	/**
	 * Overrides the default new for categorization callback
	 * This is intended for use while testing and will fail if MW_PHPUNIT_TEST is not defined.
	 *
	 * @param callable $callback
	 * @see RecentChange::newForCategorization for callback signiture
	 */
	public function overrideNewForCategorizationCallback( callable $callback ) {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new LogicException( 'Cannot override newForCategorization callback in operation.' );
		}
		$this->newForCategorizationCallback = $callback;
	}

	/**
	 * Determines the number of template links for recursive link updates
	 */
	public function checkTemplateLinks() {
		$this->numTemplateLinks = $this->backlinkCache->getNumLinks( 'templatelinks' );
	}

	/**
	 * Create a recentchanges entry for category additions
	 *
	 * @param PageIdentity $categoryPage
	 */
	public function triggerCategoryAddedNotification( PageIdentity $categoryPage ) {
		$this->createRecentChangesEntry( $categoryPage, self::CATEGORY_ADDITION );
	}

	/**
	 * Create a recentchanges entry for category removals
	 *
	 * @param PageIdentity $categoryPage
	 */
	public function triggerCategoryRemovedNotification( PageIdentity $categoryPage ) {
		$this->createRecentChangesEntry( $categoryPage, self::CATEGORY_REMOVAL );
	}

	/**
	 * Create a recentchanges entry using RecentChange::notifyCategorization()
	 *
	 * @param PageIdentity $categoryPage
	 * @param int $type
	 */
	private function createRecentChangesEntry( PageIdentity $categoryPage, $type ) {
		$this->notifyCategorization(
			$this->timestamp,
			$categoryPage,
			$this->getUser(),
			$this->getChangeMessageText(
				$type,
				$this->pageTitle->getPrefixedText(),
				$this->numTemplateLinks
			),
			$this->pageTitle,
			$this->getPreviousRevisionTimestamp(),
			$this->revision,
			$this->forImport,
			$type === self::CATEGORY_ADDITION
		);
	}

	/**
	 * @param string $timestamp Timestamp of the recent change to occur in TS_MW format
	 * @param PageIdentity $categoryPage Page of the category a page is being added to or removed from
	 * @param UserIdentity|null $user User object of the user that made the change
	 * @param string $comment Change summary
	 * @param PageIdentity $page Page that is being added or removed
	 * @param string $lastTimestamp Parent revision timestamp of this change in TS_MW format
	 * @param RevisionRecord|null $revision
	 * @param bool $forImport Whether the associated revision was imported
	 * @param bool $added true, if the category was added, false for removed
	 */
	private function notifyCategorization(
		$timestamp,
		PageIdentity $categoryPage,
		?UserIdentity $user,
		$comment,
		PageIdentity $page,
		$lastTimestamp,
		$revision,
		bool $forImport,
		$added
	) {
		$deleted = $revision ? $revision->getVisibility() & RevisionRecord::SUPPRESSED_USER : 0;
		$newRevId = $revision ? $revision->getId() : 0;

		/**
		 * T109700 - Default bot flag to true when there is no corresponding RC entry
		 * This means all changes caused by parser functions & Lua on reparse are marked as bot
		 * Also in the case no RC entry could be found due to replica DB lag
		 */
		$bot = 1;
		$lastRevId = 0;
		$ip = '';

		# If no revision is given, the change was probably triggered by parser functions
		if ( $revision !== null ) {
			$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

			$correspondingRc = $revisionStore->getRecentChange( $this->revision ) ??
				$revisionStore->getRecentChange( $this->revision, IDBAccessObject::READ_LATEST );
			if ( $correspondingRc !== null ) {
				$bot = $correspondingRc->getAttribute( 'rc_bot' ) ?: 0;
				$ip = $correspondingRc->getAttribute( 'rc_ip' ) ?: '';
				$lastRevId = $correspondingRc->getAttribute( 'rc_last_oldid' ) ?: 0;
			}
		}

		/** @var RecentChange $rc */
		$rc = ( $this->newForCategorizationCallback )(
			$timestamp,
			$categoryPage,
			$user,
			$comment,
			$page,
			$lastRevId,
			$newRevId,
			$lastTimestamp,
			$bot,
			$ip,
			$deleted,
			$added,
			$forImport
		);
		$rc->save();
	}

	/**
	 * Get the user associated with this change.
	 *
	 * If there is no revision associated with the change and thus no editing user
	 * fallback to a default.
	 *
	 * False will be returned if the user name specified in the
	 * 'autochange-username' message is invalid.
	 *
	 * @return UserIdentity|null
	 */
	private function getUser(): ?UserIdentity {
		if ( $this->revision ) {
			$user = $this->revision->getUser( RevisionRecord::RAW );
			if ( $user ) {
				return $user;
			}
		}

		$username = wfMessage( 'autochange-username' )->inContentLanguage()->text();

		$user = User::newSystemUser( $username );
		if ( $user && !$user->isRegistered() ) {
			$user->addToDatabase();
		}

		return $user ?: null;
	}

	/**
	 * Returns the change message according to the type of category membership change
	 *
	 * The message keys created in this method may be one of:
	 * - recentchanges-page-added-to-category
	 * - recentchanges-page-added-to-category-bundled
	 * - recentchanges-page-removed-from-category
	 * - recentchanges-page-removed-from-category-bundled
	 *
	 * @param int $type may be CategoryMembershipChange::CATEGORY_ADDITION
	 * or CategoryMembershipChange::CATEGORY_REMOVAL
	 * @param string $prefixedText result of Title::->getPrefixedText()
	 * @param int $numTemplateLinks
	 *
	 * @return string
	 */
	private function getChangeMessageText( $type, $prefixedText, $numTemplateLinks ) {
		$array = [
			self::CATEGORY_ADDITION => 'recentchanges-page-added-to-category',
			self::CATEGORY_REMOVAL => 'recentchanges-page-removed-from-category',
		];

		$msgKey = $array[$type];

		if ( intval( $numTemplateLinks ) > 0 ) {
			$msgKey .= '-bundled';
		}

		return wfMessage( $msgKey, $prefixedText )->inContentLanguage()->text();
	}

	/**
	 * Returns the timestamp of the page's previous revision or null if the latest revision
	 * does not refer to a parent revision
	 *
	 * @return null|string
	 */
	private function getPreviousRevisionTimestamp() {
		$rl = MediaWikiServices::getInstance()->getRevisionLookup();
		$latestRev = $rl->getRevisionByTitle( $this->pageTitle );
		if ( $latestRev ) {
			$previousRev = $rl->getPreviousRevision( $latestRev );
			if ( $previousRev ) {
				return $previousRev->getTimestamp();
			}
		}
		return null;
	}

}
