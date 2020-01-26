<?php

/**
 * @license WTFPL
 * @author Max Semenik
 * @author Brad Jorsch
 * @author Thiemo Kreuz
 */
class PageImages {

	/**
	 * Page property used to store the best page image information.
	 * If the best image is the same as the best image with free license,
	 * then nothing is stored under this property.
	 * Note changing this value is not advised as it will invalidate all
	 * existing page property names on a production instance
	 * and cause them to be regenerated.
	 * @see PageImages::PROP_NAME_FREE
	 */
	const PROP_NAME = 'page_image';

	/**
	 * Page property used to store the best free page image information
	 * Note changing this value is not advised as it will invalidate all
	 * existing page property names on a production instance
	 * and cause them to be regenerated.
	 */
	const PROP_NAME_FREE = 'page_image_free';

	/**
	 * Get property name used in page_props table. When a page image
	 * is stored it will be stored under this property name on the corresponding
	 * article.
	 *
	 * @param bool $isFree Whether the image is a free-license image
	 * @return string
	 */
	public static function getPropName( $isFree ) {
		return $isFree ? self::PROP_NAME_FREE : self::PROP_NAME;
	}

	/**
	 * Returns page image for a given title
	 *
	 * @param Title $title Title to get page image for
	 *
	 * @return File|bool
	 */
	public static function getPageImage( Title $title ) {
		if ( $title->inNamespace( NS_FILE ) ) {
			return wfFindFile( $title );
		}

		$dbr = wfGetDB( DB_REPLICA );
		$fileName = $dbr->selectField( 'page_props',
			'pp_value',
			[
				'pp_page' => $title->getArticleID(),
				'pp_propname' => [ self::PROP_NAME, self::PROP_NAME_FREE ]
			],
			__METHOD__,
			[ 'ORDER BY' => 'pp_propname' ]
		);

		$file = false;
		if ( $fileName ) {
			$file = wfFindFile( $fileName );
		}

		return $file;
	}

	/**
	 * InfoAction hook handler, adds the page image to the info=action page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 *
	 * @param IContextSource $context Context, used to extract the title of the page
	 * @param array[] &$pageInfo Auxillary information about the page.
	 */
	public static function onInfoAction( IContextSource $context, &$pageInfo ) {
		global $wgThumbLimits;

		$imageFile = self::getPageImage( $context->getTitle() );
		if ( !$imageFile ) {
			// The page has no image
			return;
		}

		$thumbSetting = $context->getUser()->getOption( 'thumbsize' );
		$thumbSize = $wgThumbLimits[$thumbSetting];

		$thumb = $imageFile->transform( [ 'width' => $thumbSize ] );
		if ( !$thumb ) {
			return;
		}
		$imageHtml = $thumb->toHtml(
			[
				'alt' => $imageFile->getTitle()->getText(),
				'desc-link' => true,
			]
		);

		$pageInfo['header-basic'][] = [
			$context->msg( 'pageimages-info-label' ),
			$imageHtml
		];
	}

	/**
	 * ApiOpenSearchSuggest hook handler, enhances ApiOpenSearch results with this extension's data
	 *
	 * @param array[] &$results Array of results to add page images too
	 */
	public static function onApiOpenSearchSuggest( array &$results ) {
		global $wgPageImagesExpandOpenSearchXml;

		if ( !$wgPageImagesExpandOpenSearchXml || !count( $results ) ) {
			return;
		}

		$pageIds = array_keys( $results );
		$data = self::getImages( $pageIds, 50 );
		foreach ( $pageIds as $id ) {
			if ( isset( $data[$id]['thumbnail'] ) ) {
				$results[$id]['image'] = $data[$id]['thumbnail'];
			} else {
				$results[$id]['image'] = null;
			}
		}
	}

	/**
	 * SpecialMobileEditWatchlist::images hook handler, adds images to mobile watchlist A-Z view
	 *
	 * @param IContextSource $context Context object. Ignored
	 * @param array[] $watchlist Array of relevant pages on the watchlist, sorted by namespace
	 * @param array[] &$images Array of images to populate
	 */
	public static function onSpecialMobileEditWatchlistImages(
		IContextSource $context, array $watchlist, array &$images
	) {
		$ids = [];
		foreach ( $watchlist as $ns => $pages ) {
			foreach ( array_keys( $pages ) as $dbKey ) {
				$title = Title::makeTitle( $ns, $dbKey );
				// Getting page ID here is safe because SpecialEditWatchlist::getWatchlistInfo()
				// uses LinkBatch
				$id = $title->getArticleID();
				if ( $id ) {
					$ids[$id] = $dbKey;
				}
			}
		}

		$data = self::getImages( array_keys( $ids ) );
		foreach ( $data as $id => $page ) {
			if ( isset( $page['pageimage'] ) ) {
				$images[ $page['ns'] ][ $ids[$id] ] = $page['pageimage'];
			}
		}
	}

	/**
	 * Returns image information for pages with given ids
	 *
	 * @param int[] $pageIds
	 * @param int $size
	 *
	 * @return array[]
	 */
	private static function getImages( array $pageIds, $size = 0 ) {
		$ret = [];
		foreach ( array_chunk( $pageIds, ApiBase::LIMIT_SML1 ) as $chunk ) {
			$request = [
				'action' => 'query',
				'prop' => 'pageimages',
				'piprop' => 'name',
				'pageids' => implode( '|', $chunk ),
				'pilimit' => 'max',
			];

			if ( $size ) {
				$request['piprop'] = 'thumbnail';
				$request['pithumbsize'] = $size;
			}

			$api = new ApiMain( new FauxRequest( $request ) );
			$api->execute();

			$ret += (array)$api->getResult()->getResultData(
				[ 'query', 'pages' ], [ 'Strip' => 'base' ]
			);
		}
		return $ret;
	}

	/**
	 * @param OutputPage &$out The page being output.
	 * @param Skin &$skin Skin object used to generate the page. Ignored
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$imageFile = self::getPageImage( $out->getContext()->getTitle() );
		if ( !$imageFile ) {
			return;
		}

		// See https://developers.facebook.com/docs/sharing/best-practices?locale=en_US#tags
		$thumb = $imageFile->transform( [ 'width' => 1200 ] );
		if ( !$thumb ) {
			return;
		}

		$out->addMeta( 'og:image', wfExpandUrl( $thumb->getUrl(), PROTO_CANONICAL ) );
	}

}
