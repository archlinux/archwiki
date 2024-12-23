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

use MediaWiki\Category\Category;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Core functions for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 */
class CategoryTree {
	public OptionManager $optionManager;
	private Config $config;
	private IConnectionProvider $dbProvider;
	private LinkRenderer $linkRenderer;

	public function __construct(
		array $options,
		Config $config,
		IConnectionProvider $dbProvider,
		LinkRenderer $linkRenderer
	) {
		$this->optionManager = new OptionManager( $options, $config );
		$this->config = $config;
		$this->dbProvider = $dbProvider;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * Add ResourceLoader modules to the OutputPage object
	 */
	public static function setHeaders( OutputPage $outputPage ): void {
		# Add the modules
		$outputPage->addModuleStyles( 'ext.categoryTree.styles' );
		$outputPage->addModules( 'ext.categoryTree' );
	}

	/**
	 * Custom tag implementation. This is called by Hooks::parserHook, which is used to
	 * load CategoryTreeFunctions.php on demand.
	 * @param string $category
	 * @param bool $hideroot
	 * @param array $attr
	 * @param int $depth
	 * @return bool|string
	 */
	public function getTag( string $category, bool $hideroot = false, array $attr = [],
		int $depth = 1
	) {
		$title = self::makeTitle( $category );
		if ( !$title ) {
			return false;
		}

		if ( isset( $attr['class'] ) ) {
			$attr['class'] .= ' CategoryTreeTag';
		} else {
			$attr['class'] = 'CategoryTreeTag';
		}

		$attr['data-ct-mode'] = $this->optionManager->getOption( 'mode' );
		$attr['data-ct-options'] = $this->optionManager->getOptionsAsJsStructure();

		if ( !$title->getArticleID() ) {
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
	 * @suppress PhanUndeclaredClassMethod,PhanUndeclaredClassInstanceof
	 */
	public function renderChildren( Title $title, int $depth = 1 ): string {
		if ( !$title->inNamespace( NS_CATEGORY ) ) {
			// Non-categories can't have children. :)
			return '';
		}

		$dbr = $this->dbProvider->getReplicaDatabase();

		$inverse = $this->optionManager->isInverse();
		$mode = $this->optionManager->getOption( 'mode' );
		$namespaces = $this->optionManager->getOption( 'namespaces' );

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'page_id', 'page_namespace', 'page_title',
				'page_is_redirect', 'page_len', 'page_latest', 'cl_to', 'cl_from'
			] )
			->orderBy( [ 'cl_type', 'cl_sortkey' ] )
			->limit( $this->config->get( 'CategoryTreeMaxChildren' ) )
			->caller( __METHOD__ );

		if ( $inverse ) {
			$queryBuilder
				->from( 'categorylinks' )
				->leftJoin( 'page', null, [
					'cl_to = page_title', 'page_namespace' => NS_CATEGORY
				] )
				->where( [ 'cl_from' => $title->getArticleID() ] );
		} else {
			$queryBuilder
				->from( 'page' )
				->join( 'categorylinks', null, 'cl_from = page_id' )
				->where( [ 'cl_to' => $title->getDBkey() ] )
				->useIndex( 'cl_sortkey' );

			# namespace filter.
			if ( $namespaces ) {
				// NOTE: we assume that the $namespaces array contains only integers!
				// decodeNamepsaces makes it so.
				$queryBuilder->where( [ 'page_namespace' => $namespaces ] );
			} elseif ( $mode !== CategoryTreeMode::ALL ) {
				if ( $mode === CategoryTreeMode::PAGES ) {
					$queryBuilder->where( [ 'cl_type' => [ 'page', 'subcat' ] ] );
				} else {
					$queryBuilder->where( [ 'cl_type' => 'subcat' ] );
				}
			}
		}

		# fetch member count if possible
		$doCount = !$inverse && $this->config->get( 'CategoryTreeUseCategoryTable' );

		if ( $doCount ) {
			$queryBuilder
				->leftJoin( 'category', null, [ 'cat_title = page_title', 'page_namespace' => NS_CATEGORY ] )
				->fields( [ 'cat_id', 'cat_title', 'cat_subcats', 'cat_pages', 'cat_files' ] );
		}

		$res = $queryBuilder->fetchResultSet();

		# collect categories separately from other pages
		$categories = '';
		$other = '';
		$suppressTranslations = OptionManager::decodeBoolean(
			$this->optionManager->getOption( 'notranslations' )
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
				$page = TranslatablePage::isTranslationPage( $title );

				if ( ( $page instanceof TranslatablePage ) && $baseTitle->exists() ) {
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
	 */
	public function renderParents( Title $title ): string {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$res = $dbr->newSelectQueryBuilder()
			->select( 'cl_to' )
			->from( 'categorylinks' )
			->where( [ 'cl_from' => $title->getArticleID() ] )
			->limit( $this->config->get( 'CategoryTreeMaxChildren' ) )
			->orderBy( 'cl_to' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$special = SpecialPage::getTitleFor( 'CategoryTree' );

		$s = [];

		foreach ( $res as $row ) {
			$t = Title::makeTitle( NS_CATEGORY, $row->cl_to );

			$s[] = Html::rawElement( 'span', [ 'class' => 'CategoryTreeItem' ],
				$this->linkRenderer->makeLink(
					$special,
					$t->getText(),
					[ 'class' => 'CategoryTreeLabel' ],
					[ 'target' => $t->getDBkey() ] + $this->optionManager->getOptions()
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
	public function renderNode( Title $title, int $children = 0 ): string {
		if ( $this->config->get( 'CategoryTreeUseCategoryTable' )
			&& $title->inNamespace( NS_CATEGORY )
			&& !$this->optionManager->isInverse()
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
	 */
	public function renderNodeInfo( Title $title, ?Category $cat = null, int $children = 0 ): string {
		$mode = $this->optionManager->getOption( 'mode' );

		$isInCatNS = $title->inNamespace( NS_CATEGORY );
		$key = $title->getDBkey();

		$hideprefix = $this->optionManager->getOption( 'hideprefix' );

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

		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$link = Html::rawElement( 'bdi', [ 'dir' => $contLang->getDir() ],
			$this->linkRenderer->makeLink( $title, $label ) );

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
					// href and role will be added client-side
					'class' => 'CategoryTreeToggle',
					'data-ct-title' => $key,
					'href' => $title->getLocalURL(),
				];

				if ( $children === 0 ) {
					$linkattr['aria-expanded'] = 'false';
				} else {
					$linkattr['data-ct-loaded'] = true;
					$linkattr['aria-expanded'] = 'true';
				}

				$bullet = Html::element( 'a', $linkattr ) . ' ';
			}
		} else {
			$bullet = '';
			$attr['class'] = 'CategoryTreePageBullet';
		}
		$s .= Html::rawElement( 'span', $attr, $bullet ) . ' ';

		$s .= $link;

		if ( $count !== false && $this->optionManager->getOption( 'showcount' ) ) {
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
	 */
	public static function createCountString( IContextSource $context, ?Category $cat,
		int $countMode
	): string {
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
		$s = ' ' . Html::rawElement(
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
	 */
	public static function makeTitle( string $title ): ?Title {
		$title = trim( $title );

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
}
