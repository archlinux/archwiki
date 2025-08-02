<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Special;

use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewImport;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewList;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTools;
use MediaWiki\Html\Html;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

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
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterHistoryPager
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterPager
 * @group Database
 */
class SpecialAbuseFilterTest extends SpecialPageTestBase {
	use MockAuthorityTrait;
	use FilterFromSpecsTestTrait;

	private Authority $authorityCannotUseProtectedVar;

	private Authority $authorityCanUseProtectedVar;

	protected function setUp(): void {
		parent::setUp();

		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions or alter logging that cause the tests to fail.
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
			'AbuseFilterLogProtectedVariableValueAccess',
		] );

		// Create an authority who can see private filters but not protected variables
		$this->authorityCannotUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getMutableTestUser()->getUserIdentity(),
			[
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
			]
		);

		// Create an authority who can see private and protected variables
		$this->authorityCanUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getMutableTestUser()->getUserIdentity(),
			[
				'abusefilter-access-protected-vars',
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$filterStore = AbuseFilterServices::getFilterStore();
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );

		// Create a test filter where first revision is public, and the second two are protected.
		// The public revision exists to test handling in AbuseFilterViewHistory.
		$firstFilterRevision = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_name = "1.2.3.5"',
			'name' => 'Filter to be converted',
			'privacy' => Flags::FILTER_PUBLIC,
			'userIdentity' => $performer,
			'timestamp' => $this->getDb()->timestamp( '20190825000000' ),
		] );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null, $firstFilterRevision, MutableFilter::newDefault()
		) );
		$secondFilterRevision = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_unnamed_ip = "1.2.3.5"',
			'name' => 'Filter with protected variables',
			'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
			'userIdentity' => $performer,
			'timestamp' => $this->getDb()->timestamp( '20190826000000' ),
		] );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, 1, $secondFilterRevision, $firstFilterRevision
		) );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, 1,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'rules' => 'user_unnamed_ip = "1.2.3.4"',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'userIdentity' => $performer,
				'timestamp' => $this->getDb()->timestamp( '20190827000000' ),
				'hitCount' => 1,
				'actions' => [ 'tags' => [ 'test' ] ]
			] ),
			$secondFilterRevision
		) );

		// Create a second filter which is not public
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '2',
				'rules' => 'user_name = "1.2.3.4"',
				'name' => 'Filter without protected variables',
				'privacy' => Flags::FILTER_PUBLIC,
				'userIdentity' => $performer,
				'timestamp' => '20000101000000',
			] ),
			MutableFilter::newDefault()
		) );

		// Add a log on the protected filter which has a hit count of 1
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_unnamed_ip' => '1.2.3.4',
				'user_name' => 'User1',
			] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );

		// Verify that the expected number of DB rows were created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->caller( __METHOD__ )
			->assertFieldValue( 2 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_history' )
			->caller( __METHOD__ )
			->assertFieldValue( 4 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
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

	public function testViewEditTokenMismatch() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Test filter',
					'wpFilterRules' => 'user_name = "1.2.3.4"',
					'wpFilterNotes' => '',
				],
				// This was posted
				true,
			),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'abusefilter-edit-token-not-match',
			$html,
			'The token mismatch warning message was not present.'
		);
	}

	public function testViewEditUnrecoverableError() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => '',
					'wpFilterRules' => 'user_name = "1.2.3.4"',
					'wpFilterNotes' => '',
				],
				// This was posted
				true,
			)
		);

		$this->assertStringContainsString(
			'abusefilter-edit-notallowed',
			$html,
			'The permission error message was not present.'
		);
	}

	public function testViewEditForInvalidImport() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest( [ 'wpImportText' => 'abc' ], true ),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-import-invalid-data',
			$html,
			'An unknown filter ID should cause an error message.'
		);
		$this->assertStringContainsString(
			'(abusefilter-return',
			$html,
			'Button to return the filter management was missing.'
		);
	}

	/** @dataProvider provideViewEditForBadFilter */
	public function testViewEditForBadFilter( $subPage ) {
		[ $html, ] = $this->executeSpecialPage(
			$subPage, new FauxRequest(), null, $this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-edit-badfilter',
			$html,
			'An unknown filter ID should cause an error message.'
		);
		$this->assertStringContainsString(
			'(abusefilter-return',
			$html,
			'Button to return the filter management was missing.'
		);
	}

	public static function provideViewEditForBadFilter() {
		return [
			'Unknown filter ID' => [ '12345' ],
			'Unknown history ID for existing filter' => [ 'history/1/item/123456' ],
		];
	}

	public function testViewEditProtectedVarsCheckboxPresentForProtectedFilter() {
		// Xml::buildForm uses the global wfMessage which means we need to set
		// the language for the user globally too.
		$this->setUserLang( 'qqx' );

		[ $html, ] = $this->executeSpecialPage(
			'1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);

		$this->assertStringNotContainsString(
			'abusefilter-edit-protected-help-message',
			$html,
			'The enabled checkbox to protect the filter was not present.'
		);
		$this->assertStringContainsString(
			'abusefilter-edit-protected-variable-already-protected',
			$html,
			'The disabled checkbox explaining that the filter is protected was not present.'
		);

		// Also check that the filter hit count is present and as expected for the protected filter.
		$this->assertStringContainsString( '(abusefilter-edit-hitcount', $html );
		$this->assertStringContainsString( '(abusefilter-hitcount: 1', $html );
	}

	public function testViewEditForProtectedFilterWhenUserLacksAuthority() {
		[ $html, ] = $this->executeSpecialPage(
			'1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-edit-denied-protected-vars',
			$html,
			'The protected filter permission error was not present.'
		);
	}

	public function testViewEditProtectedVarsCheckboxAbsentForUnprotectedFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'2',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);
		$this->assertStringNotContainsString(
			'abusefilter-edit-protected',
			$html,
			'Elements related to protected filters were present.'
		);
	}

	public function testViewEditProtectedVarsSave() {
		$authority = $this->authorityCanUseProtectedVar;
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $authority->getUser() );

		// Set the abuse filter editor to the context user, so that the edit token matches
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Uses protected variable',
					'wpFilterRules' => 'user_unnamed_ip = "4.2.3.4"',
					'wpFilterNotes' => '',
					'wpEditToken' => $user->getEditToken( [ 'abusefilter', 'new' ] ),
				],
				// This was posted
				true,
				RequestContext::getMain()->getRequest()->getSession()
			),
			null,
			$authority
		);

		$this->assertStringContainsString(
			'abusefilter-edit-protected-variable-not-protected',
			$html,
			'The error message about protecting the filter was not present.'
		);

		$this->assertStringContainsString(
			'abusefilter-edit-protected-help-message',
			$html,
			'The enabled checkbox to protect the filter was not present.'
		);
	}

	public function testViewEditProtectedVarsSaveSuccess() {
		$authority = $this->authorityCanUseProtectedVar;
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $authority->getUser() );

		// Set the abuse filter editor to the context user, so that the edit token matches
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html, $response ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Uses protected variable',
					'wpFilterRules' => 'user_unnamed_ip = "4.2.3.4"',
					'wpFilterProtected' => '1',
					'wpFilterNotes' => '',
					'wpEditToken' => $user->getEditToken( [ 'abusefilter', 'new' ] ),
				],
				// This was posted
				true,
				RequestContext::getMain()->getRequest()->getSession()
			),
			null,
			$authority
		);

		// On saving successfully, the page redirects
		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'result=success', $response->getHeader( 'location' ) );
	}

	public function testViewTestBatchProtectedVarsFilterVisibility() {
		// Assert that the user who cannot see protected variables cannot load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);
		$this->assertStringNotContainsString( '1.2.3.4', $html );

		// Assert that the user who can see protected variables can load the filter
		[ $html, ] = $this->executeSpecialPage(
			'test/1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);
		$this->assertStringContainsString( '1.2.3.4', $html );
	}

	/**
	 * Common test code used by tests which load the list of AbuseFilters,
	 * used to verify that the headings on the table of AbuseFilters are
	 * as expected.
	 *
	 * @param string $html The HTML of the special page
	 * @param Authority $authority The Authority who viewed the special page
	 * @param bool $searchModeEnabled Whether the special page request included searching
	 *   for filters with a specific substring in their pattern.
	 */
	private function verifyViewListHeadingsPresent(
		string $html, Authority $authority, bool $searchModeEnabled = false
	) {
		$tableHtml = $this->assertAndGetByElementClass( $html, 'mw-datatable' );

		$expectedTableHeadings = [
			'abusefilter-list-id',
			'abusefilter-list-public',
			'abusefilter-list-consequences',
			'abusefilter-list-status',
			'abusefilter-list-lastmodified',
			'abusefilter-list-visibility',
		];
		$expectedTableHeadingsToBeMissing = [];

		if ( $authority->isAllowed( 'abusefilter-log-detail' ) ) {
			$expectedTableHeadings[] = 'abusefilter-list-hitcount';
		} else {
			$expectedTableHeadingsToBeMissing[] = 'abusefilter-list-hitcount';
		}

		$canViewPrivateFilters = $this->getServiceContainer()->get( AbuseFilterPermissionManager::SERVICE_NAME )
			->canViewPrivateFilters( $authority );
		if ( $canViewPrivateFilters && $searchModeEnabled ) {
			$expectedTableHeadings[] = 'abusefilter-list-pattern';
		} else {
			$expectedTableHeadingsToBeMissing[] = 'abusefilter-list-pattern';
		}

		foreach ( $expectedTableHeadings as $heading ) {
			$this->assertStringContainsString( $heading, $tableHtml );
		}

		foreach ( $expectedTableHeadingsToBeMissing as $heading ) {
			$this->assertStringNotContainsString( $heading, $tableHtml );
		}
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}

	public function testViewListWhenLimitIsOne() {
		[ $html, ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'limit' => 1 ] ),
			null,
			$this->authorityCanUseProtectedVar
		);

		// Verify the structure of one row in the table, ensuring the correct flags are set.
		$this->verifyViewListHeadingsPresent( $html, $this->authorityCanUseProtectedVar );

		$this->assertStringContainsString(
			'AbuseFilter/1',
			$this->assertAndGetByElementClass( $html, 'TablePager_col_af_id' ),
			'Missing the URL to the filter'
		);
		$this->assertStringContainsString(
			'Filter with protected variables',
			$this->assertAndGetByElementClass( $html, 'TablePager_col_af_public_comments' )
		);

		$cellClassesToExpectedText = [
			'TablePager_col_af_actions' => '(abusefilter-action-tags)',
			'TablePager_col_af_enabled' => '(abusefilter-enabled)',
			'TablePager_col_af_hidden' => '(abusefilter-protected)',
		];
		foreach ( $cellClassesToExpectedText as $class => $expectedText ) {
			$this->assertSame( $expectedText, $this->assertAndGetByElementClass( $html, $class ) );
		}

		$this->assertStringContainsString(
			'abusefilter-hitcount: 1',
			$this->assertAndGetByElementClass( $html, 'TablePager_col_af_hit_count' )
		);

		$timestampCellHtml = $this->assertAndGetByElementClass( $html, 'TablePager_col_af_timestamp' );
		$this->assertStringContainsString( 'abusefilter-edit-lastmod-text', $timestampCellHtml );
		$this->assertStringContainsString( 'UTSysop', $timestampCellHtml, 'Missing last editor of filter' );
	}

	public function testViewListProtectedVarsFilterVisibility() {
		// Ensure that even if the user cannot view the details of a protected filter
		// they can still see the filter in the filter list
		[ $html, ] = $this->executeSpecialPage(
			'',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);
		$this->assertStringContainsString( 'abusefilter-protected', $html );
		$this->verifyViewListHeadingsPresent( $html, $this->authorityCannotUseProtectedVar );
	}

	public function testViewListWithSearchQueryProtectedVarsFilterVisibility() {
		// Stub out a page with query results for a filter that uses protected variables
		// &sort=af_id&limit=50&asc=&desc=1&deletedfilters=hide&querypattern=user_unnamed_ip&searchoption=LIKE
		$requestWithProtectedVar = new FauxRequest( [
			'sort' => 'af_id',
			'limit' => 50,
			'asc' => '',
			'desc' => 1,
			'deletedfilters' => 'hide',
			'querypattern' => 'user_unnamed_ip = "1',
			'searchoption' => 'LIKE',
			'rulescope' => 'all',
			'furtheroptions' => []
		] );

		// Assert that the user who cannot see protected variables sees no filters when searching
		[ $html, ] = $this->executeSpecialPage(
			'',
			$requestWithProtectedVar,
			null,
			$this->authorityCannotUseProtectedVar
		);
		$this->assertStringContainsString( 'table_pager_empty', $html );
		$this->verifyViewListHeadingsPresent( $html, $this->authorityCannotUseProtectedVar, true );

		// Assert that the user who can see protected variables sees the filter from the db
		[ $html, ] = $this->executeSpecialPage(
			'',
			$requestWithProtectedVar,
			null,
			$this->authorityCanUseProtectedVar
		);
		$this->assertStringContainsString( 'Filter with protected variables', $html );
		$this->verifyViewListHeadingsPresent( $html, $this->authorityCanUseProtectedVar, true );

		// Check that the search found one result and that the pattern is bolded to show the text match
		$patternCellHtml = $this->assertAndGetByElementClass( $html, 'TablePager_col_af_pattern' );
		$this->assertSame( '<b>user_unnamed_ip = "1</b>.2.3.4"', $patternCellHtml );
	}

	public function testViewHistoryForProtectedFilterWhenUserLacksAuthority() {
		[ $html, ] = $this->executeSpecialPage(
			'history/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected)',
			$html,
			'The protected filter permission error was not present.'
		);
		$this->assertStringNotContainsString(
			'abusefilter-history-select-user',
			$html,
			'The filter history should not be shown if the user cannot see the filter.'
		);
	}

	/**
	 * Common test code used by tests which load the history of AbuseFilter filters,
	 * used to verify that the headings on the table on the page is as expected
	 *
	 * @param string $html The HTML of the special page
	 */
	private function verifyHistoryHeadingsPresent( string $html ) {
		$tableHtml = $this->assertAndGetByElementClass( $html, 'mw-datatable' );

		$expectedTableHeadings = [
			'abusefilter-history-timestamp',
			'abusefilter-history-user',
			'abusefilter-history-public',
			'abusefilter-history-flags',
			'abusefilter-history-actions',
			'abusefilter-history-diff',
			'abusefilter-history-timestamp',
		];

		foreach ( $expectedTableHeadings as $heading ) {
			$this->assertStringContainsString( $heading, $tableHtml );
		}
	}

	/**
	 * Common test code used by tests which load the history of AbuseFilter filters,
	 * used to verify that the search form shown on the page has the expected fields.
	 *
	 * @param string $html The HTML of the special page
	 */
	private function verifyHistorySearchFormFields( string $html ) {
		$this->assertStringContainsString( '(abusefilter-history-select-user', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-filter', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-submit', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-legend', $html );
	}

	public function testViewHistoryWhenFilteringForSpecificFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'history/1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);

		// Verify the structure of the form fields and items near the form.
		$this->verifyHistorySearchFormFields( $html );
		$this->assertStringContainsString( '(abusefilter-history-backedit)', $html );

		// Verify the structure of the table
		$this->verifyHistoryHeadingsPresent( $html );

		// Get the HTML for the most recent edit to the filter we are filtering for
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.mw-abusefilter-history-id-3' );
		$this->assertNotCount( 0, $element, "Could not find most recent edit in $html" );
		$rowHtml = Html::rawElement( 'table', [], DOMCompat::getInnerHTML( $element[0] ) );

		// Verify the structure of the row we have found
		$this->assertStringContainsString(
			'UTSysop',
			$this->assertAndGetByElementClass( $rowHtml, 'TablePager_col_afh_user_text' ),
			"Missing editor of the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'Filter with protected variables',
			$this->assertAndGetByElementClass( $rowHtml, 'TablePager_col_afh_public_comments' ),
			"Missing name of filter in $rowHtml"
		);
		$this->assertSame(
			$this->getServiceContainer()->get( SpecsFormatter::SERVICE_NAME )->formatFlags(
				'protected,enabled', $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
			),
			$this->assertAndGetByElementClass( $rowHtml, 'TablePager_col_afh_flags' ),
			"Unexpected flags on the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'abusefilter-action-tags',
			$this->assertAndGetByElementClass( $rowHtml, 'TablePager_col_afh_actions' ),
			"Unexpected actions on the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'abusefilter-history-diff',
			$this->assertAndGetByElementClass( $rowHtml, 'TablePager_col_afh_id' ),
			"Missing diff for the specific version of the filter in $rowHtml"
		);
	}

	public function testViewHistoryHidesProtectedFiltersWhenUserLacksPermissions() {
		[ $html, ] = $this->executeSpecialPage(
			'history',
			new FauxRequest( [] ),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHistorySearchFormFields( $html );
		$this->verifyHistoryHeadingsPresent( $html );

		// Verify that the only filter versions shown is the one without protected variables, including
		// versions of the filter which is now protected.
		$this->assertStringNotContainsString( 'Filter with protected variables', $html );
		$this->assertStringNotContainsString( 'Filter to be converted', $html );
		$this->assertStringContainsString( 'Filter without protected variables', $html );
	}

	/** @dataProvider provideViewDiffWhenDiffInvalid */
	public function testViewDiffWhenDiffInvalid( $subPage ) {
		[ $html, ] = $this->executeSpecialPage(
			$subPage,
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString( '(abusefilter-diff-invalid)', $html );
	}

	public static function provideViewDiffWhenDiffInvalid() {
		return [
			'Filter ID is not numeric' => [ 'history/abc/diff/prev/1' ],
			'Version IDs do not exist' => [ 'history/1/diff/prev/123456' ],
		];
	}

	/** @dataProvider provideViewDiffForProtectedFilterWhenUserLacksAuthority */
	public function testViewDiffForProtectedFilterWhenUserLacksAuthority( $subPage ) {
		[ $html, ] = $this->executeSpecialPage(
			$subPage,
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected)',
			$html,
			'The protected filter permission error was not present.'
		);
	}

	public static function provideViewDiffForProtectedFilterWhenUserLacksAuthority() {
		return [
			'Diff between version which was not protected and a version which is protected' => [
				'history/1/diff/next/1'
			],
			'Diff between two protected versions of the filter' => [ 'history/1/diff/3/prev' ],
		];
	}

	private function verifyHasExamineIntroMessage( string $html ) {
		$this->assertStringContainsString(
			'(abusefilter-examine-intro', $html, 'Missing examine explainer message'
		);
	}

	public function testViewExamineForLogEntryWithMissingId() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1234',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-notfound)',
			$html,
			'Missing error message for unknown AbuseLog ID.'
		);
	}

	public function testViewExamineForLogEntryWhereUserCannotSeeTheFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-log-cannot-see-details)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhereUserCannotSeeSpecificProtectedVariable() {
		// Mock that all users lack access to user_unnamed_ip only, so we can test denying access based on the
		// protected variables that are present in the log.
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'user_unnamed_ip', $variables ) ) {
					$returnStatus->fatal( 'test' );
				}
			}
		);

		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);

		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-examine-protected-vars-permission)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhenFilterIsGlobalAndGlobalFiltersHaveBeenDisabled() {
		// Mock FilterLookup::getFilter to throw a CentralDBNotAvailableException exception
		$mockFilterLookup = $this->createMock( FilterLookup::class );
		$mockFilterLookup->method( 'getFilter' )
			->willThrowException( new CentralDBNotAvailableException() );
		$this->setService( 'AbuseFilterFilterLookup', $mockFilterLookup );

		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		// Verify that even though the Filter details could not be fetched, the filter is still considered
		// protected (to assume the most strict restrictions).
		$this->verifyHasExamineIntroMessage( $html );
		$this->assertStringContainsString(
			'(abusefilter-log-cannot-see-details)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testViewExamineForLogEntryWhenUserCanSeeLog() {
		[ $html, ] = $this->executeSpecialPage(
			'examine/log/1',
			new FauxRequest(),
			null,
			$this->authorityCanUseProtectedVar
		);
		DeferredUpdates::doUpdates();

		$this->verifyHasExamineIntroMessage( $html );

		// Check that the test tools elements are loaded
		$this->assertStringContainsString( '(abusefilter-examine-test', $html );
		$this->assertStringContainsString( '(abusefilter-examine-test-button', $html );

		// Verify that the examiner for the log entry is displayed by checking that the user_unnamed_ip
		// variable value is present.
		$this->assertStringContainsString( '(abusefilter-examine-vars', $html );
		$abuseLogDetailsTableHtml = $this->assertAndGetByElementClass( $html, 'mw-abuselog-details' );
		$this->assertStringContainsString( '1.2.3.4', $abuseLogDetailsTableHtml );

		// Verify that a protected variable access log was created as protected variable values were viewed.
		$result = $this->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $result->numRows() );
		$result->rewind();
		$this->assertArrayEquals(
			[ 'variables' => [ 'user_unnamed_ip' ] ],
			LogEntryBase::extractParams( $result->fetchRow()['log_params'] ),
			false,
			true
		);
	}
}
