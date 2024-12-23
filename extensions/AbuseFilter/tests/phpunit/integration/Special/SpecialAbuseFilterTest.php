<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Special;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewImport;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewList;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTools;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\Special\AbuseFilterSpecialPage
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewImport
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewList
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTools
 * @group Database
 */
class SpecialAbuseFilterTest extends SpecialPageTestBase {
	use MockAuthorityTrait;

	/**
	 * @var SimpleAuthority
	 */
	private $authorityCannotViewProtectedVar;

	/**
	 * @var SimpleAuthority
	 */
	private $authorityCanViewProtectedVar;

	protected function setUp(): void {
		parent::setUp();

		// Add filter to query for
		$filter = [
			'id' => '1',
			'rules' => 'user_unnamed_ip = "1.2.3.4"',
			'name' => 'Filter with protected variables',
			'hidden' => Flags::FILTER_USES_PROTECTED_VARS,
			'user' => 0,
			'user_text' => 'FilterTester',
			'timestamp' => '20190826000000',
			'enabled' => 1,
			'comments' => '',
			'hit_count' => 0,
			'throttled' => 0,
			'deleted' => 0,
			'actions' => [],
			'global' => 0,
			'group' => 'default'
		];
		$this->createFilter( $filter );

		// Create the user to query for filters
		$user = $this->getTestSysop()->getUser();

		// Create an authority who can see private filters but not protected variables
		$this->authorityCannotViewProtectedVar = new SimpleAuthority(
			$user,
			[ 'abusefilter-log-private', 'abusefilter-view-private' ]
		);

		// Create an authority who can see private and protected variables
		$this->authorityCanViewProtectedVar = new SimpleAuthority(
			$user,
			[ 'abusefilter-access-protected-vars', 'abusefilter-log-private', 'abusefilter-view-private' ]
		);
	}

	/**
	 * @dataProvider provideInstantiateView
	 */
	public function testInstantiateView( string $viewClass, array $params = [] ) {
		$sp = $this->newSpecialPage();
		$view = $sp->instantiateView( $viewClass, $params );
		$this->assertInstanceOf( $viewClass, $view );
	}

	public static function provideInstantiateView(): array {
		return [
			[ AbuseFilterViewDiff::class ],
			[ AbuseFilterViewEdit::class, [ 'filter' => 1 ] ],
			[ AbuseFilterViewExamine::class ],
			[ AbuseFilterViewHistory::class ],
			[ AbuseFilterViewImport::class ],
			[ AbuseFilterViewList::class ],
			[ AbuseFilterViewRevert::class ],
			[ AbuseFilterViewTestBatch::class ],
			[ AbuseFilterViewTools::class ],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialAbuseFilter {
		$services = MediaWikiServices::getInstance();
		$sp = new SpecialAbuseFilter(
			$services->getService( AbuseFilterPermissionManager::SERVICE_NAME ),
			$services->getObjectFactory()
		);
		$sp->setLinkRenderer(
			$services->getLinkRendererFactory()->create()
		);
		return $sp;
	}

	/**
	 * Adapted from FilterStoreTest->getFilterFromSpecs()
	 *
	 * @param array $filterSpecs
	 * @param array $actions
	 * @return Filter
	 */
	private function getFilterFromSpecs( array $filterSpecs, array $actions = [] ): Filter {
		return new Filter(
			new Specs(
				$filterSpecs['rules'],
				$filterSpecs['comments'],
				$filterSpecs['name'],
				array_keys( $filterSpecs['actions'] ),
				$filterSpecs['group']
			),
			new Flags(
				$filterSpecs['enabled'],
				$filterSpecs['deleted'],
				$filterSpecs['hidden'],
				$filterSpecs['global']
			),
			$actions,
			new LastEditInfo(
				$filterSpecs['user'],
				$filterSpecs['user_text'],
				$filterSpecs['timestamp']
			),
			$filterSpecs['id'],
			$filterSpecs['hit_count'],
			$filterSpecs['throttled']
		);
	}

	/**
	 * Adapted from FilterStoreTest->createFilter()
	 *
	 * @param array $row
	 */
	private function createFilter( array $row ): void {
		$row['timestamp'] = $this->getDb()->timestamp( $row['timestamp'] );
		$filter = $this->getFilterFromSpecs( $row );
		$oldFilter = MutableFilter::newDefault();
		// Use some black magic to bypass checks
		/** @var FilterStore $filterStore */
		$filterStore = TestingAccessWrapper::newFromObject( AbuseFilterServices::getFilterStore() );
		$row = $filterStore->filterToDatabaseRow( $filter, $oldFilter );
		$row['af_actor'] = $this->getServiceContainer()->getActorNormalization()->acquireActorId(
			$this->getTestUser()->getUserIdentity(),
			$this->getDb()
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	public function testViewTestBatchProtectedVarsFilterVisibility() {
		// Assert that the user who cannot see protected variables cannot load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			new FauxRequest(),
			null,
			$this->authorityCannotViewProtectedVar
		);
		$this->assertStringNotContainsString( '1.2.3.4', $html );

		// Assert that the user who can see protected variables can load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			new FauxRequest(),
			null,
			$this->authorityCanViewProtectedVar
		);
		$this->assertStringContainsString( '1.2.3.4', $html );
	}

	public function testViewListProtectedVarsFilterVisibility() {
		// Stub out a page with query results for a filter that uses protected variables
		// &sort=af_id&limit=50&asc=&desc=1&deletedfilters=hide&querypattern=user_unnamed_ip&searchoption=LIKE
		$requestWithProtectedVar = new FauxRequest( [
			'sort' => 'af_id',
			'limit' => 50,
			'asc' => '',
			'desc' => 1,
			'deletedfilters' => 'hide',
			'querypattern' => 'user_unnamed_ip',
			'searchoption' => 'LIKE',
			'rulescope' => 'all',
			'furtheroptions' => []
		] );

		// Assert that the user who cannot see protected variables sees no filters
		[ $html, ] = $this->executeSpecialPage(
			'',
			$requestWithProtectedVar,
			null,
			$this->authorityCannotViewProtectedVar
		);
		$this->assertStringContainsString( 'table_pager_empty', $html );

		// Assert that the user who can see protected variables sees the filter from the db
		[ $html, ] = $this->executeSpecialPage(
			'',
			$requestWithProtectedVar,
			null,
			$this->authorityCanViewProtectedVar
		);
		$this->assertStringContainsString( '1.2.3.4', $html );
	}

}
