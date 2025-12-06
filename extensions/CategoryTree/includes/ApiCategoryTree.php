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
 */

namespace MediaWiki\Extension\CategoryTree;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Json\FormatJson;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;

class ApiCategoryTree extends ApiBase {
	public function __construct(
		ApiMain $main,
		string $action,
		private readonly CategoryTreeFactory $categoryTreeFactory,
		private readonly IConnectionProvider $dbProvider,
		private readonly LanguageConverterFactory $languageConverterFactory,
		private readonly WANObjectCache $wanCache,
	) {
		parent::__construct( $main, $action );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): void {
		$params = $this->extractRequestParams();

		$options = $this->extractOptions( $params );

		$title = CategoryTree::makeTitle( $params['category'] );
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['category'] ) ] );
		}

		$depth = isset( $options['depth'] ) ? (int)$options['depth'] : 1;

		$ct = $this->categoryTreeFactory->newCategoryTree( $options );
		$depth = $ct->optionManager->capDepth( $depth );
		$html = $this->getHTML( $ct, $title, $depth );

		$this->getMain()->setCacheMode( 'public' );

		$this->getResult()->addContentValue( $this->getModuleName(), 'html', $html );
	}

	/**
	 * @param array $params
	 * @return string[]
	 */
	private function extractOptions( array $params ): array {
		if ( !isset( $params['options'] ) ) {
			return [];
		}

		$options = FormatJson::decode( $params['options'] );
		if ( !is_object( $options ) ) {
			$this->dieWithError( 'apierror-categorytree-invalidjson', 'invalidjson' );
		}

		foreach ( $options as $option => $value ) {
			if ( is_scalar( $value ) || $value === null ) {
				continue;
			}
			if ( $option === 'namespaces' && is_array( $value ) ) {
				continue;
			}
			$this->dieWithError(
				[ 'apierror-categorytree-invalidjson-option', $option ], 'invalidjson-option'
			);
		}

		return get_object_vars( $options );
	}

	/** @inheritDoc */
	public function getConditionalRequestData( $condition ) {
		if ( $condition === 'last-modified' ) {
			$params = $this->extractRequestParams();
			$title = CategoryTree::makeTitle( $params['category'] );
			return $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
				->select( 'page_touched' )
				->from( 'page' )
				->where( [
					'page_namespace' => NS_CATEGORY,
					'page_title' => $title->getDBkey(),
				] )
				->caller( __METHOD__ )
				->fetchField();
		}
		return null;
	}

	/**
	 * Get category tree HTML for the given tree, title and depth
	 *
	 * @param CategoryTree $ct
	 * @param Title $title
	 * @param int $depth
	 * @return string HTML
	 */
	private function getHTML( CategoryTree $ct, Title $title, int $depth ): string {
		$langConv = $this->languageConverterFactory->getLanguageConverter();

		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey(
				'categorytree-html-ajax',
				md5( $title->getDBkey() ),
				md5( $ct->optionManager->getOptionsAsCacheKey( $depth ) ),
				$this->getLanguage()->getCode(),
				$langConv->getExtraHashOptions(),
				$this->getConfig()->get( MainConfigNames::RenderHashAppend )
			),
			$this->wanCache::TTL_DAY,
			static fn () => $ct->renderChildren( $title, $depth ),
			[
				'touchedCallback' => function () {
					$timestamp = $this->getConditionalRequestData( 'last-modified' );

					return $timestamp ? wfTimestamp( TS_UNIX, $timestamp ) : null;
				}
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams(): array {
		return [
			'category' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'options' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function isInternal(): bool {
		return true;
	}
}
