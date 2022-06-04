<?php
/**
 * © 2006-2008 Daniel Kinzler and others
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
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 */

namespace MediaWiki\Extension\CategoryTree;

use Article;
use Category;
use Config;
use Html;
use IContextSource;
use MediaWiki\Hook\OutputPageMakeCategoryLinksHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinBuildSidebarHook;
use MediaWiki\Hook\SpecialTrackingCategories__generateCatLinkHook;
use MediaWiki\Hook\SpecialTrackingCategories__preprocessHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use OutputPage;
use Parser;
use ParserOutput;
use PPFrame;
use Sanitizer;
use Skin;
use SpecialPage;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Hooks for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 *
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	ArticleFromTitleHook,
	SpecialTrackingCategories__preprocessHook,
	SpecialTrackingCategories__generateCatLinkHook,
	OutputPageParserOutputHook,
	SkinBuildSidebarHook,
	ParserFirstCallInitHook,
	OutputPageMakeCategoryLinksHook
{

	private const EXTENSION_DATA_FLAG = 'CategoryTree';

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param Config $config
	 */
	public function __construct( ILoadBalancer $loadBalancer, Config $config ) {
		$this->loadBalancer = $loadBalancer;
		$this->config = $config;
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		if ( !$this->config->get( 'CategoryTreeAllowTag' ) ) {
			return;
		}
		$parser->setHook( 'categorytree', [ $this, 'parserHook' ] );
		$parser->setFunctionHook( 'categorytree', [ $this, 'parserFunction' ] );
	}

	/**
	 * Entry point for the {{#categorytree}} tag parser function.
	 * This is a wrapper around Hooks::parserHook
	 * @param Parser $parser
	 * @param string ...$params
	 * @return array|string
	 */
	public function parserFunction( Parser $parser, ...$params ) {
		// first user-supplied parameter must be category name
		if ( !$params ) {
			// no category specified, return nothing
			return '';
		}
		$cat = array_shift( $params );

		// build associative arguments from flat parameter list
		$argv = [];
		foreach ( $params as $p ) {
			if ( preg_match( '/^\s*(\S.*?)\s*=\s*(.*?)\s*$/', $p, $m ) ) {
				$k = $m[1];
				// strip any quotes enclosing the value
				$v = preg_replace( '/^"\s*(.*?)\s*"$/', '$1', $m[2] );
			} else {
				$k = trim( $p );
				$v = true;
			}

			$argv[$k] = $v;
		}

		if ( $parser->getOutputType() === Parser::OT_PREPROCESS ) {
			return Html::rawElement( 'categorytree', $argv, $cat );
		} else {
			// now handle just like a <categorytree> tag
			$html = $this->parserHook( $cat, $argv, $parser );
			return [ $html, 'noparse' => true, 'isHTML' => true ];
		}
	}

	/**
	 * Obtain a category sidebar link based on config
	 * @return bool|string of link
	 */
	private function getCategorySidebarBox() {
		if ( !$this->config->get( 'CategoryTreeSidebarRoot' ) ) {
			return false;
		}
		return $this->parserHook(
			$this->config->get( 'CategoryTreeSidebarRoot' ),
			$this->config->get( 'CategoryTreeSidebarOptions' )
		);
	}

	/**
	 * Hook implementation for injecting a category tree into the sidebar.
	 * Only does anything if $wgCategoryTreeSidebarRoot is set to a category name.
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public function onSkinBuildSidebar( $skin, &$sidebar ) {
		$html = $this->getCategorySidebarBox();
		if ( $html ) {
			$sidebar['categorytree-portlet'] = [];
			CategoryTree::setHeaders( $skin->getOutput() );
		}
	}

	/**
	 * Hook implementation for injecting a category tree link into the sidebar.
	 * Only does anything if $wgCategoryTreeSidebarRoot is set to a category name.
	 * @param Skin $skin
	 * @param string $portlet
	 * @param string &$html
	 */
	public function onSkinAfterPortlet( $skin, $portlet, &$html ) {
		if ( $portlet === 'categorytree-portlet' ) {
			$box = $this->getCategorySidebarBox();
			if ( $box ) {
				$html .= $box;
			}
		}
	}

	/**
	 * Entry point for the <categorytree> tag parser hook.
	 * This loads CategoryTreeFunctions.php and calls CategoryTree::getTag()
	 * @param string $cat
	 * @param array $argv
	 * @param Parser|null $parser
	 * @param PPFrame|null $frame
	 * @param bool $allowMissing
	 * @return bool|string
	 */
	public function parserHook(
		$cat,
		array $argv,
		Parser $parser = null,
		PPFrame $frame = null,
		$allowMissing = false
	) {
		if ( $parser ) {
			# flag for use by Hooks::parserOutput
			$parser->getOutput()->setExtensionData( self::EXTENSION_DATA_FLAG, true );
		}

		$ct = new CategoryTree( $argv );

		$attr = Sanitizer::validateTagAttributes( $argv, 'div' );

		$hideroot = isset( $argv['hideroot'] )
			? CategoryTree::decodeBoolean( $argv['hideroot'] ) : null;
		$onlyroot = isset( $argv['onlyroot'] )
			? CategoryTree::decodeBoolean( $argv['onlyroot'] ) : null;
		$depthArg = isset( $argv['depth'] ) ? (int)$argv['depth'] : null;

		$depth = CategoryTree::capDepth( $ct->getOption( 'mode' ), $depthArg );
		if ( $onlyroot ) {
			$depth = 0;
		}

		return $ct->getTag( $parser, $cat, $hideroot, $attr, $depth, $allowMissing );
	}

	/**
	 * Hook callback that injects messages and things into the <head> tag,
	 * if needed in the current page.
	 * Does nothing if self::EXTENSION_DATA_FLAG is not set on $parserOutput extension data.
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		if ( $parserOutput->getExtensionData( self::EXTENSION_DATA_FLAG ) ) {
			CategoryTree::setHeaders( $outputPage );
		}
	}

	/**
	 * ArticleFromTitle hook, override category page handling
	 *
	 * @param Title $title
	 * @param Article|null &$article Article (object) that will be returned
	 * @param IContextSource $context
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onArticleFromTitle( $title, &$article, $context ) {
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$article = new CategoryTreeCategoryPage( $title );
		}
	}

	/**
	 * OutputPageMakeCategoryLinks hook, override category links
	 * @param OutputPage $out
	 * @param array $categories
	 * @param array &$links
	 * @return bool
	 */
	public function onOutputPageMakeCategoryLinks( $out, $categories, &$links ) {
		if ( !$this->config->get( 'CategoryTreeHijackPageCategories' ) ) {
			// Not enabled, don't do anything
			return true;
		}

		$options = $this->config->get( 'CategoryTreePageCategoryOptions' );
		foreach ( $categories as $category => $type ) {
			$links[$type][] = $this->parserHook( $category, $options, null, null, true );
		}
		CategoryTree::setHeaders( $out );

		return false;
	}

	/**
	 * Get exported data for the "ext.categoryTree" ResourceLoader module.
	 *
	 * @internal For use in extension.json only.
	 * @return array Data to be serialised as data.json
	 */
	public static function getDataForJs() {
		global $wgCategoryTreeCategoryPageOptions;

		// Look, this is pretty bad but CategoryTree is just whacky, it needs to be rewritten
		$ct = new CategoryTree( $wgCategoryTreeCategoryPageOptions );

		return [
			'defaultCtOptions' => $ct->getOptionsAsJsStructure(),
		];
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::preprocess hook
	 * @suppress PhanUndeclaredProperty SpecialPage->categoryTreeCategories
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param array $trackingCategories [ 'msg' => Title, 'cats' => Title[] ]
	 * @phan-param array<string,array{msg:Title,cats:Title[]}> $trackingCategories
	 */
	public function onSpecialTrackingCategories__preprocess(
		$specialPage,
		$trackingCategories
	) {
		$categoryDbKeys = [];
		foreach ( $trackingCategories as $catMsg => $data ) {
			foreach ( $data['cats'] as $catTitle ) {
				$categoryDbKeys[] = $catTitle->getDbKey();
			}
		}
		$categories = [];
		if ( $categoryDbKeys ) {
			$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
			$res = $dbr->select(
				'category',
				[ 'cat_id', 'cat_title', 'cat_pages', 'cat_subcats', 'cat_files' ],
				[ 'cat_title' => array_unique( $categoryDbKeys ) ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$categories[$row->cat_title] = Category::newFromRow( $row );
			}
		}
		$specialPage->categoryTreeCategories = $categories;
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::generateCatLink hook
	 * @suppress PhanUndeclaredProperty SpecialPage->categoryTreeCategories
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param Title $catTitle Title object of the linked category
	 * @param string &$html Result html
	 */
	public function onSpecialTrackingCategories__generateCatLink( $specialPage,
		$catTitle, &$html
	) {
		if ( !isset( $specialPage->categoryTreeCategories ) ) {
			return;
		}

		$cat = $specialPage->categoryTreeCategories[$catTitle->getDBkey()] ?? null;

		$html .= CategoryTree::createCountString( $specialPage->getContext(), $cat, 0 );
	}
}
