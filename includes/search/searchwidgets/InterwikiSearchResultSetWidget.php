<?php

namespace MediaWiki\Search\SearchWidgets;

use ISearchResultSet;
use MediaWiki\Html\Html;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Title\Title;
use OOUI;

/**
 * Renders one or more ISearchResultSets into a sidebar grouped by
 * interwiki prefix. Includes a per-wiki header indicating where
 * the results are from.
 */
class InterwikiSearchResultSetWidget implements SearchResultSetWidget {
	/** @var SpecialSearch */
	protected $specialSearch;
	/** @var SearchResultWidget */
	protected $resultWidget;
	/** @var string[]|null */
	protected $customCaptions;
	/** @var LinkRenderer */
	protected $linkRenderer;
	/** @var InterwikiLookup */
	protected $iwLookup;
	/** @var \MediaWiki\Output\OutputPage */
	protected $output;
	/** @var bool */
	protected $showMultimedia;
	/** @var array */
	protected $iwLogoOverrides;

	public function __construct(
		SpecialSearch $specialSearch,
		SearchResultWidget $resultWidget,
		LinkRenderer $linkRenderer,
		InterwikiLookup $iwLookup,
		$showMultimedia = false
	) {
		$this->specialSearch = $specialSearch;
		$this->resultWidget = $resultWidget;
		$this->linkRenderer = $linkRenderer;
		$this->iwLookup = $iwLookup;
		$this->output = $specialSearch->getOutput();
		$this->showMultimedia = $showMultimedia;
		$this->iwLogoOverrides = $this->specialSearch->getConfig()->get( 'InterwikiLogoOverride' );
	}

	/**
	 * @param string $term User provided search term
	 * @param ISearchResultSet|ISearchResultSet[] $resultSets List of interwiki
	 *  results to render.
	 * @return string HTML
	 */
	public function render( $term, $resultSets ) {
		if ( !is_array( $resultSets ) ) {
			$resultSets = [ $resultSets ];
		}

		$this->loadCustomCaptions();

		if ( $this->showMultimedia ) {
			$this->output->addModules( 'mediawiki.special.search.commonsInterwikiWidget' );
		}
		$this->output->addModuleStyles( 'mediawiki.special.search.interwikiwidget.styles' );
		$this->output->addModuleStyles( 'oojs-ui.styles.icons-wikimedia' );

		$iwResults = [];
		foreach ( $resultSets as $resultSet ) {
			foreach ( $resultSet as $result ) {
				if ( !$result->isBrokenTitle() ) {
					$iwResults[$result->getTitle()->getInterwiki()][] = $result;
				}
			}
		}

		$iwResultSetPos = 1;
		$iwResultListOutput = '';

		foreach ( $iwResults as $iwPrefix => $results ) {
			// TODO: Assumes interwiki results are never paginated
			$position = 0;
			$iwResultItemOutput = '';

			foreach ( $results as $result ) {
				$iwResultItemOutput .= $this->resultWidget->render( $result, $position++ );
			}

			$headerHtml = $this->headerHtml( $term, $iwPrefix );
			$footerHtml = $this->footerHtml( $term, $iwPrefix );
			$iwResultListOutput .= Html::rawElement( 'li',
				[
					'class' => 'iw-resultset',
					'data-iw-resultset-pos' => $iwResultSetPos,
					'data-iw-resultset-source' => $iwPrefix
				],

				$headerHtml .
				$iwResultItemOutput .
				$footerHtml
			);
			$iwResultSetPos++;
		}

		return Html::rawElement(
			'div',
			[ 'id' => 'mw-interwiki-results' ],
			Html::rawElement(
				'ul', [ 'class' => 'iw-results', ], $iwResultListOutput
			)
		);
	}

	/**
	 * Generates an HTML header for the given interwiki prefix
	 *
	 * @param string $term User provided search term
	 * @param string $iwPrefix Interwiki prefix of wiki to show heading for
	 * @return string HTML
	 */
	protected function headerHtml( $term, $iwPrefix ) {
		$href = Title::makeTitle( NS_SPECIAL, 'Search', '', $iwPrefix )->getLocalURL(
			[ 'search' => $term, 'fulltext' => 1 ]
		);

		$interwiki = $this->iwLookup->fetch( $iwPrefix );
		$parsed = wfParseUrl( wfExpandUrl( $interwiki ? $interwiki->getURL() : '/' ) );

		$caption = $this->customCaptions[$iwPrefix] ?? $parsed['host'];

		$searchLink = Html::rawElement( 'a', [ 'href' => $href, 'target' => '_blank' ], $caption );

		return Html::rawElement( 'div',
			[ 'class' => 'iw-result__header' ],
			$this->iwIcon( $iwPrefix ) . $searchLink );
	}

	/**
	 * Generates an HTML footer for the given interwiki prefix
	 *
	 * @param string $term User provided search term
	 * @param string $iwPrefix Interwiki prefix of wiki to show heading for
	 * @return string HTML
	 */
	protected function footerHtml( $term, $iwPrefix ) {
		$href = Title::makeTitle( NS_SPECIAL, 'Search', '', $iwPrefix )->getLocalURL(
			[ 'search' => $term, 'fulltext' => 1 ]
		);

		$interwiki = $this->iwLookup->fetch( $iwPrefix );
		$parsed = wfParseUrl( wfExpandUrl( $interwiki ? $interwiki->getURL() : '/' ) );

		$caption = $this->specialSearch->msg( 'search-interwiki-resultset-link', $parsed['host'] )->escaped();

		$searchLink = Html::rawElement( 'a', [ 'href' => $href, 'target' => '_blank' ], $caption );

		return Html::rawElement( 'div',
			[ 'class' => 'iw-result__footer' ],
			$searchLink );
	}

	protected function loadCustomCaptions() {
		if ( $this->customCaptions !== null ) {
			return;
		}

		$this->customCaptions = [];
		$customLines = explode( "\n", $this->specialSearch->msg( 'search-interwiki-custom' )->escaped() );
		foreach ( $customLines as $line ) {
			$parts = explode( ':', $line, 2 );
			if ( count( $parts ) === 2 ) {
				$this->customCaptions[$parts[0]] = $parts[1];
			}
		}
	}

	/**
	 * Generates a custom OOUI icon element.
	 * These icons are either generated by fetching the interwiki favicon.
	 * or by using config 'InterwikiLogoOverrides'.
	 *
	 * @param string $iwPrefix Interwiki prefix
	 * @return OOUI\IconWidget
	 */
	protected function iwIcon( $iwPrefix ) {
		$logoName = $this->generateLogoName( $iwPrefix );
		// If the value is an URL we use the favicon
		if ( filter_var( $logoName, FILTER_VALIDATE_URL ) || $logoName === "/" ) {
			return $this->generateIconFromFavicon( $logoName );
		}

		$iwIcon = new OOUI\IconWidget( [
			'icon' => $logoName
		] );

		return $iwIcon;
	}

	/**
	 * Generates the logo name used to render the interwiki icon.
	 * The logo name can be defined in two ways:
	 * 1) The logo is generated using interwiki getURL to fetch the site favicon
	 * 2) The logo name is defined using config `wgInterwikiLogoOverride`. This accept
	 * Codex icon names and URLs.
	 *
	 * @param string $prefix Interwiki prefix
	 * @return string logoName
	 */
	protected function generateLogoName( $prefix ) {
		$logoOverridesKeys = array_keys( $this->iwLogoOverrides );
		if ( in_array( $prefix, $logoOverridesKeys ) ) {
			return $this->iwLogoOverrides[ $prefix ];
		}

		$interwiki = $this->iwLookup->fetch( $prefix );
		return $interwiki ? $interwiki->getURL() : '/';
	}

	/**
	 * Fetches the favicon of the provided URL.
	 *
	 * @param string $logoUrl
	 * @return OOUI\IconWidget
	 */
	protected function generateIconFromFavicon( $logoUrl ) {
		$parsed = wfParseUrl( wfExpandUrl( $logoUrl ) );
		$iwIconUrl = $parsed['scheme'] .
			$parsed['delimiter'] .
			$parsed['host'] .
			( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' ) .
			'/favicon.ico';

		$iwIcon = new OOUI\IconWidget( [
			'icon' => 'favicon'
		] );

		return $iwIcon->setAttributes( [ 'style' => "background-image:url($iwIconUrl);" ] );
	}
}
