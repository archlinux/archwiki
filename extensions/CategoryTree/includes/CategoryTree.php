<?php
/**
 * © 2006-2007 Daniel Kinzler
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

use Category;
use Exception;
use ExtensionRegistry;
use FormatJson;
use Html;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Parser;
use RequestContext;
use SpecialPage;
use Title;

/**
 * Core functions for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 */
class CategoryTree {
	/** @var array */
	private $mOptions = [];

	/** @var LinkRenderer */
	private $linkRenderer;

	/**
	 * @param array $options
	 */
	public function __construct( array $options ) {
		global $wgCategoryTreeDefaultOptions;
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		// ensure default values and order of options.
		// Order may become important, it may influence the cache key!
		foreach ( $wgCategoryTreeDefaultOptions as $option => $default ) {
			$this->mOptions[$option] = $options[$option] ?? $default;
		}

		$this->mOptions['mode'] = self::decodeMode( $this->mOptions['mode'] );

		if ( $this->mOptions['mode'] === CategoryTreeMode::PARENTS ) {
			// namespace filter makes no sense with CategoryTreeMode::PARENTS
			$this->mOptions['namespaces'] = false;
		}

		$this->mOptions['hideprefix'] = self::decodeHidePrefix( $this->mOptions['hideprefix'] );
		$this->mOptions['showcount'] = self::decodeBoolean( $this->mOptions['showcount'] );
		$this->mOptions['namespaces'] = self::decodeNamespaces( $this->mOptions['namespaces'] );

		if ( $this->mOptions['namespaces'] ) {
			# automatically adjust mode to match namespace filter
			if ( count( $this->mOptions['namespaces'] ) === 1
				&& $this->mOptions['namespaces'][0] === NS_CATEGORY ) {
				$this->mOptions['mode'] = CategoryTreeMode::CATEGORIES;
			} elseif ( !in_array( NS_FILE, $this->mOptions['namespaces'] ) ) {
				$this->mOptions['mode'] = CategoryTreeMode::PAGES;
			} else {
				$this->mOptions['mode'] = CategoryTreeMode::ALL;
			}
		}
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function getOption( $name ) {
		return $this->mOptions[$name];
	}

	/**
	 * @return bool
	 */
	private function isInverse() {
		return $this->getOption( 'mode' ) === CategoryTreeMode::PARENTS;
	}

	/**
	 * @param mixed $nn
	 * @return array|bool
	 */
	private static function decodeNamespaces( $nn ) {
		if ( $nn === false || $nn === null ) {
			return false;
		}

		if ( !is_array( $nn ) ) {
			$nn = preg_split( '![\s#:|]+!', $nn );
		}

		$namespaces = [];
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		foreach ( $nn as $n ) {
			if ( is_int( $n ) ) {
				$ns = $n;
			} else {
				$n = trim( $n );
				if ( $n === '' ) {
					continue;
				}

				$lower = strtolower( $n );

				if ( is_numeric( $n ) ) {
					$ns = (int)$n;
				} elseif ( $n === '-' || $n === '_' || $n === '*' || $lower === 'main' ) {
					$ns = NS_MAIN;
				} else {
					$ns = $contLang->getNsIndex( $n );
				}
			}

			if ( is_int( $ns ) ) {
				$namespaces[] = $ns;
			}
		}

		# get elements into canonical order
		sort( $namespaces );
		return $namespaces;
	}

	/**
	 * @param mixed $mode
	 * @return int|string
	 */
	public static function decodeMode( $mode ) {
		global $wgCategoryTreeDefaultOptions;

		if ( $mode === null ) {
			return $wgCategoryTreeDefaultOptions['mode'];
		}
		if ( is_int( $mode ) ) {
			return $mode;
		}

		$mode = trim( strtolower( $mode ) );

		if ( is_numeric( $mode ) ) {
			return (int)$mode;
		}

		if ( $mode === 'all' ) {
			$mode = CategoryTreeMode::ALL;
		} elseif ( $mode === 'pages' ) {
			$mode = CategoryTreeMode::PAGES;
		} elseif ( $mode === 'categories' || $mode === 'sub' ) {
			$mode = CategoryTreeMode::CATEGORIES;
		} elseif ( $mode === 'parents' || $mode === 'super' || $mode === 'inverse' ) {
			$mode = CategoryTreeMode::PARENTS;
		} elseif ( $mode === 'default' ) {
			$mode = $wgCategoryTreeDefaultOptions['mode'];
		}

		return (int)$mode;
	}

	/**
	 * Helper function to convert a string to a boolean value.
	 * Perhaps make this a global function in MediaWiki proper
	 * @param mixed $value
	 * @return bool|null|string
	 */
	public static function decodeBoolean( $value ) {
		if ( $value === null ) {
			return null;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return ( $value > 0 );
		}

		$value = trim( strtolower( $value ) );
		if ( is_numeric( $value ) ) {
			return ( (int)$value > 0 );
		}

		if ( $value === 'yes' || $value === 'y'
			|| $value === 'true' || $value === 't' || $value === 'on'
		) {
			return true;
		} elseif ( $value === 'no' || $value === 'n'
			|| $value === 'false' || $value === 'f' || $value === 'off'
		) {
			return false;
		} elseif ( $value === 'null' || $value === 'default' || $value === 'none' || $value === 'x' ) {
			return null;
		} else {
			return false;
		}
	}

	/**
	 * @param mixed $value
	 * @return int|string
	 */
	public static function decodeHidePrefix( $value ) {
		global $wgCategoryTreeDefaultOptions;

		if ( $value === null ) {
			return $wgCategoryTreeDefaultOptions['hideprefix'];
		}
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( $value === true ) {
			return CategoryTreeHidePrefix::ALWAYS;
		}
		if ( $value === false ) {
			return CategoryTreeHidePrefix::NEVER;
		}

		$value = trim( strtolower( $value ) );

		if ( $value === 'yes' || $value === 'y'
			|| $value === 'true' || $value === 't' || $value === 'on'
		) {
			return CategoryTreeHidePrefix::ALWAYS;
		} elseif ( $value === 'no' || $value === 'n'
			|| $value === 'false' || $value === 'f' || $value === 'off'
		) {
			return CategoryTreeHidePrefix::NEVER;
		} elseif ( $value === 'always' ) {
			return CategoryTreeHidePrefix::ALWAYS;
		} elseif ( $value === 'never' ) {
			return CategoryTreeHidePrefix::NEVER;
		} elseif ( $value === 'auto' ) {
			return CategoryTreeHidePrefix::AUTO;
		} elseif ( $value === 'categories' || $value === 'category' || $value === 'smart' ) {
			return CategoryTreeHidePrefix::CATEGORIES;
		} else {
			return $wgCategoryTreeDefaultOptions['hideprefix'];
		}
	}

	/**
	 * Add ResourceLoader modules to the OutputPage object
	 * @param OutputPage $outputPage
	 */
	public static function setHeaders( OutputPage $outputPage ) {
		# Add the modules
		$outputPage->addModuleStyles( 'ext.categoryTree.styles' );
		$outputPage->addModules( 'ext.categoryTree' );
	}

	/**
	 * @param array $options
	 * @param string $enc
	 * @return mixed
	 * @throws Exception
	 */
	protected static function encodeOptions( array $options, $enc ) {
		if ( $enc === 'mode' || $enc === '' ) {
			$opt = $options['mode'];
		} elseif ( $enc === 'json' ) {
			$opt = FormatJson::encode( $options );
		} else {
			throw new Exception( 'Unknown encoding for CategoryTree options: ' . $enc );
		}

		return $opt;
	}

	/**
	 * @param int|null $depth
	 * @return string
	 */
	public function getOptionsAsCacheKey( $depth = null ) {
		$key = '';

		foreach ( $this->mOptions as $k => $v ) {
			if ( is_array( $v ) ) {
				$v = implode( '|', $v );
			}
			$key .= $k . ':' . $v . ';';
		}

		if ( $depth !== null ) {
			$key .= ';depth=' . $depth;
		}
		return $key;
	}

	/**
	 * @param int|null $depth
	 * @return mixed
	 */
	public function getOptionsAsJsStructure( $depth = null ) {
		if ( $depth !== null ) {
			$opt = $this->mOptions;
			$opt['depth'] = $depth;
			$s = self::encodeOptions( $opt, 'json' );
		} else {
			$s = self::encodeOptions( $this->mOptions, 'json' );
		}

		return $s;
	}

	/**
	 * Custom tag implementation. This is called by Hooks::parserHook, which is used to
	 * load CategoryTreeFunctions.php on demand.
	 * @param ?Parser $parser
	 * @param string $category
	 * @param bool $hideroot
	 * @param array $attr
	 * @param int $depth
	 * @param bool $allowMissing
	 * @return bool|string
	 */
	public function getTag( ?Parser $parser, $category, $hideroot = false, array $attr = [],
		$depth = 1, $allowMissing = false
	) {
		global $wgCategoryTreeDisableCache;

		$category = trim( $category );
		if ( $category === '' ) {
			return false;
		}

		if ( $parser ) {
			if ( $wgCategoryTreeDisableCache === true ) {
				$parser->getOutput()->updateCacheExpiry( 0 );
			} elseif ( is_int( $wgCategoryTreeDisableCache ) ) {
				$parser->getOutput()->updateCacheExpiry( $wgCategoryTreeDisableCache );
			}
		}

		$title = self::makeTitle( $category );

		if ( $title === null ) {
			return false;
		}

		if ( isset( $attr['class'] ) ) {
			$attr['class'] .= ' CategoryTreeTag';
		} else {
			$attr['class'] = 'CategoryTreeTag';
		}

		$attr['data-ct-mode'] = $this->mOptions['mode'];
		$attr['data-ct-options'] = $this->getOptionsAsJsStructure();

		if ( !$allowMissing && !$title->getArticleID() ) {
			$html = Html::rawElement( 'span', [ 'class' => 'CategoryTreeNotice' ],
				wfMessage( 'categorytree-not-found' )
					->plaintextParams( $category )
					->parse()
			);
		} else {
			if ( !$hideroot ) {
				$html = $this->renderNode( $title, $depth );
			} else {
				$html = $this->renderChildren( $title, $depth );
			}
		}

		return Html::rawElement( 'div', $attr, $html );
	}

	/**
	 * Returns a string with an HTML representation of the children of the given category.
	 * @param Title $title
	 * @param int $depth
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredClassInstanceof
	 * @return string
	 */
	public function renderChildren( Title $title, $depth = 1 ) {
		global $wgCategoryTreeMaxChildren, $wgCategoryTreeUseCategoryTable;

		if ( !$title->inNamespace( NS_CATEGORY ) ) {
			// Non-categories can't have children. :)
			return '';
		}

		$dbr = wfGetDB( DB_REPLICA );

		$inverse = $this->isInverse();
		$mode = $this->getOption( 'mode' );
		$namespaces = $this->getOption( 'namespaces' );

		$tables = [ 'page', 'categorylinks' ];
		$fields = [ 'page_id', 'page_namespace', 'page_title',
			'page_is_redirect', 'page_len', 'page_latest', 'cl_to',
			'cl_from' ];
		$where = [];
		$joins = [];
		$options = [ 'ORDER BY' => 'cl_type, cl_sortkey', 'LIMIT' => $wgCategoryTreeMaxChildren ];

		if ( $inverse ) {
			$joins['categorylinks'] = [ 'RIGHT JOIN', [
				'cl_to = page_title', 'page_namespace' => NS_CATEGORY
			] ];
			$where['cl_from'] = $title->getArticleID();
		} else {
			$joins['categorylinks'] = [ 'JOIN', 'cl_from = page_id' ];
			$where['cl_to'] = $title->getDBkey();
			$options['USE INDEX']['categorylinks'] = 'cl_sortkey';

			# namespace filter.
			if ( $namespaces ) {
				// NOTE: we assume that the $namespaces array contains only integers!
				// decodeNamepsaces makes it so.
				$where['page_namespace'] = $namespaces;
			} elseif ( $mode !== CategoryTreeMode::ALL ) {
				if ( $mode === CategoryTreeMode::PAGES ) {
					$where['cl_type'] = [ 'page', 'subcat' ];
				} else {
					$where['cl_type'] = 'subcat';
				}
			}
		}

		# fetch member count if possible
		$doCount = !$inverse && $wgCategoryTreeUseCategoryTable;

		if ( $doCount ) {
			$tables = array_merge( $tables, [ 'category' ] );
			$fields = array_merge( $fields, [
				'cat_id', 'cat_title', 'cat_subcats', 'cat_pages', 'cat_files'
			] );
			$joins['category'] = [ 'LEFT JOIN', [
				'cat_title = page_title', 'page_namespace' => NS_CATEGORY ]
			];
		}

		$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $joins );

		# collect categories separately from other pages
		$categories = '';
		$other = '';
		$suppressTranslations = self::decodeBoolean(
			$this->getOption( 'notranslations' )
		) && ExtensionRegistry::getInstance()->isLoaded( 'Translate' );

		if ( $suppressTranslations ) {
			$lb = MediaWikiServices::getInstance()->getLinkBatchFactory()->newLinkBatch();
			foreach ( $res as $row ) {
				$title = Title::newFromText( $row->page_title, $row->page_namespace );
				// Page name could have slashes, check the subpage for valid language built-in codes
				if ( $title !== null && $title->getSubpageText() ) {
					$lb->addObj( $title->getBaseTitle() );
				}
			}

			$lb->execute();
		}

		foreach ( $res as $row ) {
			if ( $suppressTranslations ) {
				$title = Title::newFromRow( $row );
				$baseTitle = $title->getBaseTitle();
				$page = \TranslatablePage::isTranslationPage( $title );

				if ( ( $page instanceof \TranslatablePage ) && $baseTitle->exists() ) {
					// T229265: Render only the default pages created and ignore their
					// translations.
					continue;
				}
			}

			# NOTE: in inverse mode, the page record may be null, because we use a right join.
			#      happens for categories with no category page (red cat links)
			if ( $inverse && $row->page_title === null ) {
				$t = Title::makeTitle( NS_CATEGORY, $row->cl_to );
			} else {
				# TODO: translation support; ideally added to Title object
				$t = Title::newFromRow( $row );
			}

			$cat = null;

			if ( $doCount && (int)$row->page_namespace === NS_CATEGORY ) {
				$cat = Category::newFromRow( $row, $t );
			}

			$s = $this->renderNodeInfo( $t, $cat, $depth - 1 );

			if ( (int)$row->page_namespace === NS_CATEGORY ) {
				$categories .= $s;
			} else {
				$other .= $s;
			}
		}

		return $categories . $other;
	}

	/**
	 * Returns a string with an HTML representation of the parents of the given category.
	 * @param Title $title
	 * @return string
	 */
	public function renderParents( Title $title ) {
		global $wgCategoryTreeMaxChildren;

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			'categorylinks',
			[ 'cl_to' ],
			[ 'cl_from' => $title->getArticleID() ],
			__METHOD__,
			[
				'LIMIT' => $wgCategoryTreeMaxChildren,
				'ORDER BY' => 'cl_to'
			]
		);

		$special = SpecialPage::getTitleFor( 'CategoryTree' );

		$s = [];

		foreach ( $res as $row ) {
			$t = Title::makeTitle( NS_CATEGORY, $row->cl_to );

			$s[] = Html::rawElement( 'span', [ 'class' => 'CategoryTreeItem' ],
				$this->linkRenderer->makeLink(
					$special,
					$t->getText(),
					[ 'class' => 'CategoryTreeLabel' ],
					[ 'target' => $t->getDBkey() ] + $this->mOptions
				)
			);
		}

		return implode( wfMessage( 'pipe-separator' )->escaped(), $s );
	}

	/**
	 * Returns a string with a HTML represenation of the given page.
	 * @param Title $title
	 * @param int $children
	 * @return string
	 */
	public function renderNode( Title $title, $children = 0 ) {
		global $wgCategoryTreeUseCategoryTable;

		if ( $wgCategoryTreeUseCategoryTable && $title->inNamespace( NS_CATEGORY )
			&& !$this->isInverse()
		) {
			$cat = Category::newFromTitle( $title );
		} else {
			$cat = null;
		}

		return $this->renderNodeInfo( $title, $cat, $children );
	}

	/**
	 * Returns a string with a HTML represenation of the given page.
	 * $info must be an associative array, containing at least a Title object under the 'title' key.
	 * @param Title $title
	 * @param Category|null $cat
	 * @param int $children
	 * @return string
	 */
	public function renderNodeInfo( Title $title, Category $cat = null, $children = 0 ) {
		$mode = $this->getOption( 'mode' );

		$isInCatNS = $title->inNamespace( NS_CATEGORY );
		$key = $title->getDBkey();

		$hideprefix = $this->getOption( 'hideprefix' );

		if ( $hideprefix === CategoryTreeHidePrefix::ALWAYS ) {
			$hideprefix = true;
		} elseif ( $hideprefix === CategoryTreeHidePrefix::AUTO ) {
			$hideprefix = ( $mode === CategoryTreeMode::CATEGORIES );
		} elseif ( $hideprefix === CategoryTreeHidePrefix::CATEGORIES ) {
			$hideprefix = $isInCatNS;
		} else {
			$hideprefix = true;
		}

		// when showing only categories, omit namespace in label unless we explicitely defined the
		// configuration setting
		// patch contributed by Manuel Schneider <manuel.schneider@wikimedia.ch>, Bug 8011
		if ( $hideprefix ) {
			$label = $title->getText();
		} else {
			$label = $title->getPrefixedText();
		}

		$link = $this->linkRenderer->makeLink( $title, $label );

		$count = false;
		$s = '';

		# NOTE: things in CategoryTree.js rely on the exact order of tags!
		#      Specifically, the CategoryTreeChildren div must be the first
		#      sibling with nodeName = DIV of the grandparent of the expland link.

		$s .= Html::openElement( 'div', [ 'class' => 'CategoryTreeSection' ] );
		$s .= Html::openElement( 'div', [ 'class' => 'CategoryTreeItem' ] );

		$attr = [ 'class' => 'CategoryTreeBullet' ];

		if ( $isInCatNS ) {
			if ( $cat ) {
				if ( $mode === CategoryTreeMode::CATEGORIES ) {
					$count = $cat->getSubcatCount();
				} elseif ( $mode === CategoryTreeMode::PAGES ) {
					$count = $cat->getMemberCount() - $cat->getFileCount();
				} else {
					$count = $cat->getMemberCount();
				}
			}
			if ( $count === 0 ) {
				$bullet = '';
				$attr['class'] = 'CategoryTreeEmptyBullet';
			} else {
				$linkattr = [
					'class' => 'CategoryTreeToggle',
					'data-ct-title' => $key,
				];

				if ( $children === 0 ) {
					$linkattr['data-ct-state'] = 'collapsed';
				} else {
					$linkattr['data-ct-loaded'] = true;
					$linkattr['data-ct-state'] = 'expanded';
				}

				$bullet = Html::element( 'span', $linkattr ) . ' ';
			}
		} else {
			$bullet = '';
			$attr['class'] = 'CategoryTreePageBullet';
		}
		$s .= Html::rawElement( 'span', $attr, $bullet ) . ' ';

		$s .= $link;

		if ( $count !== false && $this->getOption( 'showcount' ) ) {
			$s .= self::createCountString( RequestContext::getMain(), $cat, $count );
		}

		$s .= Html::closeElement( 'div' );
		$s .= Html::openElement(
			'div',
			[
				'class' => 'CategoryTreeChildren',
				'style' => $children === 0 ? 'display:none' : null
			]
		);

		if ( $isInCatNS && $children > 0 ) {
			$children = $this->renderChildren( $title, $children );
			if ( $children === '' ) {
				switch ( $mode ) {
					case CategoryTreeMode::CATEGORIES:
						$msg = 'categorytree-no-subcategories';
						break;
					case CategoryTreeMode::PAGES:
						$msg = 'categorytree-no-pages';
						break;
					case CategoryTreeMode::PARENTS:
						$msg = 'categorytree-no-parent-categories';
						break;
					default:
						$msg = 'categorytree-nothing-found';
						break;
				}
				$children = Html::element( 'i', [ 'class' => 'CategoryTreeNotice' ],
					wfMessage( $msg )->text()
				);
			}
			$s .= $children;
		}

		$s .= Html::closeElement( 'div' ) . Html::closeElement( 'div' );

		return $s;
	}

	/**
	 * Create a string which format the page, subcat and file counts of a category
	 * @param IContextSource $context
	 * @param ?Category $cat
	 * @param int $countMode
	 * @return string
	 */
	public static function createCountString( IContextSource $context, ?Category $cat,
		$countMode
	) {
		$allCount = $cat ? $cat->getMemberCount() : 0;
		$subcatCount = $cat ? $cat->getSubcatCount() : 0;
		$fileCount = $cat ? $cat->getFileCount() : 0;
		$pages = $cat ? $cat->getPageCount( Category::COUNT_CONTENT_PAGES ) : 0;

		$attr = [
			'title' => $context->msg( 'categorytree-member-counts' )
				->numParams( $subcatCount, $pages, $fileCount, $allCount, $countMode )->text(),
			# numbers and commas get messed up in a mixed dir env
			'dir' => $context->getLanguage()->getDir()
		];
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$s = $contLang->getDirMark() . ' ';

		# Create a list of category members with only non-zero member counts
		$memberNums = [];
		if ( $subcatCount ) {
			$memberNums[] = $context->msg( 'categorytree-num-categories' )
				->numParams( $subcatCount )->text();
		}
		if ( $pages ) {
			$memberNums[] = $context->msg( 'categorytree-num-pages' )->numParams( $pages )->text();
		}
		if ( $fileCount ) {
			$memberNums[] = $context->msg( 'categorytree-num-files' )
				->numParams( $fileCount )->text();
		}
		$memberNumsShort = $memberNums
			? $context->getLanguage()->commaList( $memberNums )
			: $context->msg( 'categorytree-num-empty' )->text();

		# Only $5 is actually used in the default message.
		# Other arguments can be used in a customized message.
		$s .= Html::rawElement(
			'span',
			$attr,
			$context->msg( 'categorytree-member-num' )
				// Do not use numParams on params 1-4, as they are only used for customisation.
				->params( $subcatCount, $pages, $fileCount, $allCount, $memberNumsShort )
				->escaped()
		);

		return $s;
	}

	/**
	 * Creates a Title object from a user provided (and thus unsafe) string
	 * @param string $title
	 * @return null|Title
	 */
	public static function makeTitle( $title ) {
		$title = trim( strval( $title ) );

		if ( $title === '' ) {
			return null;
		}

		# The title must be in the category namespace
		# Ignore a leading Category: if there is one
		$t = Title::newFromText( $title, NS_CATEGORY );
		if ( !$t || !$t->inNamespace( NS_CATEGORY ) || $t->isExternal() ) {
			// If we were given something like "Wikipedia:Foo" or "Template:",
			// try it again but forced.
			$title = "Category:$title";
			$t = Title::newFromText( $title );
		}
		return $t;
	}

	/**
	 * Internal function to cap depth
	 * @param string $mode
	 * @param int $depth
	 * @return int|mixed
	 */
	public static function capDepth( $mode, $depth ) {
		global $wgCategoryTreeMaxDepth;

		if ( !is_numeric( $depth ) ) {
			return 1;
		}

		$depth = intval( $depth );

		if ( is_array( $wgCategoryTreeMaxDepth ) ) {
			$max = $wgCategoryTreeMaxDepth[$mode] ?? 1;
		} elseif ( is_numeric( $wgCategoryTreeMaxDepth ) ) {
			$max = $wgCategoryTreeMaxDepth;
		} else {
			wfDebug( 'CategoryTree::capDepth: $wgCategoryTreeMaxDepth is invalid.' );
			$max = 1;
		}

		return min( $depth, $max );
	}
}
