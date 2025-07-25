<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Special;

use Generator;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentity;
use SpecialPageTestBase;
use stdClass;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseLogPager
 * @covers \MediaWiki\Extension\AbuseFilter\View\HideAbuseLog
 * @group Database
 */
class SpecialAbuseLogTest extends SpecialPageTestBase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;

	private static UserIdentity $logPerformer;

	private Authority $authorityCannotUseProtectedVar;
	private Authority $authorityCanUseProtectedVar;

	protected function setUp(): void {
		parent::setUp();

		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions that cause the tests to fail.
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
		] );

		// Create an authority who can see private filters but not protected variables
		$this->authorityCannotUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getTestUser()->getUserIdentity(),
			[
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
			]
		);

		// Create an authority who can see private and protected variables
		$this->authorityCanUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getTestUser()->getUserIdentity(),
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
	 * @param stdClass $row
	 * @param RevisionRecord $revRec
	 * @param bool $canSeeHidden
	 * @param bool $canSeeSuppressed
	 * @param string $expected
	 * @dataProvider provideEntryAndVisibility
	 */
	public function testGetEntryVisibilityForUser(
		stdClass $row,
		RevisionRecord $revRec,
		bool $canSeeHidden,
		bool $canSeeSuppressed,
		string $expected
	) {
		$user = $this->createMock( UserIdentity::class );
		$authority = new SimpleAuthority( $user, $canSeeSuppressed ? [ 'viewsuppressed' ] : [] );
		$afPermManager = $this->createMock( AbuseFilterPermissionManager::class );
		$afPermManager->method( 'canSeeHiddenLogEntries' )->with( $authority )->willReturn( $canSeeHidden );
		$revLookup = $this->createMock( RevisionLookup::class );
		$revLookup->method( 'getRevisionById' )->willReturn( $revRec );
		$this->setService( 'RevisionLookup', $revLookup );
		$this->assertSame(
			$expected,
			SpecialAbuseLog::getEntryVisibilityForUser( $row, $authority, $afPermManager )
		);
	}

	public static function provideEntryAndVisibility(): Generator {
		$visibleRow = (object)[ 'afl_rev_id' => 1, 'afl_deleted' => 0 ];
		$hiddenRow = (object)[ 'afl_rev_id' => 1, 'afl_deleted' => 1 ];
		$page = new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentityValue::LOCAL );
		$visibleRev = new MutableRevisionRecord( $page );

		yield 'Visible entry and rev, cannot see hidden, cannot see suppressed' =>
			[ $visibleRow, $visibleRev, false, false, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Visible entry and rev, can see hidden, cannot see suppressed' =>
			[ $visibleRow, $visibleRev, true, false, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Visible entry and rev, cannot see hidden, can see suppressed' =>
			[ $visibleRow, $visibleRev, false, false, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Visible entry and rev, can see hidden, can see suppressed' =>
			[ $visibleRow, $visibleRev, true, false, SpecialAbuseLog::VISIBILITY_VISIBLE ];

		yield 'Hidden entry, visible rev, can see hidden, cannot see suppressed' =>
			[ $hiddenRow, $visibleRev, true, false, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Hidden entry, visible rev, cannot see hidden, cannot see suppressed' =>
			[ $hiddenRow, $visibleRev, false, false, SpecialAbuseLog::VISIBILITY_HIDDEN ];
		yield 'Hidden entry, visible rev, can see hidden, can see suppressed' =>
			[ $hiddenRow, $visibleRev, true, true, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Hidden entry, visible rev, cannot see hidden, can see suppressed' =>
			[ $hiddenRow, $visibleRev, false, true, SpecialAbuseLog::VISIBILITY_HIDDEN ];

		$userSupRev = new MutableRevisionRecord( $page );
		$userSupRev->setVisibility( RevisionRecord::SUPPRESSED_USER );
		yield 'Hidden entry, user suppressed rev, can see hidden, cannot see suppressed' =>
			[ $hiddenRow, $userSupRev, true, false, SpecialAbuseLog::VISIBILITY_HIDDEN_IMPLICIT ];
		yield 'Hidden entry, user suppressed rev, cannot see hidden, cannot see suppressed' =>
			[ $hiddenRow, $userSupRev, false, false, SpecialAbuseLog::VISIBILITY_HIDDEN ];
		yield 'Hidden entry, user suppressed rev, can see hidden, can see suppressed' =>
			[ $hiddenRow, $userSupRev, true, true, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Hidden entry, user suppressed rev, cannot see hidden, can see suppressed' =>
			[ $hiddenRow, $userSupRev, false, true, SpecialAbuseLog::VISIBILITY_HIDDEN ];

		$allSuppRev = new MutableRevisionRecord( $page );
		$allSuppRev->setVisibility( RevisionRecord::SUPPRESSED_ALL );
		yield 'Hidden entry, all suppressed rev, can see hidden, cannot see suppressed' =>
			[ $hiddenRow, $allSuppRev, true, false, SpecialAbuseLog::VISIBILITY_HIDDEN_IMPLICIT ];
		yield 'Hidden entry, all suppressed rev, cannot see hidden, cannot see suppressed' =>
			[ $hiddenRow, $allSuppRev, false, false, SpecialAbuseLog::VISIBILITY_HIDDEN ];
		yield 'Hidden entry, all suppressed rev, can see hidden, can see suppressed' =>
			[ $hiddenRow, $allSuppRev, true, true, SpecialAbuseLog::VISIBILITY_VISIBLE ];
		yield 'Hidden entry, all suppressed rev, cannot see hidden, can see suppressed' =>
			[ $hiddenRow, $allSuppRev, false, true, SpecialAbuseLog::VISIBILITY_HIDDEN ];
	}

	/** @dataProvider provideGetPrivateDetailsRowForFatalStatus */
	public function testGetPrivateDetailsRowForFatalStatus( $id, $authorityHasRights, $expectedErrorMessage ) {
		if ( $authorityHasRights ) {
			$authority = $this->mockRegisteredUltimateAuthority();
		} else {
			$authority = $this->mockRegisteredNullAuthority();
		}
		$this->assertStatusError(
			$expectedErrorMessage,
			SpecialAbuseLog::getPrivateDetailsRow( $authority, $id )
		);
	}

	public static function provideGetPrivateDetailsRowForFatalStatus() {
		return [
			'Filter ID does not exist' => [ 1234, true, 'abusefilter-log-nonexistent' ],
			'Authority lacks rights' => [ 1, false, 'abusefilter-log-cannot-see-details' ],
		];
	}

	public function testGetPrivateDetailsRow() {
		$actualStatus = SpecialAbuseLog::getPrivateDetailsRow( $this->mockRegisteredUltimateAuthority(), 1 );
		$this->assertStatusGood( $actualStatus );
		$this->assertStatusValue(
			(object)[
				'afl_id' => '1',
				'afl_user_text' => self::$logPerformer->getName(),
				'afl_filter_id' => '1',
				'afl_global' => '0',
				'afl_timestamp' => '20240506070809',
				'afl_ip' => '1.2.3.4',
				'af_id' => '1',
				'af_public_comments' => 'Filter with protected variables',
				'af_hidden' => Flags::FILTER_USES_PROTECTED_VARS,
			],
			$actualStatus
		);
	}

	/**
	 * Calls DOMCompat::getElementById, expects that it returns a valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $id The ID to search for, excluding the "#" character
	 * @return string
	 */
	private function assertAndGetByElementId( string $html, string $id ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::getElementById( $specialPageDocument, $id );
		$this->assertNotNull( $element, "Could not find element with ID $id in $html" );
		return DOMCompat::getInnerHTML( $element );
	}

	/**
	 * Verifies that the search form is present and that it contains
	 * the expected form fields.
	 *
	 * @param string $html The HTML of the special page
	 * @param Authority $authority The Authority that was used to generate the HTML of the special page
	 */
	private function verifySearchFormFieldsValid( string $html, Authority $authority ) {
		$formHtml = $this->assertAndGetByElementId( $html, 'abusefilter-log-search' );

		$formFields = [
			'abusefilter-log-search-user',
			'abusefilter-test-period-start',
			'abusefilter-log-search-impact',
			'abusefilter-log-search-action-label',
			'abusefilter-log-search-action-taken-label',
			'abusefilter-log-search-filter',
		];
		$formFieldsExpectedToBeMissing = [];

		if ( $authority->isAllowed( 'abusefilter-hidden-log' ) ) {
			$formFields[] = 'abusefilter-log-search-entries-label';
		} else {
			$formFieldsExpectedToBeMissing[] = 'abusefilter-log-search-entries-label';
		}

		foreach ( $formFields as $field ) {
			$this->assertStringContainsString(
				'(' . $field, $formHtml, "Missing field $field from Special:AbuseLog form"
			);
		}

		foreach ( $formFieldsExpectedToBeMissing as $field ) {
			$this->assertStringNotContainsString(
				'(' . $field, $formHtml, "Field $field should be not present in Special:AbuseLog form"
			);
		}
	}

	public function testViewListOfLogsForUserLackingAccessToTheLog() {
		// Run the Special page with an authority that cannot see protected variables, as they should
		// still be able to see the log but not what filter it came from.
		[ $html ] = $this->executeSpecialPage(
			'', null, null, $this->authorityCannotUseProtectedVar
		);

		$this->verifySearchFormFieldsValid( $html, $this->authorityCannotUseProtectedVar );

		// Verify that both log entries are displayed, but that no extended details can be seen because the user
		// lacks access to the log and filter.
		$this->assertSame( 2, substr_count( $html, '(abusefilter-log-entry' ) );
		$this->assertSame( 0, substr_count( $html, '(abusefilter-log-detailedentry-meta' ) );

		// Verify some contents of the log line
		$this->assertStringContainsString( '(abusefilter-log-noactions', $html );
	}

	public function testViewListOfLogsForUserWithAccessToTheLog() {
		$authority = $this->authorityCanUseProtectedVar;
		[ $html ] = $this->executeSpecialPage( '', null, null, $authority );

		$this->verifySearchFormFieldsValid( $html, $authority );

		// Verify that both log entries are displayed along with links to look at the log, as they can access
		// the log and filter.
		$this->assertSame( 2, substr_count( $html, '(abusefilter-log-detailedentry-meta' ) );

		// Verify some contents of the log line
		$this->assertStringContainsString( '(abusefilter-changeslist-examine', $html );
		$this->assertStringContainsString( '(abusefilter-log-detailslink', $html );
		$this->assertStringContainsString( '(abusefilter-log-detailedentry-local', $html );
		$this->assertStringContainsString( '(abusefilter-log-noactions', $html );
	}

	public function testViewListOfLogsForUserWhoCanSeeFilterButNotLog() {
		$authority = $this->authorityCanUseProtectedVar;

		// Mock that old_wikitext is a protected variable and that all users with generic protected variable access
		// can see all protected variables except old_wikitext.
		$this->overrideConfigValue( 'AbuseFilterProtectedVariables', [ 'old_wikitext' ] );
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'old_wikitext', $variables ) ) {
					$returnStatus->fatal( 'test' );
				}
			}
		);

		[ $html ] = $this->executeSpecialPage( '', null, null, $authority );

		$this->verifySearchFormFieldsValid( $html, $authority );

		// Verify that a log entry is present in the page that indicates the user has access to the filter but
		// lacks access to the log due to a variable specific restriction.
		$this->assertSame(
			1, substr_count( $html, '(abusefilter-log-detailedentry-meta-without-action-links' ),
			"Unexpected number of abusefilter-log-detailedentry-meta-without-action-links in $html"
		);

		// Assert that the link to the filter and the actions taken are displayed as the user can see that.
		$this->assertStringContainsString( '(abusefilter-log-detailedentry-local', $html );
		$this->assertStringContainsString( '(abusefilter-log-noactions', $html );
	}

	public function testShowDetailsForNonExistentLogId() {
		[ $html ] = $this->executeSpecialPage(
			'12345', null, null, $this->authorityCannotUseProtectedVar
		);
		$this->assertStringContainsString( '(abusefilter-log-nonexistent', $html );
	}

	public function testShowDetailsWhenUserLacksProtectedVariablesAccess() {
		[ $html ] = $this->executeSpecialPage(
			'1', null, null, $this->authorityCannotUseProtectedVar
		);
		$this->assertStringContainsString( '(abusefilter-log-cannot-see-details', $html );
	}

	public function testShowDetailsWhenUserLacksAccessToProtectedVariableValues() {
		// Mock that all users do not have access to protected variable values for the purposes of this test.
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'user_unnamed_ip', $variables ) ) {
					$returnStatus->fatal( 'test' );
				}
			}
		);

		[ $html ] = $this->executeSpecialPage(
			'1', null, null, $this->authorityCanUseProtectedVar
		);
		$this->assertStringContainsString( '(abusefilter-examine-protected-vars-permission', $html );
	}

	public function testViewLogWhenAssociatedFilterIsGlobalAndGlobalFiltersHaveBeenDisabled() {
		// Mock FilterLookup::getFilter to throw a CentralDBNotAvailableException exception
		$mockFilterLookup = $this->createMock( FilterLookup::class );
		$mockFilterLookup->method( 'getFilter' )
			->willThrowException( new CentralDBNotAvailableException() );
		$this->setService( 'AbuseFilterFilterLookup', $mockFilterLookup );

		[ $html ] = $this->executeSpecialPage(
			'1', null, null, $this->authorityCannotUseProtectedVar
		);

		// Verify that even though the Filter details could not be fetched, the filter is still considered
		// protected (through assuming the most strict restrictions).
		$this->assertStringContainsString(
			'(abusefilter-log-cannot-see-details)',
			$html,
			'Missing protected filter access error.'
		);
	}

	public function testHideAbuseLogWhenUserLacksPermission() {
		[ $html ] = $this->executeSpecialPage(
			'hide', null, null, $this->mockRegisteredNullAuthority()
		);

		$this->assertStringContainsString( '(abusefilter-log-hide-forbidden)', $html );
	}

	public function testHideAbuseLogWhenNoLogSelected() {
		[ $html ] = $this->executeSpecialPage(
			'hide', null, null,
			$this->mockRegisteredAuthorityWithPermissions( [ 'abusefilter-hide-log' ] )
		);

		$this->assertStringContainsString( '(abusefilter-log-hide-no-selected)', $html );
	}

	public function testHideAbuseLogWhenHideIdIsInvalid() {
		[ $html ] = $this->executeSpecialPage(
			'hide',
			new FauxRequest( [ 'hideids' => '1234' ] ),
			null, $this->mockRegisteredAuthorityWithPermissions( [ 'abusefilter-hide-log' ] )
		);

		$this->assertStringContainsString( '(abusefilter-log-hide-no-selected)', $html );
	}

	public function addDBDataOnce() {
		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions that cause the tests to fail.
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
		] );

		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		// Get a testing filter
		$performer = $this->getTestSysop()->getUser();
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'rules' => 'user_unnamed_ip = "1.2.3.4"',
			] ),
			MutableFilter::newDefault()
		) );

		// Insert two hits on the filter, one with user_unnamed_ip and with old_wikitext. This so that we can
		// simulate a user having access to the filter but not the log due to a variable specific restriction.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$logPerformer = $this->getTestUser();
		self::$logPerformer = $logPerformer->getUserIdentity();
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$logPerformer->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => '~2024-1',
				'user_unnamed_ip' => '1.2.3.4',
			] )
		)->addLogEntries( [ 1 => [] ] );
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$logPerformer->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => '~2024-1',
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( SpecialAbuseLog::PAGE_NAME );
	}
}
