<?php

use MediaWiki\MediaWikiServices;

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
		$ctConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'categorytree' );
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
		global $wgMemc;

		$mckey = ObjectCache::getLocalClusterInstance()->makeKey(
			'ajax-categorytree',
			md5( $title->getDBkey() ),
			md5( $ct->getOptionsAsCacheKey( $depth ) ),
			$this->getLanguage()->getCode(),
			MediaWikiServices::getInstance()->getContentLanguage()->getExtraHashOptions(),
			$ctConfig->get( 'RenderHashAppend' )
		);

		$touched = $this->getConditionalRequestData( 'last-modified' );
		if ( $touched ) {
			$mcvalue = $wgMemc->get( $mckey );
			if ( $mcvalue && $touched <= $mcvalue['timestamp'] ) {
				$html = $mcvalue['value'];
			}
		}

		if ( !isset( $html ) ) {
			$html = $ct->renderChildren( $title, $depth );

			$wgMemc->set(
				$mckey,
				[
					'timestamp' => wfTimestampNow(),
					'value' => $html
				],
				86400
			);
		}
		return trim( $html );
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
