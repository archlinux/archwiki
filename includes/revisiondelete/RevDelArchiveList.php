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
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

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
	 * @param IDatabase $db
	 * @return mixed
	 */
	public function doQuery( $db ) {
		$timestamps = [];
		foreach ( $this->ids as $id ) {
			$timestamps[] = $db->timestamp( $id );
		}

		$arQuery = $this->revisionStore->getArchiveQueryInfo();
		$tables = $arQuery['tables'];
		$fields = $arQuery['fields'];
		$conds = [
			'ar_namespace' => $this->getPage()->getNamespace(),
			'ar_title' => $this->getPage()->getDBkey(),
			'ar_timestamp' => $timestamps,
		];
		$join_conds = $arQuery['joins'];
		$options = [ 'ORDER BY' => 'ar_timestamp DESC' ];

		ChangeTags::modifyDisplayQuery(
			$tables,
			$fields,
			$conds,
			$join_conds,
			$options,
			''
		);

		return $db->select( $tables,
			$fields,
			$conds,
			__METHOD__,
			$options,
			$join_conds
		);
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
