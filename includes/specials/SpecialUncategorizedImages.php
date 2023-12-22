<?php
/**
 * Implements Special:Uncategorizedimages
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
 * @ingroup SpecialPage
 * @author Rob Church <robchur@gmail.com>
 */

namespace MediaWiki\Specials;

use MediaWiki\SpecialPage\ImageQueryPage;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Special page lists images which haven't been categorised
 *
 * @ingroup SpecialPage
 * @todo FIXME: Use an instance of UncategorizedPagesPage or something
 */
class SpecialUncategorizedImages extends ImageQueryPage {

	/**
	 * @param IConnectionProvider $dbProvider
	 */
	public function __construct( IConnectionProvider $dbProvider ) {
		parent::__construct( 'Uncategorizedimages' );
		$this->setDatabaseProvider( $dbProvider );
	}

	protected function sortDescending() {
		return false;
	}

	public function isExpensive() {
		return true;
	}

	public function isSyndicated() {
		return false;
	}

	protected function getOrderFields() {
		return [ 'title' ];
	}

	public function execute( $par ) {
		$this->addHelpLink( 'Help:Categories' );
		parent::execute( $par );
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'page', 'categorylinks' ],
			'fields' => [
				'namespace' => 'page_namespace',
				'title' => 'page_title',
			],
			'conds' => [
				'cl_from' => null,
				'page_namespace' => NS_FILE,
				'page_is_redirect' => 0,
			],
			'join_conds' => [
				'categorylinks' => [
					'LEFT JOIN',
					'cl_from=page_id',
				],
			],
		];
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialUncategorizedImages::class, 'SpecialUncategorizedImages' );
