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

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\CategoryViewer__doCategoryQueryHook;
use MediaWiki\Hook\CategoryViewer__generateLinkHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinBuildSidebarHook;
use MediaWiki\Hook\SpecialTrackingCategories__generateCatLinkHook;
use MediaWiki\Hook\SpecialTrackingCategories__preprocessHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Output\Hook\OutputPageRenderCategoryLinkHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Hooks for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 */
class Hooks implements
	SpecialTrackingCategories__preprocessHook,
	SpecialTrackingCategories__generateCatLinkHook,
	SkinBuildSidebarHook,
	ParserFirstCallInitHook,
	OutputPageRenderCategoryLinkHook,
	CategoryViewer__doCategoryQueryHook,
	CategoryViewer__generateLinkHook
{
	public function __construct(
		private readonly CategoryCache $categoryCache,
		private readonly CategoryTreeFactory $categoryTreeFactory,
		private readonly Config $config,
		private readonly TitleFormatter $titleFormatter,
	) {
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
	 * @param string $cat
	 * @param string ...$params
	 * @return array|string
	 */
	public function parserFunction( Parser $parser, string $cat, string ...$params ) {
		// The first user-supplied parameter is the category name.
		// On `{{#categorytree:}}` $cat is ''.

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
	 * @return string|null of link
	 */
	private function getCategorySidebarBox(): ?string {
		if ( !$this->config->get( 'CategoryTreeSidebarRoot' ) ) {
			return null;
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
	 * This loads CategoryTree and calls CategoryTree::getTag()
	 * @param string|null $cat
	 * @param array $argv
	 * @param Parser|null $parser
	 * @return string
	 */
	public function parserHook(
		?string $cat,
		array $argv,
		?Parser $parser = null
	): string {
		if ( $parser ) {
			$parserOutput = $parser->getOutput();
			$parserOutput->addModuleStyles( [ 'ext.categoryTree.styles' ] );
			$parserOutput->addModules( [ 'ext.categoryTree' ] );

			$disableCache = $this->config->get( 'CategoryTreeDisableCache' );
			if ( $disableCache === true ) {
				$parserOutput->updateCacheExpiry( 0 );
			} elseif ( is_int( $disableCache ) ) {
				$parserOutput->updateCacheExpiry( $disableCache );
			}
		}

		$ct = $this->categoryTreeFactory->newCategoryTree( $argv );

		$attr = Sanitizer::validateTagAttributes( $argv, 'div' );

		$hideroot = OptionManager::decodeBoolean( $argv['hideroot'] ?? false );
		$onlyroot = OptionManager::decodeBoolean( $argv['onlyroot'] ?? false );
		$depthArg = (int)( $argv['depth'] ?? 1 );

		$depth = $ct->optionManager->capDepth( $depthArg );
		if ( $onlyroot ) {
			$depth = 0;
			$message = '<span class="error">'
				. wfMessage( 'categorytree-onlyroot-message' )->inContentLanguage()->parse()
				. '</span>';
			if ( $parser ) {
				$parser->getOutput()->addWarningMsg( 'categorytree-deprecation-warning' );
				$parser->addTrackingCategory( 'categorytree-deprecation-category' );
			}
		} else {
			$message = '';
		}

		return $message .
			$ct->getTag( $cat ?? '', $hideroot, $attr, $depth );
	}

	/**
	 * OutputPageRenderCategoryLink hook
	 * @param OutputPage $outputPage
	 * @param ProperPageIdentity $categoryTitle
	 * @param string $text
	 * @param ?string &$link
	 */
	public function onOutputPageRenderCategoryLink(
		OutputPage $outputPage,
		ProperPageIdentity $categoryTitle,
		string $text,
		?string &$link
	): void {
		if ( !$this->config->get( 'CategoryTreeHijackPageCategories' ) ) {
			// Not enabled, don't do anything
			return;
		}
		if ( !$categoryTitle->exists() ) {
			// Category doesn't exist. Let the normal LinkRenderer generate the link.
			return;
		}

		CategoryTree::setHeaders( $outputPage );

		$options = $this->config->get( 'CategoryTreePageCategoryOptions' );
		$link = $this->parserHook(
			$this->titleFormatter->getPrefixedText( $categoryTitle ),
			$options
		);
	}

	/**
	 * Get exported data for the "ext.categoryTree" ResourceLoader module.
	 *
	 * @internal For use in extension.json only.
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array Data to be serialised as data.json
	 */
	public static function getDataForJs( RL\Context $context, Config $config ): array {
		// Look, this is pretty bad but CategoryTree is just whacky, it needs to be rewritten
		$optionManager = new OptionManager( $config->get( 'CategoryTreeCategoryPageOptions' ), $config );

		return [
			'defaultCtOptions' => $optionManager->getOptionsAsJsStructure(),
		];
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::preprocess hook
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param array $trackingCategories [ 'msg' => LinkTarget, 'cats' => LinkTarget[] ]
	 * @phan-param array<string,array{msg:LinkTarget,cats:LinkTarget[]}> $trackingCategories
	 */
	public function onSpecialTrackingCategories__preprocess(
		$specialPage,
		$trackingCategories
	) {
		$categoryTargets = [];
		foreach ( $trackingCategories as $data ) {
			foreach ( $data['cats'] as $catTitle ) {
				$categoryTargets[] = $catTitle;
			}
		}
		$this->categoryCache->doQuery( $categoryTargets );
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::generateCatLink hook
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param LinkTarget $catTitle LinkTarget object of the linked category
	 * @param string &$html Result html
	 */
	public function onSpecialTrackingCategories__generateCatLink( $specialPage,
		$catTitle, &$html
	) {
		$cat = $this->categoryCache->getCategory( $catTitle );

		$html .= CategoryTree::createCountString( $specialPage->getContext(), $cat, 0 );
	}

	/**
	 * @param string $type
	 * @param IResultWrapper $res
	 */
	public function onCategoryViewer__doCategoryQuery( $type, $res ) {
		if ( $type === 'subcat' ) {
			$this->categoryCache->fillFromQuery( $res );
		}
	}

	/**
	 * @param string $type
	 * @param Title $title
	 * @param string $html
	 * @param string &$link
	 * @return bool
	 */
	public function onCategoryViewer__generateLink( $type, $title, $html, &$link ) {
		if ( $type !== 'subcat' || $link !== null ) {
			return true;
		}

		$request = RequestContext::getMain()->getRequest();
		if ( $request->getCheck( 'notree' ) ) {
			return true;
		}

		$options = $this->config->get( 'CategoryTreeCategoryPageOptions' );
		$mode = $request->getRawVal( 'mode' );
		if ( $mode !== null ) {
			$options['mode'] = $mode;
		}
		$tree = $this->categoryTreeFactory->newCategoryTree( $options );

		$cat = $this->categoryCache->getCategory( $title );

		$link = $tree->renderNodeInfo( $title, $cat );

		CategoryTree::setHeaders( RequestContext::getMain()->getOutput() );
		return false;
	}
}
