<?php
/**
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 * @copyright Copyright © 2013, Mark Holmquist
 */

namespace MediaWiki\Extension\MultimediaViewer;

use MediaWiki\Category\Category;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\Media\Hook\ThumbnailBeforeProduceHTMLHook;
use MediaWiki\Media\ThumbnailImage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\CategoryPage;
use MediaWiki\Page\Hook\CategoryPageViewHook;
use MediaWiki\Page\PageProps;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MobileContext;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMUtils;

class Hooks implements
	MakeGlobalVariablesScriptHook,
	UserGetDefaultOptionsHook,
	GetPreferencesHook,
	BeforePageDisplayHook,
	CategoryPageViewHook,
	ResourceLoaderGetConfigVarsHook,
	GetDoubleUnderscoreIDsHook,
	ThumbnailBeforeProduceHTMLHook
{
	// Minimum number of images in a wiki page to enable the carousel.
	private const MIN_CAROUSEL_IMAGES = 3;
	private const DISABLE_IMAGE_CAROUSEL_PREFERENCE = 'disable_image_carousel';
	private const DISABLE_MOBILE_CAROUSEL_BEHAVIOR_SWITCH = 'nomediaviewercarousel';

	public function __construct(
		private readonly Config $config,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly PageProps $pageProps,
		private readonly ?MobileContext $mobileContext,
	) {
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetDefaultOptions
	 * @param array &$defaultOptions
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		if ( $this->config->get( 'MediaViewerEnableByDefault' ) ) {
			$defaultOptions['multimediaviewer-enable'] = 1;
		}

		$defaultOptions[self::DISABLE_IMAGE_CAROUSEL_PREFERENCE] = 0;
	}

	/**
	 * Checks the context for whether to load the viewer.
	 * @param User $performer
	 * @return bool
	 */
	protected function shouldHandleClicks( User $performer ): bool {
		if ( $this->isMobileFrontendView() &&
			$this->userOptionsLookup->getBoolOption( $performer, self::DISABLE_IMAGE_CAROUSEL_PREFERENCE )
		) {
			return false;
		}

		if ( $performer->isNamed() ) {
			return (bool)$this->userOptionsLookup->getOption( $performer, 'multimediaviewer-enable' );
		}

		return (bool)(
			$this->config->get( 'MediaViewerEnableByDefaultForAnonymous' ) ??
			$this->config->get( 'MediaViewerEnableByDefault' )
		);
	}

	/**
	 * Whether the current request is being served through MobileFrontend's mobile view.
	 *
	 * @return bool
	 */
	protected function isMobileFrontendView(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$this->mobileContext &&
			$this->mobileContext->shouldDisplayMobileView();
	}

	/**
	 * Handler for all places where we add the modules
	 * Could be on article pages or on Category pages
	 * @param OutputPage $out
	 */
	protected function getModules( OutputPage $out ) {
		// The MobileFrontend extension provides its own implementation of MultimediaViewer.
		// See https://phabricator.wikimedia.org/T65504 and subtasks for more details.
		// To avoid loading MMV twice, we check the environment we are running in.
		$user = $out->getUser();
		$isMobileFrontendView = $this->isMobileFrontendView();
		$modules = [];
		if ( $this->shouldUseMobileCarousel( $out ) ) {
			// mmv.carousel depends on mmv.bootstrap, so loading the carousel also loads bootstrap.
			$modules[] = 'mmv.carousel';
			$out->addModuleStyles( 'mmv.carousel.styles' );
		} elseif ( $isMobileFrontendView ) {
			$hasDisabledMobileCarousel = $this->userOptionsLookup->getBoolOption(
				$user,
				self::DISABLE_IMAGE_CAROUSEL_PREFERENCE
			);
			// On mobile without the carousel, only load the bootstrap when the beta
			// flag is set to replace MobileFrontend's viewer. A user opt-out should
			// suppress both the carousel shell and the beta viewer replacement.
			if ( !$hasDisabledMobileCarousel && $out->getRequest()->getFuzzyBool( 'mmvBeta' ) ) {
				$modules[] = 'mmv.bootstrap';
			}
		} else {
			$modules[] = 'mmv.bootstrap';
		}

		if ( $modules ) {
			$out->addModules( $modules );
		}
	}

	/**
	 * Whether the mobile carousel entrypoint should be used for this request.
	 *
	 * The carousel is only available in MobileFrontend mobile view when the
	 * feature flag is enabled, the current user has not opted out in
	 * preferences, and the page has not opted out via
	 * __NOMEDIAVIEWERCAROUSEL__.
	 *
	 * @return bool
	 */
	protected function shouldUseMobileCarousel( OutputPage $out ): bool {
		if ( !$this->isMobileFrontendView() || !$this->config->get( 'MediaViewerMobileCarousel' ) ) {
			return false;
		}

		$user = $out->getUser();
		// Remaining opt-outs are per-user and per-page.
		$hasDisabledCarouselPreference = $this->userOptionsLookup->getBoolOption(
			$user,
			self::DISABLE_IMAGE_CAROUSEL_PREFERENCE
		);
		$pageExcludesCarousel = $this->isPageExcludedFromMobileCarousel( $out );

		return !$hasDisabledCarouselPreference &&
			!$pageExcludesCarousel;
	}

	/**
	 * Whether the current page declares that the mobile carousel should not be shown.
	 *
	 * This is driven by the __NOMEDIAVIEWERCAROUSEL__ behavior switch, which
	 * MediaWiki records as a page property during parse. Checking page props
	 * here lets request-time carousel decisions reuse parser output metadata
	 * without reparsing the page content.
	 *
	 * @param OutputPage $out
	 * @return bool
	 */
	protected function isPageExcludedFromMobileCarousel( OutputPage $out ): bool {
		$title = $out->getTitle();
		if ( !$title->canExist() ) {
			return false;
		}

		return $this->pageProps->getProperties(
			$title,
			self::DISABLE_MOBILE_CAROUSEL_BEHAVIOR_SWITCH
		) !== [];
	}

	/**
	 * The current request skin name, including temporary `?useskin=` overrides.
	 *
	 * @return string
	 */
	protected function getCurrentRequestSkinName(): string {
		return RequestContext::getMain()->getSkin()->getSkinName();
	}

	/**
	 * Extract thumbnail image data from the parser output of a wiki page.
	 * The parser cache is used if possible.
	 *
	 * @param OutputPage $out An output page
	 * @return array[] Each item has keys: title, href, src, srcset, width, height, alt
	 */
	protected function extractImages( OutputPage $out ): array {
		$thumbExtractor = new ThumbExtractor(
			$this->config->get( 'MediaViewerExtensions' ),
			$this->config->get( 'MediaViewerExcludedImageSelectors' )
		);

		$mwServices = MediaWikiServices::getInstance();
		$context = $out->getContext();
		$wikiPage = $context->getWikiPage();
		$parserOptions = ParserOptions::newFromContext( $context );
		$parserOutput = $wikiPage->getParserOutput( $parserOptions );

		if ( $parserOutput ) {
			// In production, extract thumbnails from parser output + file metadata
			$fileNames = [];
			foreach ( $parserOutput->getLinkList( ParserOutputLinkTypes::MEDIA ) as $medium ) {
				$fileNames[] = $medium['link']->getText();
			}
			$files = $mwServices->getRepoGroup()->findFiles( $fileNames );
			$body = $parserOutput->getContentHolder()->getAsDom();

			return $this->mapForCarousel( $thumbExtractor->extract( $body, $files ) );
		} else {
			// In local dev with MobileFrontendContentProvider, extract from HTML directly
			return $this->extractImagesFromHtml( $out->getHTML(), $thumbExtractor );
		}
	}

	/**
	 * Extract thumbnail data directly from HTML, without File objects.
	 *
	 * Used when ParserOutput is unavailable (e.g. MobileFrontendContentProvider
	 * proxied pages). File names are parsed from link hrefs instead of
	 * RepoGroup::findFiles().
	 *
	 * @param string $html Page HTML
	 * @param ThumbExtractor $thumbExtractor
	 * @return array[] Each item has keys: title, href, src, srcset, width, height, alt
	 */
	private function extractImagesFromHtml( string $html, ThumbExtractor $thumbExtractor ): array {
		if ( $html === '' ) {
			return [];
		}

		$doc = DOMCompat::newDocument( true );
		$body = DOMUtils::parseHTMLToFragment( $doc, $html );

		$seen = [];
		$thumbData = [];

		foreach ( $thumbExtractor->findThumbs( $body ) as $thumb ) {
			// Find the parent <a> to get the file name
			$anchor = DOMCompat::getParentElement( $thumb );
			if ( !$anchor || $anchor->nodeName !== 'a' ) {
				continue;
			}
			$href = $anchor->getAttribute( 'href' );

			// Extract file name from href
			// Parsoid: href="./File:Example.jpg"  Legacy: href="/wiki/File:Example.jpg"
			if ( !preg_match( '#(?:^\./|/wiki/)File:(.+)$#', $href, $m ) ) {
				continue;
			}
			$fileName = urldecode( $m[1] );

			// Deduplicate by file name
			if ( isset( $seen[$fileName] ) ) {
				continue;
			}
			$seen[$fileName] = true;

			$thumbData[] = [
				'title' => Title::makeTitleSafe( NS_FILE, $fileName ),
				'thumb' => $thumb,
			];
		}

		// mapForCarousel reads from the thumb Elements, so it must be called
		// while $doc is still in scope (Parsoid DOM frees nodes when their
		// owning document is garbage-collected).
		return $this->mapForCarousel( $thumbData );
	}

	/**
	 * Map extracted thumbnail data to the flat array format used by
	 * {@link buildCarouselItems}.
	 *
	 * @param array<array{title:Title,thumb:Element}> $thumbData
	 * @return array[] Each item has keys: title, href, src, srcset, width, height, alt
	 */
	private function mapForCarousel( array $thumbData ): array {
		$result = [];
		foreach ( $thumbData as $item ) {
			$thumb = $item['thumb'];
			$result[] = [
				'title' => $item['title']->getPrefixedText(),
				'href' => $item['title']->getLocalURL(),
				'src' => $thumb->getAttribute( 'src' )
					?: $thumb->getAttribute( 'data-mw-src' ),
				'srcset' => $thumb->getAttribute( 'srcset' )
					?: $thumb->getAttribute( 'data-mw-srcset' ),
				'width' => $thumb->getAttribute( 'width' ),
				'height' => $thumb->getAttribute( 'height' ),
				'alt' => $thumb->getAttribute( 'alt' ),
			];
		}
		return $result;
	}

	/**
	 * Build the HTML for carousel thumbnail items.
	 *
	 * @param array[] $thumbData Each thumb has keys: title, href, src, srcset, width, height, alt
	 * @return string
	 */
	private function buildCarouselItems( array $thumbData ): string {
		$html = '';
		foreach ( $thumbData as $i => $data ) {
			$html .= Html::rawElement(
				'li',
				[
					'class' => 'mmv-carousel__item',
					'data-mmv-title' => $data['title'],
					'data-mmv-position' => (string)( $i + 1 ),
				],
				Html::rawElement(
					'a',
					[
						'href' => $data['href'],
						'class' => 'mmv-carousel__item-link mw-file-description',
					],
					Html::element(
						'img',
						[
							'src' => $data['src'],
							'srcset' => $data['srcset'] ?: false,
							'width' => $data['width'],
							'height' => $data['height'],
							'alt' => $data['alt'],
							'class' => 'mmv-carousel__item-image',
							'loading' => 'lazy',
						]
					)
				)
			);
		}
		return $html;
	}

	/**
	 * Build the server-rendered carousel shell so the client module can
	 * progressively enhance it.
	 * @param array[] $thumbData carousel thumbnails
	 * @return string
	 */
	private function buildCarouselHtml( array $thumbData ): string {
		$itemsHtml = $this->buildCarouselItems( $thumbData );

		return Html::rawElement(
			'div',
			[
				'id' => 'mmv-carousel-root',
				'class' => 'mw-mmv-wrapper mmv-carousel',
				'data-mmv-carousel' => ' ',
			],
			Html::rawElement(
				'div',
				[ 'class' => 'mmv-carousel__viewport' ],
				Html::rawElement(
					'ul',
					[
						'class' => 'mmv-carousel__items'
					],
					$itemsHtml
				)
			)
		);
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Add JavaScript to the page when an image is on it
	 * and the user has enabled the feature
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$pageIsSpecialPage = $out->getTitle()->inNamespace( NS_SPECIAL );
		$fileRelatedSpecialPages = [ 'Newimages', 'Listfiles', 'Mostimages',
			'MostGloballyLinkedFiles', 'Uncategorizedimages', 'Unusedimages', 'Search' ];
		$pageIsFileRelatedSpecialPage = $out->getTitle()->inNamespace( NS_SPECIAL )
			&& in_array( $this->specialPageFactory->resolveAlias( $out->getTitle()->getDBkey() )[0],
				$fileRelatedSpecialPages );

		if ( !$pageIsSpecialPage || $pageIsFileRelatedSpecialPage ) {
			$this->getModules( $out );
			if ( $this->shouldUseMobileCarousel( $out ) ) {
				$thumbData = $this->extractImages( $out );
				if ( count( $thumbData ) >= self::MIN_CAROUSEL_IMAGES ) {
					$out->prependHTML( $this->buildCarouselHtml( $thumbData ) );
				}
			}
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CategoryPageView
	 * Add JavaScript to the page if there are images in the category
	 * @param CategoryPage $catPage
	 */
	public function onCategoryPageView( $catPage ) {
		$title = $catPage->getTitle();
		$cat = Category::newFromTitle( $title );
		if ( $cat->getFileCount() > 0 ) {
			$out = $catPage->getContext()->getOutput();
			$this->getModules( $out );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 * Adds a default-enabled preference to gate the feature
	 * @param User $user
	 * @param array &$prefs
	 */
	public function onGetPreferences( $user, &$prefs ) {
		$prefs['multimediaviewer-enable'] = [
			'type' => 'toggle',
			'label-message' => 'multimediaviewer-optin-pref',
			'section' => 'rendering/files',
		];

		if ( !$this->config->get( 'MediaViewerMobileCarousel' ) ) {
			return;
		}

		$prefs[self::DISABLE_IMAGE_CAROUSEL_PREFERENCE] = [
			'type' => $this->getCurrentRequestSkinName() === 'minerva' ? 'toggle' : 'hidden',
			'label-message' => 'multimediaviewer-disable-image-carousel-pref',
			'section' => 'rendering/files',
		];
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 * Export variables used in both PHP and JS to keep DRY
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgMediaViewer'] = true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetDoubleUnderscoreIDs
	 *
	 * Registers the __NOMEDIAVIEWERCAROUSEL__ behavior switch so MediaWiki
	 * recognizes it during parse and records its presence as a page property.
	 * That page property is later used to suppress the mobile carousel for
	 * specific pages.
	 *
	 * @param string[] &$doubleUnderscoreIDs
	 * @return void
	 */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		$doubleUnderscoreIDs[] = self::DISABLE_MOBILE_CAROUSEL_BEHAVIOR_SWITCH;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MakeGlobalVariablesScript
	 * Export variables which depend on the current user
	 * @param array &$vars
	 * @param OutputPage $out
	 * @return void
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$user = $out->getUser();
		$isMultimediaViewerEnable = $this->userOptionsLookup->getDefaultOption(
			'multimediaviewer-enable',
			$user
		);

		$vars['wgMediaViewerOnClick'] = $this->shouldHandleClicks( $user );
		// needed because of T71942; could be different for anon and logged-in
		$vars['wgMediaViewerEnabledByDefault'] = (bool)$isMultimediaViewerEnable;
	}

	/**
	 * ResourceLoader callback to resolve thumbnail width buckets.
	 * Uses $wgThumbnailSteps if configured, otherwise falls back to
	 * $wgMediaViewerThumbnailBucketSizes.
	 *
	 * @param Context|null $context
	 * @param Config $config
	 * @return array
	 */
	public static function getCommonConfig( ?Context $context, Config $config ): array {
		$steps = $config->get( MainConfigNames::ThumbnailSteps );
		return [
			'thumbnailBucketSizes' => $steps ?: $config->get( 'MediaViewerThumbnailBucketSizes' ),
			'imageQueryParameter' => $config->get( 'MediaViewerImageQueryParameter' ),
		];
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ThumbnailBeforeProduceHTML
	 * Modify thumbnail DOM
	 * @param ThumbnailImage $thumbnail
	 * @param array &$attribs Attributes of the <img> element
	 * @param array|bool &$linkAttribs Attributes of the wrapping <a> element
	 */
	public function onThumbnailBeforeProduceHTML(
		$thumbnail,
		&$attribs,
		&$linkAttribs
	) {
		$file = $thumbnail->getFile();

		if ( $file ) {
			$attribs['data-file-width'] = $file->getWidth();
			$attribs['data-file-height'] = $file->getHeight();
		}
	}
}
