<?php

namespace MediaWiki\Extension\CategoryTree;

use ApiBase;
use ApiMain;
use Config;
use ConfigFactory;
use FormatJson;
use MediaWiki\Languages\LanguageConverterFactory;
use Title;
use WANObjectCache;

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

class ApiCategoryTree extends ApiBase {
	/** @var ConfigFactory */
	private $configFactory;

	/** @var LanguageConverterFactory */
	private $languageConverterFactory;

	/** @var WANObjectCache */
	private $wanCache;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param ConfigFactory $configFactory
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param WANObjectCache $wanCache
	 */
	public function __construct(
		ApiMain $main,
		$action,
		ConfigFactory $configFactory,
		LanguageConverterFactory $languageConverterFactory,
		WANObjectCache $wanCache
	) {
		parent::__construct( $main, $action );
		$this->configFactory = $configFactory;
		$this->languageConverterFactory = $languageConverterFactory;
		$this->wanCache = $wanCache;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$options = [];
		if ( isset( $params['options'] ) ) {
			$options = FormatJson::decode( $params['options'] );
			if ( !is_object( $options ) ) {
				$this->dieWithError( 'apierror-categorytree-invalidjson', 'invalidjson' );
			}
			$options = get_object_vars( $options );
		}

		$title = CategoryTree::makeTitle( $params['category'] );
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['category'] ) ] );
		}

		$depth = isset( $options['depth'] ) ? (int)$options['depth'] : 1;

		$ct = new CategoryTree( $options );
		$depth = CategoryTree::capDepth( $ct->getOption( 'mode' ), $depth );
		$ctConfig = $this->configFactory->makeConfig( 'categorytree' );
		$html = $this->getHTML( $ct, $title, $depth, $ctConfig );

		$this->getMain()->setCacheMode( 'public' );

		$this->getResult()->addContentValue( $this->getModuleName(), 'html', $html );
	}

	/**
	 * @param string $condition
	 *
	 * @return bool|null|string
	 */
	public function getConditionalRequestData( $condition ) {
		if ( $condition === 'last-modified' ) {
			$params = $this->extractRequestParams();
			$title = CategoryTree::makeTitle( $params['category'] );
			return wfGetDB( DB_REPLICA )->selectField( 'page', 'page_touched',
				[
					'page_namespace' => NS_CATEGORY,
					'page_title' => $title->getDBkey(),
				],
				__METHOD__
			);
		}
	}

	/**
	 * Get category tree HTML for the given tree, title, depth and config
	 *
	 * @param CategoryTree $ct
	 * @param Title $title
	 * @param int $depth
	 * @param Config $ctConfig Config for CategoryTree
	 * @return string HTML
	 */
	private function getHTML( CategoryTree $ct, Title $title, $depth, Config $ctConfig ) {
		$langConv = $this->languageConverterFactory->getLanguageConverter();

		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey(
				'categorytree-html-ajax',
				md5( $title->getDBkey() ),
				md5( $ct->getOptionsAsCacheKey( $depth ) ),
				$this->getLanguage()->getCode(),
				$langConv->getExtraHashOptions(),
				$ctConfig->get( 'RenderHashAppend' )
			),
			$this->wanCache::TTL_DAY,
			static function () use ( $ct, $title, $depth ) {
				return trim( $ct->renderChildren( $title, $depth ) );
			},
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
	public function getAllowedParams() {
		return [
			'category' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'options' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function isInternal() {
		return true;
	}
}
