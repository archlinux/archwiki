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

namespace MediaWiki\Specials;

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\ImageQueryPage;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * List of unused images
 *
 * @ingroup SpecialPage
 */
class SpecialUnusedImages extends ImageQueryPage {
	private int $migrationStage;

	public function __construct( IConnectionProvider $dbProvider ) {
		parent::__construct( 'Unusedimages' );
		$this->setDatabaseProvider( $dbProvider );
		$this->migrationStage = MediaWikiServices::getInstance()->getMainConfig()->get(
			MainConfigNames::FileSchemaMigrationStage
		);
	}

	public function isExpensive() {
		return true;
	}

	protected function sortDescending() {
		return false;
	}

	public function isSyndicated() {
		return false;
	}

	public function getQueryInfo() {
		if ( $this->migrationStage & SCHEMA_COMPAT_READ_OLD ) {
			$tables = [ 'image' ];
			$nameField = 'img_name';
			$timestampField = 'img_timestamp';
			$extraConds = [];
			$extraJoins = [];
		} else {
			$tables = [ 'file', 'filerevision' ];
			$nameField = 'file_name';
			$timestampField = 'fr_timestamp';
			$extraConds = [ 'file_deleted' => 0 ];
			$extraJoins = [ 'filerevision' => [ 'JOIN', 'file_latest = fr_id' ] ];
		}

		$retval = [
			'tables' => array_merge( $tables, [ 'imagelinks' ] ),
			'fields' => [
				'namespace' => NS_FILE,
				'title' => $nameField,
				'value' => $timestampField,
			],
			'conds' => array_merge( [ 'il_to' => null ], $extraConds ),
			'join_conds' => array_merge(
				[ 'imagelinks' => [ 'LEFT JOIN', 'il_to = ' . $nameField ] ],
				$extraJoins
			),
		];

		if ( $this->getConfig()->get( MainConfigNames::CountCategorizedImagesAsUsed ) ) {
			// Order is significant
			$retval['tables'] = [ 'image', 'page', 'categorylinks',
				'imagelinks' ];
			$retval['conds']['page_namespace'] = NS_FILE;
			$retval['conds']['cl_from'] = null;
			$retval['conds'][] = $nameField . ' = page_title';
			$retval['join_conds']['categorylinks'] = [
				'LEFT JOIN', 'cl_from = page_id' ];
			$retval['join_conds']['imagelinks'] = [
				'LEFT JOIN', 'il_to = page_title' ];
		}

		return $retval;
	}

	public function usesTimestamps() {
		return true;
	}

	protected function getPageHeader() {
		if ( $this->getConfig()->get( MainConfigNames::CountCategorizedImagesAsUsed ) ) {
			return $this->msg(
				'unusedimagestext-categorizedimgisused'
			)->parseAsBlock();
		}
		return $this->msg( 'unusedimagestext' )->parseAsBlock();
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialUnusedImages::class, 'SpecialUnusedImages' );
