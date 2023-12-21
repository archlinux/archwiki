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
 * @ingroup RevisionDelete
 */

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Status\Status;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * List for archive table items, i.e. revisions deleted via action=delete
 */
class RevDelArchiveList extends RevDelRevisionList {

	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @param IContextSource $context
	 * @param PageIdentity $page
	 * @param array $ids
	 * @param LBFactory $lbFactory
	 * @param HookContainer $hookContainer
	 * @param HtmlCacheUpdater $htmlCacheUpdater
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		IContextSource $context,
		PageIdentity $page,
		array $ids,
		LBFactory $lbFactory,
		HookContainer $hookContainer,
		HtmlCacheUpdater $htmlCacheUpdater,
		RevisionStore $revisionStore
	) {
		parent::__construct(
			$context,
			$page,
			$ids,
			$lbFactory,
			$hookContainer,
			$htmlCacheUpdater,
			$revisionStore
		);
		$this->revisionStore = $revisionStore;
	}

	public function getType() {
		return 'archive';
	}

	public static function getRelationType() {
		return 'ar_timestamp';
	}

	/**
	 * @param \Wikimedia\Rdbms\IReadableDatabase $db
	 * @return IResultWrapper
	 */
	public function doQuery( $db ) {
		$timestamps = [];
		foreach ( $this->ids as $id ) {
			$timestamps[] = $db->timestamp( $id );
		}

		$queryBuilder = $this->revisionStore->newArchiveSelectQueryBuilder( $db )
			->joinComment()
			->where( [
				'ar_namespace' => $this->getPage()->getNamespace(),
				'ar_title' => $this->getPage()->getDBkey(),
				'ar_timestamp' => $timestamps,
			] )
			->orderBy( 'ar_timestamp', SelectQueryBuilder::SORT_DESC );

		MediaWikiServices::getInstance()->getChangeTagsStore()->modifyDisplayQueryBuilder( $queryBuilder, 'archive' );

		return $queryBuilder->caller( __METHOD__ )->fetchResultSet();
	}

	public function newItem( $row ) {
		return new RevDelArchiveItem( $this, $row );
	}

	public function doPreCommitUpdates() {
		return Status::newGood();
	}

	public function doPostCommitUpdates( array $visibilityChangeMap ) {
		return Status::newGood();
	}
}
