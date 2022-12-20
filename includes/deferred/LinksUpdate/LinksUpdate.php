<?php
/**
 * Updater for link tracking tables after a page edit.
 *
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

namespace MediaWiki\Deferred\LinksUpdate;

use AutoCommitUpdate;
use BacklinkCache;
use DataUpdate;
use DeferredUpdates;
use DeprecationHelper;
use Job;
use MediaWiki\HookContainer\ProtectedHookAccessorTrait;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MWException;
use ParserOutput;
use RefreshLinksJob;
use RuntimeException;
use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\ScopedCallback;

/**
 * Class the manages updates of *_link tables as well as similar extension-managed tables
 *
 * @note: LinksUpdate is managed by DeferredUpdates::execute(). Do not run this in a transaction.
 *
 * See docs/deferred.txt
 */
class LinksUpdate extends DataUpdate {
	use ProtectedHookAccessorTrait;
	use DeprecationHelper;

	// @todo make members protected, but make sure extensions don't break

	/** @var int Page ID of the article linked from */
	protected $mId;

	/** @var Title Title object of the article linked from */
	protected $mTitle;

	/** @var ParserOutput */
	protected $mParserOutput;

	/** @var bool Whether to queue jobs for recursive updates */
	protected $mRecursive;

	/** @var RevisionRecord Revision for which this update has been triggered */
	private $mRevisionRecord;

	/**
	 * @var UserIdentity|null
	 */
	private $user;

	/** @var IDatabase */
	private $db;

	/** @var LinksTableGroup */
	private $tableFactory;

	/**
	 * @param PageIdentity $page The page we're updating
	 * @param ParserOutput $parserOutput Output from a full parse of this page
	 * @param bool $recursive Queue jobs for recursive updates?
	 *
	 * @throws MWException
	 */
	public function __construct( PageIdentity $page, ParserOutput $parserOutput, $recursive = true ) {
		parent::__construct();

		// @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty castFrom does not return null here
		$this->mTitle = Title::castFromPageIdentity( $page );
		$this->mParserOutput = $parserOutput;

		$this->deprecatePublicProperty( 'mId', '1.38', __CLASS__ );
		$this->deprecatePublicProperty( 'mTitle', '1.38', __CLASS__ );
		$this->deprecatePublicProperty( 'mParserOutput', '1.38', __CLASS__ );

		$this->deprecatePublicPropertyFallback( 'mLinks', '1.38',
			function () {
				return $this->getParserOutput()->getLinks();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mImages', '1.38',
			function () {
				return $this->getParserOutput()->getImages();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mTemplates', '1.38',
			function () {
				return $this->getParserOutput()->getTemplates();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mExternals', '1.38',
			function () {
				return $this->getParserOutput()->getExternalLinks();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mCategories', '1.38',
			function () {
				return $this->getParserOutput()->getCategories();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mProperties', '1.38',
			function () {
				return $this->getParserOutput()->getPageProperties();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mInterwikis', '1.38',
			function () {
				return $this->getParserOutput()->getInterwikiLinks();
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mInterlangs', '1.38',
			function () {
				$ill = $this->getParserOutput()->getLanguageLinks();
				$res = [];
				foreach ( $ill as $link ) {
					list( $key, $title ) = explode( ':', $link, 2 );
					$res[$key] = $title;
				}
				return $res;
			},
			null, __CLASS__
		);
		$this->deprecatePublicPropertyFallback( 'mCategories', '1.38',
			function () {
				$cats = $this->getParserOutput()->getCategories();
				foreach ( $cats as &$sortkey ) {
					# If the sortkey is longer then 255 bytes, it is truncated by DB, and then doesn't match
					# when comparing existing vs current categories, causing T27254.
					$sortkey = mb_strcut( $sortkey, 0, 255 );
				}
			},
			null, __CLASS__
		);

		$this->mRecursive = $recursive;
		$this->deprecatePublicProperty( 'mRecursive', '1.38', __CLASS__ );

		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$this->tableFactory = new LinksTableGroup(
			$services->getObjectFactory(),
			$services->getDBLoadBalancerFactory(),
			$services->getCollationFactory(),
			$page,
			$services->getLinkTargetLookup(),
			$config->get( MainConfigNames::UpdateRowsPerQuery ),
			function ( $table, $rows ) {
				$this->getHookRunner()->onLinksUpdateAfterInsert( $this, $table, $rows );
			},
			$config->get( MainConfigNames::TempCategoryCollations )
		);
		// TODO: this does not have to be called in LinksDeletionUpdate
		$this->tableFactory->setParserOutput( $parserOutput );

		$this->getHookRunner()->onLinksUpdateConstructed( $this );
	}

	public function setTransactionTicket( $ticket ) {
		parent::setTransactionTicket( $ticket );
		$this->tableFactory->setTransactionTicket( $ticket );
	}

	/**
	 * Notify LinksUpdate that a move has just been completed and set the
	 * original title
	 *
	 * @param PageReference $oldPage
	 */
	public function setMoveDetails( PageReference $oldPage ) {
		$this->tableFactory->setMoveDetails( $oldPage );
	}

	/**
	 * Update link tables with outgoing links from an updated article
	 *
	 * @note this is managed by DeferredUpdates::execute(). Do not run this in a transaction.
	 */
	public function doUpdate() {
		if ( !$this->mId ) {
			// NOTE: subclasses may initialize mId directly!
			$this->mId = $this->mTitle->getArticleID( Title::READ_LATEST );
		}

		if ( !$this->mId ) {
			// Probably due to concurrent deletion or renaming of the page
			$logger = LoggerFactory::getInstance( 'SecondaryDataUpdate' );
			$logger->notice(
				'LinksUpdate: The Title object yields no ID. Perhaps the page was deleted?',
				[
					'page_title' => $this->mTitle->getPrefixedDBkey(),
					'cause_action' => $this->getCauseAction(),
					'cause_agent' => $this->getCauseAgent()
				]
			);

			// nothing to do
			return;
		}

		// Do any setup that needs to be done prior to acquiring the lock
		// Calling getAll() here has the side-effect of calling
		// LinksUpdateBatch::setParserOutput() on all subclasses, allowing
		// those methods to also do pre-lock operations.
		foreach ( $this->tableFactory->getAll() as $table ) {
			$table->beforeLock();
		}

		if ( $this->ticket ) {
			// Make sure all links update threads see the changes of each other.
			// This handles the case when updates have to batched into several COMMITs.
			$scopedLock = self::acquirePageLock( $this->getDB(), $this->mId );
			if ( !$scopedLock ) {
				throw new RuntimeException( "Could not acquire lock for page ID '{$this->mId}'." );
			}
		}

		$this->getHookRunner()->onLinksUpdate( $this );
		$this->doIncrementalUpdate();

		// Commit and release the lock (if set)
		ScopedCallback::consume( $scopedLock );
		// Run post-commit hook handlers without DBO_TRX
		DeferredUpdates::addUpdate( new AutoCommitUpdate(
			$this->getDB(),
			__METHOD__,
			function () {
				$this->getHookRunner()->onLinksUpdateComplete( $this, $this->ticket );
			}
		) );
	}

	/**
	 * Acquire a session-level lock for performing link table updates for a page on a DB
	 *
	 * @param IDatabase $dbw
	 * @param int $pageId
	 * @param string $why One of (job, atomicity)
	 * @return ScopedCallback|null
	 * @since 1.27
	 */
	public static function acquirePageLock( IDatabase $dbw, $pageId, $why = 'atomicity' ) {
		$key = "{$dbw->getDomainID()}:LinksUpdate:$why:pageid:$pageId"; // per-wiki
		$scopedLock = $dbw->getScopedLockAndFlush( $key, __METHOD__, 15 );
		if ( !$scopedLock ) {
			$logger = LoggerFactory::getInstance( 'SecondaryDataUpdate' );
			$logger->info( "Could not acquire lock '{key}' for page ID '{page_id}'.", [
				'key' => $key,
				'page_id' => $pageId,
			] );
			return null;
		}

		return $scopedLock;
	}

	protected function doIncrementalUpdate() {
		foreach ( $this->tableFactory->getAll() as $table ) {
			$table->update();
		}

		# Refresh links of all pages including this page
		# This will be in a separate transaction
		if ( $this->mRecursive ) {
			$this->queueRecursiveJobs();
		}

		# Update the links table freshness for this title
		$this->updateLinksTimestamp();
	}

	/**
	 * Queue recursive jobs for this page
	 *
	 * Which means do LinksUpdate on all pages that include the current page,
	 * using the job queue.
	 */
	protected function queueRecursiveJobs() {
		$services = MediaWikiServices::getInstance();
		$backlinkCache = $services->getBacklinkCacheFactory()
			->getBacklinkCache( $this->mTitle );
		$action = $this->getCauseAction();
		$agent = $this->getCauseAgent();

		self::queueRecursiveJobsForTable(
			$this->mTitle, 'templatelinks', $action, $agent, $backlinkCache
		);
		if ( $this->mTitle->getNamespace() === NS_FILE ) {
			// Process imagelinks in case the title is or was a redirect
			self::queueRecursiveJobsForTable(
				$this->mTitle, 'imagelinks', $action, $agent, $backlinkCache
			);
		}

		// Get jobs for cascade-protected backlinks for a high priority queue.
		// If meta-templates change to using a new template, the new template
		// should be implicitly protected as soon as possible, if applicable.
		// These jobs duplicate a subset of the above ones, but can run sooner.
		// Which ever runs first generally no-ops the other one.
		$jobs = [];
		foreach ( $backlinkCache->getCascadeProtectedLinkPages() as $page ) {
			$jobs[] = RefreshLinksJob::newPrioritized(
				$page,
				[
					'causeAction' => $action,
					'causeAgent' => $agent
				]
			);
		}
		$services->getJobQueueGroup()->push( $jobs );
	}

	/**
	 * Queue a RefreshLinks job for any table.
	 *
	 * @param PageIdentity $page Page to do job for
	 * @param string $table Table to use (e.g. 'templatelinks')
	 * @param string $action Triggering action
	 * @param string $userName Triggering user name
	 * @param BacklinkCache|null $backlinkCache
	 */
	public static function queueRecursiveJobsForTable(
		PageIdentity $page, $table, $action = 'unknown', $userName = 'unknown', ?BacklinkCache $backlinkCache = null
	) {
		$title = Title::castFromPageIdentity( $page );
		if ( !$backlinkCache ) {
			wfDeprecatedMsg( __METHOD__ . " needs a BacklinkCache object, null passed", '1.37' );
			$backlinkCache = MediaWikiServices::getInstance()->getBacklinkCacheFactory()
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable castFrom does not return null here
				->getBacklinkCache( $title );
		}
		if ( $backlinkCache->hasLinks( $table ) ) {
			$job = new RefreshLinksJob(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable castFrom does not return null here
				$title,
				[
					'table' => $table,
					'recursive' => true,
				] + Job::newRootJobParams( // "overall" refresh links job info
					"refreshlinks:{$table}:{$title->getPrefixedText()}"
				) + [ 'causeAction' => $action, 'causeAgent' => $userName ]
			);

			MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
		}
	}

	/**
	 * Omit conflict resolution options from the insert query so that testing
	 * can confirm that the incremental update logic was correct.
	 *
	 * @param bool $mode
	 */
	public function setStrictTestMode( $mode = true ) {
		$this->tableFactory->setStrictTestMode( $mode );
	}

	/**
	 * Return the title object of the page being updated
	 * @return Title
	 */
	public function getTitle() {
		return $this->mTitle;
	}

	/**
	 * Get the page_id of the page being updated
	 *
	 * @since 1.38
	 * @return int
	 */
	public function getPageId() {
		if ( $this->mId ) {
			return $this->mId;
		} else {
			return $this->mTitle->getArticleID();
		}
	}

	/**
	 * Returns parser output
	 * @since 1.19
	 * @return ParserOutput
	 */
	public function getParserOutput() {
		return $this->mParserOutput;
	}

	/**
	 * Return the list of images used as generated by the parser
	 * @return array
	 */
	public function getImages() {
		return $this->getParserOutput()->getImages();
	}

	/**
	 * Set the RevisionRecord corresponding to this LinksUpdate
	 *
	 * @since 1.35
	 * @param RevisionRecord $revisionRecord
	 */
	public function setRevisionRecord( RevisionRecord $revisionRecord ) {
		$this->mRevisionRecord = $revisionRecord;
		$this->tableFactory->setRevision( $revisionRecord );
	}

	/**
	 * @since 1.35
	 * @return RevisionRecord|null
	 */
	public function getRevisionRecord() {
		return $this->mRevisionRecord;
	}

	/**
	 * Set the user who triggered this LinksUpdate
	 *
	 * @since 1.27
	 * @param UserIdentity $user
	 */
	public function setTriggeringUser( UserIdentity $user ) {
		$this->user = $user;
	}

	/**
	 * Get the user who triggered this LinksUpdate
	 *
	 * @since 1.27
	 * @return UserIdentity|null
	 */
	public function getTriggeringUser(): ?UserIdentity {
		return $this->user;
	}

	/**
	 * @return PageLinksTable
	 */
	protected function getPageLinksTable(): PageLinksTable {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return $this->tableFactory->get( 'pagelinks' );
	}

	/**
	 * @return ExternalLinksTable
	 */
	protected function getExternalLinksTable(): ExternalLinksTable {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return $this->tableFactory->get( 'externallinks' );
	}

	/**
	 * @return PagePropsTable
	 */
	protected function getPagePropsTable(): PagePropsTable {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return $this->tableFactory->get( 'page_props' );
	}

	/**
	 * Fetch page links added by this LinksUpdate.  Only available after the update is complete.
	 *
	 * @since 1.22
	 * @deprecated since 1.38 use getPageReferenceIterator() or getPageReferenceArray()
	 * @return Title[] Array of Titles
	 */
	public function getAddedLinks() {
		return $this->getPageLinksTable()->getTitleArray( LinksTable::INSERTED );
	}

	/**
	 * Fetch page links removed by this LinksUpdate.  Only available after the update is complete.
	 *
	 * @since 1.22
	 * @deprecated since 1.38 use getPageReferenceIterator() or getPageReferenceArray()
	 * @return Title[] Array of Titles
	 */
	public function getRemovedLinks() {
		return $this->getPageLinksTable()->getTitleArray( LinksTable::DELETED );
	}

	/**
	 * Fetch external links added by this LinksUpdate. Only available after
	 * the update is complete.
	 * @since 1.33
	 * @return null|array Array of Strings
	 */
	public function getAddedExternalLinks() {
		return $this->getExternalLinksTable()->getStringArray( LinksTable::INSERTED );
	}

	/**
	 * Fetch external links removed by this LinksUpdate. Only available after
	 * the update is complete.
	 * @since 1.33
	 * @return null|string[]
	 */
	public function getRemovedExternalLinks() {
		return $this->getExternalLinksTable()->getStringArray( LinksTable::DELETED );
	}

	/**
	 * Fetch page properties added by this LinksUpdate.
	 * Only available after the update is complete.
	 * @since 1.28
	 * @return null|array
	 */
	public function getAddedProperties() {
		return $this->getPagePropsTable()->getAssocArray( LinksTable::INSERTED );
	}

	/**
	 * Fetch page properties removed by this LinksUpdate.
	 * Only available after the update is complete.
	 * @since 1.28
	 * @return null|array
	 */
	public function getRemovedProperties() {
		return $this->getPagePropsTable()->getAssocArray( LinksTable::DELETED );
	}

	/**
	 * Get an iterator over PageReferenceValue objects corresponding to a given set
	 * type in a given table.
	 *
	 * @since 1.38
	 * @param string $tableName The name of any table that links to local titles
	 * @param int $setType One of:
	 *    - LinksTable::INSERTED: The inserted links
	 *    - LinksTable::DELETED: The deleted links
	 *    - LinksTable::CHANGED: Both the inserted and deleted links
	 *    - LinksTable::OLD: The old set of links, loaded before the update
	 *    - LinksTable::NEW: The new set of links from the ParserOutput
	 * @return iterable<PageReferenceValue>
	 * @phan-return \Traversable
	 */
	public function getPageReferenceIterator( $tableName, $setType ) {
		$table = $this->tableFactory->get( $tableName );
		if ( $table instanceof TitleLinksTable ) {
			return $table->getPageReferenceIterator( $setType );
		} else {
			throw new \InvalidArgumentException(
				__METHOD__ . ": $tableName does not have a list of titles" );
		}
	}

	/**
	 * Same as getPageReferenceIterator() but converted to an array for convenience
	 * (at the expense of additional time and memory usage)
	 *
	 * @since 1.38
	 * @param string $tableName
	 * @param int $setType
	 * @return PageReferenceValue[]
	 */
	public function getPageReferenceArray( $tableName, $setType ) {
		return iterator_to_array( $this->getPageReferenceIterator( $tableName, $setType ) );
	}

	/**
	 * Update links table freshness
	 */
	protected function updateLinksTimestamp() {
		if ( $this->mId ) {
			// The link updates made here only reflect the freshness of the parser output
			$timestamp = $this->mParserOutput->getCacheTime();
			$this->getDB()->update( 'page',
				[ 'page_links_updated' => $this->getDB()->timestamp( $timestamp ) ],
				[ 'page_id' => $this->mId ],
				__METHOD__
			);
		}
	}

	/**
	 * @return IDatabase
	 */
	protected function getDB() {
		if ( !$this->db ) {
			$this->db = wfGetDB( DB_PRIMARY );
		}

		return $this->db;
	}

	/**
	 * Whether or not this LinksUpdate will also update pages which transclude the
	 * current page or otherwise depend on it.
	 *
	 * @return bool
	 */
	public function isRecursive() {
		return $this->mRecursive;
	}
}

/** @deprecated since 1.38 */
class_alias( LinksUpdate::class, 'LinksUpdate' );
