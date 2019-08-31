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

class MultimediaViewerHooks {
	/** Link to more information about this module */
	protected static $infoLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Extension:Media_Viewer/About';

	/** Link to a page where this module can be discussed */
	protected static $discussionLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Extension_talk:Media_Viewer/About';

	/** Link to help about this module */
	protected static $helpLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Help:Extension:Media_Viewer';

	public static function onUserGetDefaultOptions( &$defaultOptions ) {
		global $wgMediaViewerEnableByDefault;

		if ( $wgMediaViewerEnableByDefault ) {
			$defaultOptions['multimediaviewer-enable'] = 1;
		}

		return true;
	}

	/**
	 * Checks the context for whether to load the viewer.
	 * @param User $user
	 * @return bool
	 */
	protected static function shouldHandleClicks( $user ) {
		global $wgMediaViewerIsInBeta, $wgMediaViewerEnableByDefaultForAnonymous,
			$wgMediaViewerEnableByDefault;

		if ( $wgMediaViewerIsInBeta && ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' ) ) {
			return BetaFeatures::isFeatureEnabled( $user, 'multimedia-viewer' );
		}

		if ( $wgMediaViewerEnableByDefaultForAnonymous === null ) {
			$enableByDefaultForAnons = $wgMediaViewerEnableByDefault;
		} else {
			$enableByDefaultForAnons = $wgMediaViewerEnableByDefaultForAnonymous;
		}

		if ( !$user->isLoggedIn() ) {
			return (bool)$enableByDefaultForAnons;
		} else {
			return (bool)$user->getOption( 'multimediaviewer-enable' );
		}
	}

	/**
	 * Handler for all places where we add the modules
	 * Could be on article pages or on Category pages
	 * @param OutputPage &$out
	 * @return bool
	 */
	protected static function getModules( &$out ) {
		$out->addModules( [ 'mmv.head', 'mmv.bootstrap.autostart' ] );

		return true;
	}

	/**
	 * Handler for BeforePageDisplay hook
	 * Add JavaScript to the page when an image is on it
	 * and the user has enabled the feature if BetaFeatures is installed
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @return bool
	 */
	public static function getModulesForArticle( &$out, &$skin ) {
		$pageHasThumbnails = count( $out->getFileSearchOptions() ) > 0;
		$pageIsFilePage = $out->getTitle()->inNamespace( NS_FILE );
		// TODO: Have Flow work out if there are any images on the page
		$pageIsFlowPage = ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) &&
			// CONTENT_MODEL_FLOW_BOARD
			$out->getTitle()->getContentModel() === 'flow-board';
		$fileRelatedSpecialPages = [ 'NewFiles', 'ListFiles', 'MostLinkedFiles',
			'MostGloballyLinkedFiles', 'UncategorizedFiles', 'UnusedFiles', 'Search' ];
		$pageIsFileRelatedSpecialPage = $out->getTitle()->inNamespace( NS_SPECIAL )
			&& in_array( $out->getTitle()->getText(), $fileRelatedSpecialPages );

		if ( $pageHasThumbnails || $pageIsFilePage || $pageIsFileRelatedSpecialPage || $pageIsFlowPage ) {
			return self::getModules( $out );
		}

		return true;
	}

	/**
	 * Handler for CategoryPageView hook
	 * Add JavaScript to the page if there are images in the category
	 * @param CategoryPage &$catPage
	 * @return bool
	 */
	public static function getModulesForCategory( &$catPage ) {
		$title = $catPage->getTitle();
		$cat = Category::newFromTitle( $title );
		if ( $cat->getFileCount() > 0 ) {
			$out = $catPage->getContext()->getOutput();
			return self::getModules( $out );
		}

		return true;
	}

	/**
	 * Add a beta preference to gate the feature
	 * @param User $user
	 * @param array &$prefs
	 * @return true
	 */
	public static function getBetaPreferences( $user, &$prefs ) {
		global $wgExtensionAssetsPath, $wgMediaViewerIsInBeta;

		if ( !$wgMediaViewerIsInBeta ) {
			return true;
		}

		$prefs['multimedia-viewer'] = [
			'label-message' => 'multimediaviewer-pref',
			'desc-message' => 'multimediaviewer-pref-desc',
			'info-link' => self::$infoLink,
			'discussion-link' => self::$discussionLink,
			'help-link' => self::$helpLink,
			'screenshot' => [
				'ltr' => "$wgExtensionAssetsPath/MultimediaViewer/viewer-ltr.svg",
				'rtl' => "$wgExtensionAssetsPath/MultimediaViewer/viewer-rtl.svg",
			],
		];

		return true;
	}

	/**
	 * Adds a default-enabled preference to gate the feature on non-beta sites
	 * @param User $user
	 * @param array &$prefs
	 * @return true
	 */
	public static function getPreferences( $user, &$prefs ) {
		global $wgMediaViewerIsInBeta;

		if ( !$wgMediaViewerIsInBeta ) {
			$prefs['multimediaviewer-enable'] = [
				'type' => 'toggle',
				'label-message' => 'multimediaviewer-optin-pref',
				'section' => 'rendering/files',
			];
		}

		return true;
	}

	/**
	 * Export variables used in both PHP and JS to keep DRY
	 * @param array &$vars
	 * @return bool
	 */
	public static function resourceLoaderGetConfigVars( &$vars ) {
		global $wgMediaViewerActionLoggingSamplingFactorMap,
			$wgMediaViewerNetworkPerformanceSamplingFactor,
			$wgMediaViewerDurationLoggingSamplingFactor,
			$wgMediaViewerDurationLoggingLoggedinSamplingFactor,
			$wgMediaViewerAttributionLoggingSamplingFactor,
			$wgMediaViewerDimensionLoggingSamplingFactor,
			$wgMediaViewerIsInBeta, $wgMediaViewerUseThumbnailGuessing, $wgMediaViewerExtensions,
			$wgMediaViewerImageQueryParameter, $wgMediaViewerRecordVirtualViewBeaconURI;

		$vars['wgMultimediaViewer'] = [
			'infoLink' => self::$infoLink,
			'discussionLink' => self::$discussionLink,
			'helpLink' => self::$helpLink,
			'useThumbnailGuessing' => (bool)$wgMediaViewerUseThumbnailGuessing,
			'durationSamplingFactor' => $wgMediaViewerDurationLoggingSamplingFactor,
			'durationSamplingFactorLoggedin' => $wgMediaViewerDurationLoggingLoggedinSamplingFactor,
			'networkPerformanceSamplingFactor' => $wgMediaViewerNetworkPerformanceSamplingFactor,
			'actionLoggingSamplingFactorMap' => $wgMediaViewerActionLoggingSamplingFactorMap,
			'attributionSamplingFactor' => $wgMediaViewerAttributionLoggingSamplingFactor,
			'dimensionSamplingFactor' => $wgMediaViewerDimensionLoggingSamplingFactor,
			'imageQueryParameter' => $wgMediaViewerImageQueryParameter,
			'recordVirtualViewBeaconURI' => $wgMediaViewerRecordVirtualViewBeaconURI,
			'tooltipDelay' => 1000,
			'extensions' => $wgMediaViewerExtensions,
		];
		$vars['wgMediaViewer'] = true;
		$vars['wgMediaViewerIsInBeta'] = $wgMediaViewerIsInBeta;

		return true;
	}

	/**
	 * Export variables which depend on the current user
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public static function makeGlobalVariablesScript( &$vars, OutputPage $out ) {
		$defaultUserOptions = User::getDefaultOptions();

		$user = $out->getUser();
		$vars['wgMediaViewerOnClick'] = self::shouldHandleClicks( $user );
		// needed because of bug 69942; could be different for anon and logged-in
		$vars['wgMediaViewerEnabledByDefault'] =
			!empty( $defaultUserOptions['multimediaviewer-enable'] );
	}

	/**
	 * Modify thumbnail DOM
	 * @param ThumbnailImage $thumbnail
	 * @param array &$attribs Attributes of the <img> element
	 * @param array|bool &$linkAttribs Attributes of the wrapping <a> element
	 * @return true
	 */
	public static function thumbnailBeforeProduceHTML( ThumbnailImage $thumbnail, array &$attribs,
		&$linkAttribs
	) {
		$file = $thumbnail->getFile();

		if ( $file ) {
			// At the moment all classes that extend File have getWidth() and getHeight()
			// but since the File class doesn't have these methods defined, this check
			// is more future-proof

			if ( method_exists( $file, 'getWidth' ) ) {
				$attribs['data-file-width'] = $file->getWidth();
			}

			if ( method_exists( $file, 'getHeight' ) ) {
				$attribs['data-file-height'] = $file->getHeight();
			}
		}

		return true;
	}
}
