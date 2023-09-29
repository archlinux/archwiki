<?php
/**
 * Copyright © 2007 Roan Kattouw "<Firstname>.<Lastname>@gmail.com"
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

use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Query module to enumerate all categories, even the ones that don't have
 * category pages.
 *
 * @ingroup API
 */
class ApiQueryAllCategories extends ApiQueryGeneratorBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ac' );
	}

	public function execute() {
		$this->run();
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 */
	private function run( $resultPageSet = null ) {
		$db = $this->getDB();
		$params = $this->extractRequestParams();

		$this->addTables( 'category' );
		$this->addFields( 'cat_title' );

		if ( $params['continue'] !== null ) {
			$cont = $this->parseContinueParamOrDie( $params['continue'], [ 'string' ] );
			$op = $params['dir'] == 'descending' ? '<=' : '>=';
			$this->addWhere( $db->buildComparison( $op, [ 'cat_title' => $cont[0] ] ) );
		}

		$dir = ( $params['dir'] == 'descending' ? 'older' : 'newer' );
		$from = ( $params['from'] === null
			? null
			: $this->titlePartToKey( $params['from'], NS_CATEGORY ) );
		$to = ( $params['to'] === null
			? null
			: $this->titlePartToKey( $params['to'], NS_CATEGORY ) );
		$this->addWhereRange( 'cat_title', $dir, $from, $to );

		$min = $params['min'];
		$max = $params['max'];
		if ( $dir == 'newer' ) {
			$this->addWhereRange( 'cat_pages', 'newer', $min, $max );
		} else {
			$this->addWhereRange( 'cat_pages', 'older', $max, $min );
		}

		if ( isset( $params['prefix'] ) ) {
			$this->addWhere( 'cat_title' . $db->buildLike(
				$this->titlePartToKey( $params['prefix'], NS_CATEGORY ),
				$db->anyString() ) );
		}

		$this->addOption( 'LIMIT', $params['limit'] + 1 );
		$sort = ( $params['dir'] == 'descending' ? ' DESC' : '' );
		$this->addOption( 'ORDER BY', 'cat_title' . $sort );

		$prop = array_fill_keys( $params['prop'], true );
		$this->addFieldsIf( [ 'cat_pages', 'cat_subcats', 'cat_files' ], isset( $prop['size'] ) );
		if ( isset( $prop['hidden'] ) ) {
			$this->addTables( [ 'page', 'page_props' ] );
			$this->addJoinConds( [
				'page' => [ 'LEFT JOIN', [
					'page_namespace' => NS_CATEGORY,
					'page_title=cat_title' ] ],
				'page_props' => [ 'LEFT JOIN', [
					'pp_page=page_id',
					'pp_propname' => 'hiddencat' ] ],
			] );
			$this->addFields( [ 'cat_hidden' => 'pp_propname' ] );
		}

		$res = $this->select( __METHOD__ );

		$pages = [];

		$result = $this->getResult();
		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've reached the one extra which shows that there are
				// additional cats to be had. Stop here...
				$this->setContinueEnumParameter( 'continue', $row->cat_title );
				break;
			}

			// Normalize titles
			$titleObj = Title::makeTitle( NS_CATEGORY, $row->cat_title );
			if ( $resultPageSet !== null ) {
				$pages[] = $titleObj;
			} else {
				$item = [];
				ApiResult::setContentValue( $item, 'category', $titleObj->getText() );
				if ( isset( $prop['size'] ) ) {
					$item['size'] = (int)$row->cat_pages;
					$item['pages'] = $row->cat_pages - $row->cat_subcats - $row->cat_files;
					$item['files'] = (int)$row->cat_files;
					$item['subcats'] = (int)$row->cat_subcats;
				}
				if ( isset( $prop['hidden'] ) ) {
					$item['hidden'] = (bool)$row->cat_hidden;
				}
				$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $item );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', $row->cat_title );
					break;
				}
			}
		}

		if ( $resultPageSet === null ) {
			$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'c' );
		} else {
			$resultPageSet->populateFromTitles( $pages );
		}
	}

	public function getAllowedParams() {
		return [
			'from' => null,
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'to' => null,
			'prefix' => null,
			'dir' => [
				ParamValidator::PARAM_DEFAULT => 'ascending',
				ParamValidator::PARAM_TYPE => [
					'ascending',
					'descending'
				],
			],
			'min' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'max' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'prop' => [
				ParamValidator::PARAM_TYPE => [ 'size', 'hidden' ],
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=allcategories&acprop=size'
				=> 'apihelp-query+allcategories-example-size',
			'action=query&generator=allcategories&gacprefix=List&prop=info'
				=> 'apihelp-query+allcategories-example-generator',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Allcategories';
	}
}
