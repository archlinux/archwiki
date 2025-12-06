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
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class Search {
	public function __construct(
		private readonly Config $config,
		private readonly IConnectionProvider $loadBalancer,
	) {
	}

	/**
	 * @param string $search
	 * @param array $namespaces
	 * @param string|null $category
	 * @param string|null $prefix
	 * @param int $pageLimit
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public function doSearchQuery(
		string $search,
		array $namespaces,
		?string $category,
		?string $prefix,
		int $pageLimit,
		bool $use_regex = false
	): IResultWrapper {
		$dbr = $this->loadBalancer->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title', 'old_text', 'slot_role_id' ] )
			->from( 'page' )
			->join( 'revision', null, 'rev_id = page_latest' )
			->join( 'slots', null, 'rev_id = slot_revision_id' )
			->join( 'content', null, 'slot_content_id = content_id' )
			->join( 'text', null, $dbr->buildIntegerCast( 'SUBSTR(content_address, 4)' ) . ' = old_id' );
		if ( $use_regex ) {
			$queryBuilder->where( $this->regexCond( 'old_text', $search ) );
		} else {
			$any = $dbr->anyString();
			$queryBuilder->where( $dbr->expr( 'old_text', IExpression::LIKE, new LikeValue( $any, $search, $any ) ) );
		}
		$queryBuilder->andWhere( [ 'page_namespace' => $namespaces ] );
		self::categoryCondition( $category, $queryBuilder );
		$this->prefixCondition( $prefix, $queryBuilder );
		return $queryBuilder->orderBy( [ 'page_namespace', 'page_title' ] )
			->limit( $pageLimit )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	private static function categoryCondition( ?string $category, SelectQueryBuilder $queryBuilder ) {
		if ( $category !== null && $category !== '' ) {
			$category = Title::newFromText( $category )->getDbKey();
			$queryBuilder->join( 'categorylinks', null, 'page_id = cl_from' )
				->join( 'linktarget', null, 'cl_target_id = lt_id' )
				->where( [ 'lt_title' => $category ] );
		}
	}

	private function prefixCondition(
		?string $prefix,
		SelectQueryBuilder $queryBuilder
	): void {
		if ( $prefix === null || $prefix === '' ) {
			return;
		}

		$title = Title::newFromText( $prefix );
		if ( $title !== null ) {
			$prefix = $title->getDbKey();
		}
		$dbr = $this->loadBalancer->getReplicaDatabase();
		$any = $dbr->anyString();
		$queryBuilder->where( $dbr->expr( 'page_title', IExpression::LIKE, new LikeValue( $prefix, $any ) ) );
	}

	/**
	 * @param string $column
	 * @param string $regex
	 * @return string query condition for regex
	 */
	private function regexCond( string $column, string $regex ): string {
		$dbr = $this->loadBalancer->getReplicaDatabase();
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
	 * @param int $pageLimit
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public function getMatchingTitles(
		string $str,
		array $namespaces,
		?string $category,
		?string $prefix,
		int $pageLimit,
		bool $use_regex = false
	): IResultWrapper {
		$dbr = $this->loadBalancer->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace' ] )
			->from( 'page' );
		$str = str_replace( ' ', '_', $str );
		if ( $use_regex ) {
			$queryBuilder->where( $this->regexCond( 'page_title', $str ) );
		} else {
			$any = $dbr->anyString();
			$queryBuilder->where( $dbr->expr( 'page_title', IExpression::LIKE, new LikeValue( $any, $str, $any ) ) );
		}
		$queryBuilder->andWhere( [ 'page_namespace' => $namespaces ] );
		self::categoryCondition( $category, $queryBuilder );
		$this->prefixCondition( $prefix, $queryBuilder );
		return $queryBuilder->orderBy( [ 'page_namespace', 'page_title' ] )
			->limit( $pageLimit )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * Do a replacement on a string.
	 */
	public static function getReplacedText(
		string $text,
		string $search,
		string $replacement,
		bool $regex
	): string {
		if ( $regex ) {
			$escapedSearch = addcslashes( $search, '/' );
			return preg_replace( "/$escapedSearch/Uu", $replacement, $text );
		} else {
			return str_replace( $search, $replacement, $text );
		}
	}

	/**
	 * Do a replacement on a title.
	 */
	public static function getReplacedTitle(
		Title $title,
		string $search,
		string $replacement,
		bool $regex
	): ?Title {
		$oldTitleText = $title->getText();
		$newTitleText = self::getReplacedText( $oldTitleText, $search, $replacement, $regex );
		return Title::makeTitleSafe( $title->getNamespace(), $newTitleText );
	}
}
