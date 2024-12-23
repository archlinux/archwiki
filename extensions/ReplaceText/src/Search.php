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
 * https://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
namespace MediaWiki\Extension\ReplaceText;

use MediaWiki\Config\Config;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class Search {
	private Config $config;
	private IConnectionProvider $loadBalancer;

	public function __construct(
		Config $config,
		IConnectionProvider $loadBalancer
	) {
		$this->config = $config;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param string $search
	 * @param array $namespaces
	 * @param string|null $category
	 * @param string|null $prefix
	 * @param int|null $pageLimit
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public function doSearchQuery(
		$search, $namespaces, $category, $prefix, $pageLimit, $use_regex = false
	) {
		$dbr = $this->loadBalancer->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title', 'old_text', 'slot_role_id' ] )
			->from( 'page' )
			->join( 'revision', null, 'rev_id = page_latest' )
			->join( 'slots', null, 'rev_id = slot_revision_id' )
			->join( 'content', null, 'slot_content_id = content_id' )
			->join( 'text', null, $dbr->buildIntegerCast( 'SUBSTR(content_address, 4)' ) . ' = old_id' );
		if ( $use_regex ) {
			$queryBuilder->where( self::regexCond( $dbr, 'old_text', $search ) );
		} else {
			$any = $dbr->anyString();
			$queryBuilder->where( $dbr->expr( 'old_text', IExpression::LIKE, new LikeValue( $any, $search, $any ) ) );
		}
		$queryBuilder->andWhere( [ 'page_namespace' => $namespaces ] );
		if ( $pageLimit === null || $pageLimit === '' ) {
			$pageLimit = $this->config->get( 'ReplaceTextResultsLimit' );
		}
		self::categoryCondition( $category, $queryBuilder );
		$this->prefixCondition( $prefix, $dbr, $queryBuilder );
		return $queryBuilder->orderBy( [ 'page_namespace', 'page_title' ] )
			->limit( $pageLimit )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @param string|null $category
	 * @param SelectQueryBuilder $queryBuilder
	 */
	public static function categoryCondition( $category, SelectQueryBuilder $queryBuilder ) {
		if ( strval( $category ) !== '' ) {
			$category = Title::newFromText( $category )->getDbKey();
			$queryBuilder->join( 'categorylinks', null, 'page_id = cl_from' )
				->where( [ 'cl_to' => $category ] );
		}
	}

	/**
	 * @param string|null $prefix
	 * @param IReadableDatabase $dbr
	 * @param SelectQueryBuilder $queryBuilder
	 */
	private function prefixCondition( $prefix, IReadableDatabase $dbr, SelectQueryBuilder $queryBuilder ) {
		if ( strval( $prefix ) === '' ) {
			return;
		}

		$title = Title::newFromText( $prefix );
		if ( $title !== null ) {
			$prefix = $title->getDbKey();
		}
		$any = $dbr->anyString();
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable $prefix is checked for null
		$queryBuilder->where( $dbr->expr( 'page_title', IExpression::LIKE, new LikeValue( $prefix, $any ) ) );
	}

	/**
	 * @param IReadableDatabase $dbr
	 * @param string $column
	 * @param string $regex
	 * @return string query condition for regex
	 */
	public static function regexCond( $dbr, $column, $regex ) {
		if ( $dbr->getType() == 'postgres' ) {
			$cond = "$column ~ ";
		} else {
			$cond = "CAST($column AS BINARY) REGEXP BINARY ";
		}
		$cond .= $dbr->addQuotes( $regex );
		return $cond;
	}

	/**
	 * @param string $str
	 * @param array $namespaces
	 * @param string|null $category
	 * @param string|null $prefix
	 * @param int|null $pageLimit
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public function getMatchingTitles(
		$str,
		$namespaces,
		$category,
		$prefix,
		$pageLimit,
		$use_regex = false
	) {
		$dbr = $this->loadBalancer->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace' ] )
			->from( 'page' );
		$str = str_replace( ' ', '_', $str );
		if ( $use_regex ) {
			$queryBuilder->where( self::regexCond( $dbr, 'page_title', $str ) );
		} else {
			$any = $dbr->anyString();
			$queryBuilder->where( $dbr->expr( 'page_title', IExpression::LIKE, new LikeValue( $any, $str, $any ) ) );
		}
		$queryBuilder->andWhere( [ 'page_namespace' => $namespaces ] );
		if ( $pageLimit === null || $pageLimit === '' ) {
			$pageLimit = $this->config->get( 'ReplaceTextResultsLimit' );
		}
		self::categoryCondition( $category, $queryBuilder );
		$this->prefixCondition( $prefix, $dbr, $queryBuilder );
		return $queryBuilder->orderBy( [ 'page_namespace', 'page_title' ] )
			->limit( $pageLimit )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * Do a replacement on a string.
	 * @param string $text
	 * @param string $search
	 * @param string $replacement
	 * @param bool $regex
	 * @return string
	 */
	public static function getReplacedText( $text, $search, $replacement, $regex ) {
		if ( $regex ) {
			$escapedSearch = addcslashes( $search, '/' );
			return preg_replace( "/$escapedSearch/Uu", $replacement, $text );
		} else {
			return str_replace( $search, $replacement, $text );
		}
	}

	/**
	 * Do a replacement on a title.
	 * @param Title $title
	 * @param string $search
	 * @param string $replacement
	 * @param bool $regex
	 * @return Title|null
	 */
	public static function getReplacedTitle( Title $title, $search, $replacement, $regex ) {
		$oldTitleText = $title->getText();
		$newTitleText = self::getReplacedText( $oldTitleText, $search, $replacement, $regex );
		return Title::makeTitleSafe( $title->getNamespace(), $newTitleText );
	}
}
