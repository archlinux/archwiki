<?php
/**
 * Copyright © 2010 Roan Kattouw "<Firstname>.<Lastname>@gmail.com"
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

use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPageFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Query module to get the results of a QueryPage-based special page
 *
 * @ingroup API
 */
class ApiQueryQueryPage extends ApiQueryGeneratorBase {

	/**
	 * @var string[] list of special page names
	 */
	private $queryPages;

	/**
	 * @var SpecialPageFactory
	 */
	private $specialPageFactory;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct(
		ApiQuery $query,
		$moduleName,
		SpecialPageFactory $specialPageFactory
	) {
		parent::__construct( $query, $moduleName, 'qp' );
		$this->queryPages = array_values( array_diff(
			array_column( QueryPage::getPages(), 1 ), // [ class, name ]
			$this->getConfig()->get( MainConfigNames::APIUselessQueryPages )
		) );
		$this->specialPageFactory = $specialPageFactory;
	}

	public function execute() {
		$this->run();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param string $name
	 * @return QueryPage
	 */
	private function getSpecialPage( $name ): QueryPage {
		$qp = $this->specialPageFactory->getPage( $name );
		if ( !$qp ) {
			self::dieDebug(
				__METHOD__,
				'SpecialPageFactory failed to create special page ' . $name
			);
		}
		if ( !( $qp instanceof QueryPage ) ) {
			self::dieDebug(
				__METHOD__,
				'Special page ' . $name . ' is not a QueryPage'
			);
		}
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable T240141
		return $qp;
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 */
	public function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();
		$result = $this->getResult();

		$qp = $this->getSpecialPage( $params['page'] );
		if ( !$qp->userCanExecute( $this->getUser() ) ) {
			$this->dieWithError( 'apierror-specialpage-cantexecute' );
		}

		$r = [ 'name' => $params['page'] ];
		if ( $qp->isCached() ) {
			if ( !$qp->isCacheable() ) {
				$r['disabled'] = true;
			} else {
				$r['cached'] = true;
				$ts = $qp->getCachedTimestamp();
				if ( $ts ) {
					$r['cachedtimestamp'] = wfTimestamp( TS_ISO_8601, $ts );
				}
				$r['maxresults'] = $this->getConfig()->get( MainConfigNames::QueryCacheLimit );
			}
		}
		$result->addValue( [ 'query' ], $this->getModuleName(), $r );

		if ( $qp->isCached() && !$qp->isCacheable() ) {
			// Disabled query page, don't run the query
			return;
		}

		$res = $qp->doQuery( $params['offset'], $params['limit'] + 1 );
		$count = 0;
		$titles = [];
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've had enough
				$this->setContinueEnumParameter( 'offset', $params['offset'] + $params['limit'] );
				break;
			}

			$title = Title::makeTitle( $row->namespace, $row->title );
			if ( $resultPageSet === null ) {
				$data = [];
				if ( isset( $row->value ) ) {
					$data['value'] = $row->value;
					if ( $qp->usesTimestamps() ) {
						$data['timestamp'] = wfTimestamp( TS_ISO_8601, $row->value );
					}
				}
				self::addTitleInfo( $data, $title );

				foreach ( $row as $field => $value ) {
					if ( !in_array( $field, [ 'namespace', 'title', 'value', 'qc_type' ] ) ) {
						$data['databaseResult'][$field] = $value;
					}
				}

				$fit = $result->addValue( [ 'query', $this->getModuleName(), 'results' ], null, $data );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'offset', $params['offset'] + $count - 1 );
					break;
				}
			} else {
				$titles[] = $title;
			}
		}
		if ( $resultPageSet === null ) {
			$result->addIndexedTagName(
				[ 'query', $this->getModuleName(), 'results' ],
				'page'
			);
		} else {
			$resultPageSet->populateFromTitles( $titles );
		}
	}

	public function getCacheMode( $params ) {
		$qp = $this->getSpecialPage( $params['page'] );
		if ( $qp->getRestriction() != '' ) {
			return 'private';
		}

		return 'public';
	}

	public function getAllowedParams() {
		return [
			'page' => [
				ParamValidator::PARAM_TYPE => $this->queryPages,
				ParamValidator::PARAM_REQUIRED => true
			],
			'offset' => [
				ParamValidator::PARAM_DEFAULT => 0,
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=querypage&qppage=Ancientpages'
				=> 'apihelp-query+querypage-example-ancientpages',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Querypage';
	}
}
