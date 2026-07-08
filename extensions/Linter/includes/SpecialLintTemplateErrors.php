<?php

namespace MediaWiki\Linter;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\LinkCache;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\QueryPage;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\TimestampFormat as TS;

class SpecialLintTemplateErrors extends QueryPage {

	private string $category;

	public function __construct(
		IConnectionProvider $dbProvider,
		private readonly TitleParser $titleParser,
		private readonly LinkCache $linkCache,
		private readonly PermissionManager $permissionManager,
		private readonly CategoryManager $categoryManager
	) {
		parent::__construct( 'LintTemplateErrors' );
		$this->setDatabaseProvider( $dbProvider );
	}

	/** @inheritDoc */
	public function isExpensive() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// If no category is provided, show an index
		if ( !$par ) {
			$this->showCategoryIndex();
			return;
		}

		if ( !$this->categoryManager->isKnownCategory( $par ) ) {
			$this->setHeaders();
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->getOutput()->addHTML(
				Html::element(
					'span',
					[ 'class' => 'error' ],
					$this->msg( 'linter-invalid-category' )->text()
				)
			);
			return;
		}

		$this->category = $par;
		$params = $this->getRequest()->getQueryValues();

		if ( isset( $params[ 'template' ] ) ) {
			$this->showPages();
			return;
		}

		parent::execute( $par );
		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
		$this->getOutput()->setPageTitleMsg(
			$this->msg( 'category-by-template',
				$this->msg( "linter-category-{$this->category}" )->text()
			)
		);
	}

	/**
	 * Display an index of all available lint error categories
	 */
	private function showCategoryIndex() {
		$this->setHeaders();

		$out = $this->getOutput();
		$out->addWikiMsg( 'linter-template-errors-index-desc' );

		$categories = $this->categoryManager->getVisibleCategories();

		$out->addHTML( Html::openElement( 'ul' ) );

		foreach ( $categories as $category ) {
			$categoryTitle = $this->msg( "linter-category-{$category}" )->text();
			$link = $this->getLinkRenderer()->makeLink(
				$this->getPageTitle( $category ),
				$categoryTitle
			);
			$out->addHTML( Html::rawElement( 'li', [], $link ) );
		}

		$out->addHTML( Html::closeElement( 'ul' ) );
	}

	/**
	 * FIXME: This is a bit redundant with SpecialLintErrors::execute
	 * but that method needs to be refactored before trying to shoehorn
	 * this in.
	 */
	private function showPages() {
		$this->setHeaders();

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle( $this->category ) );
		$out->setPageTitleMsg(
			$this->msg( 'category-by-template',
				$this->msg( "linter-category-{$this->category}" )->text()
			)
		);

		try {
			$template = $this->titleParser->parseTitle(
				$this->getRequest()->getText( 'template' )
			);
		} catch ( MalformedTitleException ) {
			$this->getOutput()->addHTML(
				Html::element(
					'span',
					[ 'class' => 'error' ],
					$this->msg( 'linter-invalid-title' )->text()
				)
			);
			return;
		}

		$pager = new LintErrorsPager(
			$this->getContext(),
			$this->categoryManager,
			$this->linkCache,
			$this->getLinkRenderer(),
			$this->permissionManager,
			$this->category,
			[],
			false,
			'',
			Title::castFromLinkTarget( $template )?->getPrefixedDBKey() ?? '',
			''
		);

		$out->addParserOutput(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getPageHeader() {
		return $this->msg(
			"category-by-template-desc", $this->category
		)->parse();
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		return [
			'tables' => [ 'linter' ],
			'fields' => [
				'title' => 'linter_template',
				// linter_template uses the local text ns prefix
				// so we need to use NS_MAIN to make it work
				'namespace' => NS_MAIN,
				'value' => 'COUNT(*)',
			],
			'conds' => [
				$dbr->expr( 'linter_cat', '=',
					$this->categoryManager->getCategoryId( $this->category ) ),
				$dbr->expr( 'linter_template', '!=', "" ),
				$dbr->expr( 'linter_template', '!=', "multi-part-template-block" ),
				$dbr->expr( 'linter_template', '!=', "parser-function" ),
			],
			'options' => [
				'GROUP BY' => [ 'linter_template' ],
			],
		];
	}

	/** @inheritDoc */
	protected function sortDescending() {
		return true;
	}

	/** @inheritDoc */
	public function formatResult( $skin, $result ) {
		$title = Title::newFromText( $result->title );
		if ( $title === null ) {
			return '&mdash;';
		}
		$count = intval( $result->value );
		return $this->getLinkRenderer()->makeLink( $title ) .
			" ({$count}, " . $this->getLinkRenderer()->makeLink(
				$this->getPageTitle()->getSubpage( $this->category ),
				'pages',
				[],
				[ 'template' => $title->getPrefixedDBKey() ]
			) . ")";
	}

	/** @inheritDoc */
	public function fetchFromCache( $limit, $offset = false ) {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'qcc_type',
				'value' => 'qcc_value',
				'namespace' => 'qcc_namespace',
				'title' => 'qcc_title',
			] )
			->from( 'querycachetwo' )
			->where( [
				'qcc_type' => $this->getName(),
				'qcc_titletwo' => $this->category,
			] );

		if ( $limit !== false ) {
			$queryBuilder->limit( intval( $limit ) );
		}

		if ( $offset !== false ) {
			$queryBuilder->offset( intval( $offset ) );
		}

		$order = $this->getCacheOrderFields();
		if ( $this->sortDescending() ) {
			$queryBuilder->orderBy( $order, SelectQueryBuilder::SORT_DESC );
		} else {
			$queryBuilder->orderBy( $order );
		}

		return $queryBuilder->caller( __METHOD__ )->fetchResultSet();
	}

	/** @inheritDoc */
	public function recache( $limit, $unused = true ) {
		return array_reduce(
			$this->categoryManager->getVisibleCategories(),
			function ( $num, $category ) use ( $limit ) {
				return $num + ( $this->recacheInternal( $limit, $category ) ?: 0 );
			},
			0
		);
	}

	/**
	 * Recache, but with a category
	 *
	 * @param int|false $limit
	 * @param string $category
	 * @return bool|int
	 */
	private function recacheInternal( $limit, $category ) {
		if ( !$this->isCacheable() ) {
			return 0;
		}

		$this->category = $category;

		$fname = static::class . '::recache';
		$dbw = $this->getDatabaseProvider()->getPrimaryDatabase();

		// Do query
		$res = $this->reallyDoQuery( $limit, false );

		$num = false;
		if ( $res ) {
			$num = $res->numRows();
			// Fetch results
			$vals = [];
			foreach ( $res as $i => $row ) {
				if ( isset( $row->value ) ) {
					if ( $this->usesTimestamps() ) {
						$value = (int)wfTimestamp( TS::UNIX, $row->value );
					} else {
						// T16414
						$value = intval( $row->value );
					}
				} else {
					$value = $i;
				}

				$vals[] = [
					'qcc_type' => $this->getName(),
					'qcc_title' => $row->title,
					'qcc_value' => $value,
					'qcc_titletwo' => $category
				];
			}

			$dbw->doAtomicSection(
				$fname,
				function ( IDatabase $dbw, $fname ) use ( $category ) {
					// Clear out any old cached data
					$dbw->newDeleteQueryBuilder()
						->deleteFrom( 'querycachetwo' )
						->where( [
							'qcc_type' => $this->getName(),
							'qcc_titletwo' => $category
						] )
						->caller( $fname )->execute();
					// Update the querycache_info record for the page
					$dbw->newInsertQueryBuilder()
						->insertInto( 'querycache_info' )
						->row( [ 'qci_type' => $this->getName(), 'qci_timestamp' => $dbw->timestamp() ] )
						->onDuplicateKeyUpdate()
						->uniqueIndexFields( [ 'qci_type' ] )
						->set( [ 'qci_timestamp' => $dbw->timestamp() ] )
						->caller( $fname )->execute();
				}
			);
			// Save results into the querycachetwo table on the primary DB
			if ( count( $vals ) ) {
				foreach ( array_chunk( $vals, 500 ) as $chunk ) {
					$dbw->newInsertQueryBuilder()
						->insertInto( 'querycachetwo' )
						->rows( $chunk )
						->caller( $fname )->execute();
				}
			}
		}

		return $num;
	}

	/** @inheritDoc */
	public function delete( LinkTarget $title ) {
		if ( $this->isCached() ) {
			$dbw = $this->getDatabaseProvider()->getPrimaryDatabase();
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'querycachetwo' )
				->where( [
					'qcc_type' => $this->getName(),
					'qcc_namespace' => NS_MAIN,
					'qcc_title' => Title::castFromLinkTarget( $title )?->getPrefixedDBKey(),
				] )
				->caller( __METHOD__ )->execute();
		}
	}

	/**
	 * @return string[]
	 */
	protected function getSubpagesForPrefixSearch() {
		return $this->categoryManager->getVisibleCategories();
	}

}
