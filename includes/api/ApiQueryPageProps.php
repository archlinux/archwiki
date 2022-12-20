<?php
/**
 * Copyright © 2010 soxred93, Bryan Tong Minh
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

use Wikimedia\ParamValidator\ParamValidator;

/**
 * A query module to show basic page information.
 *
 * @ingroup API
 */
class ApiQueryPageProps extends ApiQueryBase {

	/** @var PageProps */
	private $pageProps;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param PageProps $pageProps
	 */
	public function __construct(
		ApiQuery $query,
		$moduleName,
		PageProps $pageProps
	) {
		parent::__construct( $query, $moduleName, 'pp' );
		$this->pageProps = $pageProps;
	}

	public function execute() {
		# Only operate on existing pages
		$pages = $this->getPageSet()->getGoodPages();

		$params = $this->extractRequestParams();
		if ( $params['continue'] ) {
			$continueValue = (int)$params['continue'];
			$this->dieContinueUsageIf( strval( $continueValue ) !== $params['continue'] );
			$filteredPages = [];
			foreach ( $pages as $id => $page ) {
				if ( $id >= $continueValue ) {
					$filteredPages[$id] = $page;
				}
			}
			$pages = $filteredPages;
		}

		if ( $pages === [] ) {
			# Nothing to do
			return;
		}

		if ( $params['prop'] ) {
			$properties = $this->pageProps->getProperties( $pages, $params['prop'] );
		} else {
			$properties = $this->pageProps->getAllProperties( $pages );
		}

		ksort( $properties );

		$result = $this->getResult();
		foreach ( $properties as $pageid => $props ) {
			if ( !$this->addPageProps( $result, $pageid, $props ) ) {
				break;
			}
		}
	}

	/**
	 * Add page properties to an ApiResult, adding a continue
	 * parameter if it doesn't fit.
	 *
	 * @param ApiResult $result
	 * @param int $page
	 * @param array $props
	 * @return bool True if it fits in the result
	 */
	private function addPageProps( $result, $page, $props ) {
		ApiResult::setArrayType( $props, 'assoc' );
		$fit = $result->addValue( [ 'query', 'pages', $page ], 'pageprops', $props );

		if ( !$fit ) {
			$this->setContinueEnumParameter( 'continue', $page );
		}

		return $fit;
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return [
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&prop=pageprops&titles=Main%20Page|MediaWiki'
				=> 'apihelp-query+pageprops-example-simple',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Pageprops';
	}
}
