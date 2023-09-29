<?php

namespace PageImages;

use ApiBase;
use ApiMain;
use FauxRequest;
use File;
use IContextSource;
use MapCacheLRU;
use MediaWiki\Api\Hook\ApiOpenSearchSuggestHook;
use MediaWiki\Cache\CacheKeyHelper;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserOptionsLookup;
use OutputPage;
use Skin;
use Title;

/**
 * @license WTFPL
 * @author Max Semenik
 * @author Brad Jorsch
 * @author Thiemo Kreuz
 */
class PageImages implements
	ApiOpenSearchSuggestHook,
	BeforePageDisplayHook,
	InfoActionHook
{
	/**
	 * @const value for free images
	 */
	public const LICENSE_FREE = 'free';

	/**
	 * @const value for images with any type of license
	 */
	public const LICENSE_ANY = 'any';

	/**
	 * Page property used to store the best page image information.
	 * If the best image is the same as the best image with free license,
	 * then nothing is stored under this property.
	 * Note changing this value is not advised as it will invalidate all
	 * existing page property names on a production instance
	 * and cause them to be regenerated.
	 * @see PageImages::PROP_NAME_FREE
	 */
	public const PROP_NAME = 'page_image';

	/**
	 * Page property used to store the best free page image information
	 * Note changing this value is not advised as it will invalidate all
	 * existing page property names on a production instance
	 * and cause them to be regenerated.
	 */
	public const PROP_NAME_FREE = 'page_image_free';

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var MapCacheLRU */
	private static $cache = null;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

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
	 * Get property names used in page_props table
	 *
	 * If the license is free, then only the free property name will be returned,
	 * otherwise both free and non-free property names will be returned. That's
	 * because we save the image name only once if it's free and the best image.
	 *
	 * @param string $license either LICENSE_FREE or LICENSE_ANY,
	 * specifying whether to return the non-free property name or not
	 * @return string|array
	 */
	public static function getPropNames( $license ) {
		if ( $license === self::LICENSE_FREE ) {
			return self::getPropName( true );
		}
		return [ self::getPropName( true ), self::getPropName( false ) ];
	}

	/**
	 * Return page image for a given title
	 *
	 * @param Title $title Title to get page image for
	 * @return File|bool
	 */
	public static function getPageImage( Title $title ) {
		self::$cache ??= new MapCacheLRU( 100 );

		$file = self::$cache->getWithSetCallback(
			CacheKeyHelper::getKeyForPage( $title ),
			fn() => self::fetchPageImage( $title )
		);

		// Cast any cacheable null to false
		return $file ?? false;
	}

	/**
	 * @param Title $title Title to get page image for
	 * @return File|null|bool
	 */
	private static function fetchPageImage( Title $title ) {
		if ( !$title->canExist() ) {
			// Optimization: Do not query for special pages or other titles never in the database
			return false;
		}

		if ( $title->inNamespace( NS_FILE ) ) {
			return MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		}

		$pageId = $title->getArticleID();
		if ( !$pageId ) {
			// No page id to select from
			// Allow caching, cast null to false later
			return null;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$fileName = $dbr->selectField( 'page_props',
			'pp_value',
			[
				'pp_page' => $pageId,
				'pp_propname' => [ self::PROP_NAME, self::PROP_NAME_FREE ]
			],
			__METHOD__,
			[ 'ORDER BY' => 'pp_propname' ]
		);
		if ( !$fileName ) {
			// Allow caching, cast null to false later
			return false;
		}

		return MediaWikiServices::getInstance()->getRepoGroup()->findFile( $fileName );
	}

	/**
	 * InfoAction hook handler, adds the page image to the info=action page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 *
	 * @param IContextSource $context Context, used to extract the title of the page
	 * @param array[] &$pageInfo Auxillary information about the page.
	 */
	public function onInfoAction( $context, &$pageInfo ) {
		global $wgThumbLimits;

		$imageFile = self::getPageImage( $context->getTitle() );
		if ( !$imageFile ) {
			// The page has no image
			return;
		}

		$thumbSetting = $this->userOptionsLookup->getOption( $context->getUser(), 'thumbsize' );
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
	public function onApiOpenSearchSuggest( &$results ) {
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
	 * @param OutputPage $out The page being output.
	 * @param Skin $skin Skin object used to generate the page. Ignored
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$out->getConfig()->get( 'PageImagesOpenGraph' ) ) {
			return;
		}
		$imageFile = self::getPageImage( $out->getContext()->getTitle() );
		if ( !$imageFile ) {
			$fallback = $out->getConfig()->get( 'PageImagesOpenGraphFallbackImage' );
			if ( $fallback ) {
				$out->addMeta( 'og:image', wfExpandUrl( $fallback, PROTO_CANONICAL ) );
			}
			return;
		}

		// Open Graph protocol -- https://ogp.me/
		// Multiple images are supported according to https://ogp.me/#array
		// See https://developers.facebook.com/docs/sharing/best-practices?locale=en_US#images
		// See T282065: WhatsApp expects an image <300kB
		foreach ( [ 1200, 800, 640 ] as $width ) {
			$thumb = $imageFile->transform( [ 'width' => $width ] );
			if ( !$thumb ) {
				continue;
			}
			$out->addMeta( 'og:image', wfExpandUrl( $thumb->getUrl(), PROTO_CANONICAL ) );
			$out->addMeta( 'og:image:width', strval( $thumb->getWidth() ) );
			$out->addMeta( 'og:image:height', strval( $thumb->getHeight() ) );
		}
	}

}
