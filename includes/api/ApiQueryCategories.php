<?php
/**
 * Copyright © 2006 Yuri Astrakhan "<Firstname><Lastname>@gmail.com"
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
 * A query module to enumerate categories the set of pages belong to.
 *
 * @ingroup API
 */
class ApiQueryCategories extends ApiQueryGeneratorBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cl' );
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
		$pages = $this->getPageSet()->getGoodPages();
		if ( $pages === [] ) {
			return; // nothing to do
		}

		$params = $this->extractRequestParams();
		$prop = array_fill_keys( (array)$params['prop'], true );
		$show = array_fill_keys( (array)$params['show'], true );

		$this->addFields( [
			'cl_from',
			'cl_to'
		] );

		$this->addFieldsIf( [ 'cl_sortkey', 'cl_sortkey_prefix' ], isset( $prop['sortkey'] ) );
		$this->addFieldsIf( 'cl_timestamp', isset( $prop['timestamp'] ) );

		$this->addTables( 'categorylinks' );
		$this->addWhereFld( 'cl_from', array_keys( $pages ) );
		if ( $params['categories'] ) {
			$cats = [];
			foreach ( $params['categories'] as $cat ) {
				$title = Title::newFromText( $cat );
				if ( !$title || $title->getNamespace() !== NS_CATEGORY ) {
					$this->addWarning( [ 'apiwarn-invalidcategory', wfEscapeWikiText( $cat ) ] );
				} else {
					$cats[] = $title->getDBkey();
				}
			}
			if ( !$cats ) {
				// No titles so no results
				return;
			}
			$this->addWhereFld( 'cl_to', $cats );
		}

		if ( $params['continue'] !== null ) {
			$db = $this->getDB();
			$cont = $this->parseContinueParamOrDie( $params['continue'], [ 'int', 'string' ] );
			$op = $params['dir'] == 'descending' ? '<=' : '>=';
			$this->addWhere( $db->buildComparison( $op, [
				'cl_from' => $cont[0],
				'cl_to' => $cont[1],
			] ) );
		}

		if ( isset( $show['hidden'] ) && isset( $show['!hidden'] ) ) {
			$this->dieWithError( 'apierror-show' );
		}
		if ( isset( $show['hidden'] ) || isset( $show['!hidden'] ) || isset( $prop['hidden'] ) ) {
			$this->addOption( 'STRAIGHT_JOIN' );
			$this->addTables( [ 'page', 'page_props' ] );
			$this->addFieldsIf( 'pp_propname', isset( $prop['hidden'] ) );
			$this->addJoinConds( [
				'page' => [ 'LEFT JOIN', [
					'page_namespace' => NS_CATEGORY,
					'page_title = cl_to' ] ],
				'page_props' => [ 'LEFT JOIN', [
					'pp_page=page_id',
					'pp_propname' => 'hiddencat' ] ]
			] );
			if ( isset( $show['hidden'] ) ) {
				$this->addWhere( [ 'pp_propname IS NOT NULL' ] );
			} elseif ( isset( $show['!hidden'] ) ) {
				$this->addWhere( [ 'pp_propname IS NULL' ] );
			}
		}

		$sort = ( $params['dir'] == 'descending' ? ' DESC' : '' );
		// Don't order by cl_from if it's constant in the WHERE clause
		if ( count( $pages ) === 1 ) {
			$this->addOption( 'ORDER BY', 'cl_to' . $sort );
		} else {
			$this->addOption( 'ORDER BY', [
				'cl_from' . $sort,
				'cl_to' . $sort
			] );
		}
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$res = $this->select( __METHOD__ );

		$count = 0;
		if ( $resultPageSet === null ) {
			foreach ( $res as $row ) {
				if ( ++$count > $params['limit'] ) {
					// We've reached the one extra which shows that
					// there are additional pages to be had. Stop here...
					$this->setContinueEnumParameter( 'continue', $row->cl_from . '|' . $row->cl_to );
					break;
				}

				$title = Title::makeTitle( NS_CATEGORY, $row->cl_to );
				$vals = [];
				ApiQueryBase::addTitleInfo( $vals, $title );
				if ( isset( $prop['sortkey'] ) ) {
					$vals['sortkey'] = bin2hex( $row->cl_sortkey );
					$vals['sortkeyprefix'] = $row->cl_sortkey_prefix;
				}
				if ( isset( $prop['timestamp'] ) ) {
					$vals['timestamp'] = wfTimestamp( TS_ISO_8601, $row->cl_timestamp );
				}
				if ( isset( $prop['hidden'] ) ) {
					$vals['hidden'] = $row->pp_propname !== null;
				}

				$fit = $this->addPageSubItem( $row->cl_from, $vals );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', $row->cl_from . '|' . $row->cl_to );
					break;
				}
			}
		} else {
			$titles = [];
			foreach ( $res as $row ) {
				if ( ++$count > $params['limit'] ) {
					// We've reached the one extra which shows that
					// there are additional pages to be had. Stop here...
					$this->setContinueEnumParameter( 'continue', $row->cl_from . '|' . $row->cl_to );
					break;
				}

				$titles[] = Title::makeTitle( NS_CATEGORY, $row->cl_to );
			}
			$resultPageSet->populateFromTitles( $titles );
		}
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'sortkey',
					'timestamp',
					'hidden',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'show' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'hidden',
					'!hidden',
				]
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'categories' => [
				ParamValidator::PARAM_ISMULTI => true,
			],
			'dir' => [
				ParamValidator::PARAM_DEFAULT => 'ascending',
				ParamValidator::PARAM_TYPE => [
					'ascending',
					'descending'
				]
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&prop=categories&titles=Albert%20Einstein'
				=> 'apihelp-query+categories-example-simple',
			'action=query&generator=categories&titles=Albert%20Einstein&prop=info'
				=> 'apihelp-query+categories-example-generator',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Categories';
	}
}
