<?php
/*
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
 */

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager
 * @group Database
 */
class SuggestedInvestigationsCasesPagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;

	private static User $testUser1;
	private static User $testUser2;
	private const SIGNAL = 'signalname';

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testQuery(): void {
		$caseId = $this->addCaseWithTwoUsers();
		$pager = $this->getPager( RequestContext::getMain() );

		$results = $pager->reallyDoQuery( '', 10, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 1, $results->numRows() );

		$row = $results->fetchObject();
		$this->assertSame( $caseId, (int)$row->sic_id );
		$this->assertSame( '0', $row->sic_status );
		$this->assertSame( '', $row->sic_status_reason );
		$this->assertArrayEquals(
			[ self::$testUser2->getName(), self::$testUser1->getName() ],
			array_map( static fn ( $user ) => $user->getName(), $row->users ),
			true,
			false,
			'Users row did not have the expected items in the expected order',
		);
		$this->assertArrayEquals(
			[ self::SIGNAL ],
			$row->signals,
		);
	}

	/** @dataProvider provideFormatStatusReasonCellPerformerLink */
	public function testFormatStatusReasonCellPerformerLink(
		CaseStatus $status,
		bool $hasPerformer,
		bool $expectPerformerLink
	): void {
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
		] );

		$user = $this->getMutableTestUser()->getUser();
		$this->setUserEditCount( $user, 1 );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );
		$caseManager = $this->getCaseManager();
		$caseId = $caseManager->createCase( [ $user ], [ $signal ] );

		$performerUser = $hasPerformer ? $this->getMutableTestUser()->getUser() : null;
		$caseManager->setCaseStatus(
			$caseId,
			$status,
			'test reason',
			$performerUser?->getId() ?? 0
		);

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		if ( $expectPerformerLink ) {
			$this->assertStringContainsString( '<br', $html );
			$this->assertStringContainsString( 'User_talk:', $html );
			$this->assertStringContainsString( $performerUser->getName(), $html );
		} else {
			$this->assertStringNotContainsString( 'User_talk:', $html );
		}
	}

	public static function provideFormatStatusReasonCellPerformerLink(): array {
		return [
			'Resolved with performer shows link' => [
				'status' => CaseStatus::Resolved,
				'hasPerformer' => true,
				'expectPerformerLink' => true,
			],
			'Invalid with performer shows link' => [
				'status' => CaseStatus::Invalid,
				'hasPerformer' => true,
				'expectPerformerLink' => true,
			],
			'Open with no performer does not show link' => [
				'status' => CaseStatus::Open,
				'hasPerformer' => false,
				'expectPerformerLink' => false,
			],
			'Open with performer does not show link' => [
				'status' => CaseStatus::Open,
				'hasPerformer' => true,
				'expectPerformerLink' => false,
			],
			'Resolved with no performer does not show link' => [
				'status' => CaseStatus::Resolved,
				'hasPerformer' => false,
				'expectPerformerLink' => false,
			],
			'Invalid with no performer does not show link' => [
				'status' => CaseStatus::Invalid,
				'hasPerformer' => false,
				'expectPerformerLink' => false,
			],
		];
	}

	public function testOutput(): void {
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
			MainConfigNames::LanguageCode => 'qqx',
		] );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$caseId = $this->addCaseWithTwoUsers();
		$context = $this->makeQqxContext();
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		// Mock the edit counts for our test users so that the first test user has no edits
		// and all other users have one edit
		$mockUserEditTracker = $this->createMock( UserEditTracker::class );
		$mockUserEditTracker->method( 'getUserEditCount' )
			->willReturnCallback(
				static fn ( UserIdentity $user ) => self::$testUser1->equals( $user ) ? 0 : 1
			);
		$this->setService( 'UserEditTracker', $mockUserEditTracker );

		// Expect that the global edit count is never fetched, as we are using the local one.
		// If CentralAuth is loaded, use a no-op mock. Otherwise a use of the service should
		// mean trying to access methods on `null` (which will fail)
		if ( $this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$this->setService(
				'CentralAuth.CentralAuthEditCounter',
				$this->createNoOpMock( CentralAuthEditCounter::class )
			);
		}

		$pager = $this->getPager( $context );

		$parserOutput = $pager->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user:', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-status-open)', $html );

		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser1->getName() ) .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should contain link to Special:CheckUser for the first user'
		);
		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser2->getName() ) .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should contain link to Special:CheckUser for the second user'
		);

		$this->commonTestContribsToolLinks( 'Contributions', $html );

		$this->assertStringNotContainsString(
			'checkuser-suggestedinvestigations-user-past-checks-link-text',
			$html,
			'Links to Special:CheckUserLog should not be added unless a user has been checked'
		);

		$name1 = urlencode( self::$testUser1->getName() );
		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name2 . '%0A' . $name1 .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-investigate-reason-prefill',
			$html,
			'Should contain link to Special:Investigate in the case row'
		);
		$this->assertStringContainsString( 'title="(checkuser-suggestedinvestigations-action-investigate)"', $html );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status="open"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status-reason=""', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-signals="' . self::SIGNAL . '"', $changeStatusButtonHtml );

		// Validate the timestamp cell contains the correct data and also links to the detail view
		$urlIdentifier = $this->getCaseURLIdentifier( $caseId );
		$this->assertStringContainsString(
			'Special:SuggestedInvestigations/detail/' . $urlIdentifier,
			$html,
			'The detail view link for the case is missing'
		);

		$context = RequestContext::getMain();
		$this->assertStringContainsString(
			$context->getLanguage()->userTimeAndDate( '20250403020100', $context->getUser() ),
			$html,
			'The case update timestamp is not present in the table row for the case'
		);

		// Validate that both the status reason and status cells have the associated suggested investigations case
		// ID as data attributes.
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusReasonCell );

		$statusCell = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-status'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusCell );

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$html,
			'Filter button is not present in the page or has an unexpected label'
		);
		$this->assertActiveFiltersJsConfigVar( [], $parserOutput );
	}

	public function testOutputShowsCaseUpdateTimestamp(): void {
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
			MainConfigNames::LanguageCode => 'qqx',
		] );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$updateTimestamp = '20250607080910';
		$caseId = $this->addCaseWithTwoUsers();

		$context = $this->makeQqxContext();
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		// Expect that the global edit count is never fetched, as we are using the local one.
		// If CentralAuth is loaded, use a no-op mock. Otherwise, a use of the service should
		// mean trying to access methods on `null` (which will fail)
		if ( $this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$this->setService(
				'CentralAuth.CentralAuthEditCounter',
				$this->createNoOpMock( CentralAuthEditCounter::class )
			);
		}

		// Make a change in the case, so that a new value is set for the
		// sic_updated_timestamp column.
		ConvertibleTimestamp::setFakeTime( $updateTimestamp );

		$additionalUser = $this->getMutableTestUser()->getUser();

		$caseManager = $this->getCaseManager();
		$caseManager->updateCase( $caseId, [ $additionalUser ], [] );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString(
			"data-case-id=\"{$caseId}\"",
			$changeStatusButtonHtml
		);

		// Validate the timestamp cell contains the correct data and also links to the detail view
		$urlIdentifier = $this->getCaseURLIdentifier( $caseId );
		$this->assertStringContainsString(
			'Special:SuggestedInvestigations/detail/' . $urlIdentifier,
			$html,
			'The detail view link for the case is missing'
		);

		$context = RequestContext::getMain();
		$this->assertStringContainsString(
			$context->getLanguage()->userTimeAndDate( $updateTimestamp, $context->getUser() ),
			$html,
			'The case update timestamp is not present in the table row for the case'
		);

		$data = $this->getCaseDataFromDB(
			$caseId,
			[ 'sic_created_timestamp', 'sic_updated_timestamp' ]
		);
		$this->assertNotEquals(
			$data->sic_created_timestamp,
			$data->sic_updated_timestamp,
		);
	}

	/**
	 * Verifies that the contribs toollinks are present for a two user case and that they use the provided
	 * contributions special page.
	 */
	private function commonTestContribsToolLinks(
		string $expectedContributionsSpecialPageName,
		string $html
	): void {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$contributionsToolLinks = DOMCompat::querySelectorAll( $specialPageDocument, '.mw-usertoollinks-contribs' );
		$this->assertCount( 2, $contributionsToolLinks, 'Expected two contributions toollinks' );
		foreach ( $contributionsToolLinks as $contributionsToolLink ) {
			$toolLinkHtml = DOMCompat::getOuterHTML( $contributionsToolLink );

			// Check if the tool link has the class for indicating that the user has no edits:
			// * If it does, then the first test user is the user we should expect be associated
			//   with this tool link.
			// * If it does not, then the second test user should be expected
			// This is because we mock in the test for the first test user to have no edits
			// and all other users to have one edit
			$expectedUserName = str_contains( $toolLinkHtml, 'mw-usertoollinks-contribs-no-edits' ) ?
				self::$testUser1->getName() : self::$testUser2->getName();

			$this->assertStringContainsString(
				$expectedUserName,
				$toolLinkHtml,
				"Expected tool link to be for user $expectedUserName"
			);
			$this->assertStringContainsString(
				"Special:$expectedContributionsSpecialPageName/$expectedUserName",
				$toolLinkHtml
			);
			$this->assertStringContainsString( "(contribslink: $expectedUserName)", $toolLinkHtml );
		}
	}

	/** @dataProvider provideSignalNameForVaryingSignalsArray */
	public function testSignalNameForVaryingSignalsArray( array $signals, string $expectedSignalName ): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$this->addCaseWithTwoUsers();
		$context = $this->makeQqxContext();
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		$pager = $this->getPager( $context, $signals );
		$html = $pager->getBody();

		$this->assertStringContainsString(
			$expectedSignalName,
			$html,
			'Signal was not present in the page as expected'
		);
	}

	public static function provideSignalNameForVaryingSignalsArray(): array {
		return [
			'Signals array does not have self::SIGNAL defined' => [
				'signals' => [],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined just as a string' => [
				[ self::SIGNAL ],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined using array format' => [
				[ [ 'name' => self::SIGNAL ] ],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined using array format with custom display name' => [
				[ [ 'name' => self::SIGNAL, 'displayName' => 'Test signal display name' ] ],
				'expectedSignalName' => 'Test signal display name',
			],
		];
	}

	public function testOutputWhenGlobalContributionsUsedAsContribsLink(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$isGlobalContributionsEnabled = $this->getServiceContainer()->getSpecialPageFactory()
			->exists( 'GlobalContributions' );
		if ( !$isGlobalContributionsEnabled ) {
			$this->markTestSkipped( 'Test requires GlobalContributions dependencies to be met' );
		}

		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => true,
			MainConfigNames::LanguageCode => 'qqx',
		] );

		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		// Mock the global edit counts for our test users so that the first test user has no edits
		// and all other users have one edit
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturnCallback(
				static fn ( CentralAuthUser $centralUser ) =>
					self::$testUser1->getName() === $centralUser->getName() ? 0 : 1
			);
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );

		// Expect that the local edit count is never fetched, as we are using the global one
		$this->setService( 'UserEditTracker', $this->createNoOpMock( UserEditTracker::class ) );

		// Check that the pager uses Special:GlobalContributions as the "contribs" tool link
		$html = $this->getPager( $context )->getBody();
		$this->commonTestContribsToolLinks( 'GlobalContributions', $html );
	}

	public function testOutputWhenUserHasBeenCheckedBefore(): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		$this->addCheckUserLogEntryForFirstUser();

		$html = $this->getPager( $context )->getBody();

		// Expect that the table pager shows the "past checks" link for the first test user
		$this->assertStringContainsString( 'Special:CheckUserLog/' . self::$testUser1->getName(), $html );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-user-past-checks-link-text: ' . self::$testUser1->getName(),
			$html
		);

		// Expect that the table pager does not show the "past checks" link for the second test user
		$this->assertStringNotContainsString( 'Special:CheckUserLog/' . self::$testUser2->getName(), $html );
		$this->assertStringNotContainsString(
			'(checkuser-suggestedinvestigations-user-past-checks-link-text: ' . self::$testUser2->getName(),
			$html
		);
	}

	/**
	 * Add a CheckUserLog entry where the target of the check is the first test user
	 * (as set by {@link self::addCaseWithTwoUsers})
	 */
	private function addCheckUserLogEntryForFirstUser(): void {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			self::$testUser2,
			'userips',
			'user',
			self::$testUser1->getName(),
			'test',
			self::$testUser1->getId()
		);
		DeferredUpdates::doUpdates();
	}

	/** @dataProvider provideToolLinksThatVaryBasedOnRights */
	public function testToolLinksThatVaryBasedOnRights(
		array $authorityRights,
		bool $shouldSeeCheckUserToolLink,
		bool $shouldSeeCheckUserLogToolLink
	) {
		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredAuthorityWithPermissions( $authorityRights ) );
		$context->setLanguage( 'qqx' );

		$this->addCheckUserLogEntryForFirstUser();

		// If the user has the 'checkuser' right, then the tool links should include a link to
		// Special:CheckUser. Otherwise the tool link should not be displayed
		$html = $this->getPager( $context )->getBody();

		if ( $shouldSeeCheckUserToolLink ) {
			$this->assertStringContainsString(
				'checkuser-suggestedinvestigations-user-check-link-text',
				$html,
				'Should have checkuser tool link as user has the checkuser right'
			);
		} else {
			$this->assertStringNotContainsString(
				'checkuser-suggestedinvestigations-user-check-link-text',
				$html,
				'Should not have checkuser tool link as user lacks the checkuser right'
			);
		}

		if ( $shouldSeeCheckUserLogToolLink ) {
			$this->assertStringContainsString(
				'checkuser-suggestedinvestigations-user-past-checks-link-text',
				$html,
				'Should have checkuser log tool link as user has the checkuser right'
			);
		} else {
			$this->assertStringNotContainsString(
				'checkuser-suggestedinvestigations-user-past-checks-link-text',
				$html,
				'Should not have checkuser log tool link as user lacks the checkuser right'
			);
		}
	}

	public static function provideToolLinksThatVaryBasedOnRights(): array {
		return [
			'User has the checkuser and checkuser-log rights' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser', 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser-log right' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => false,
			],
			'User lacks the checkuser right' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser and checkuser-log rights' => [
				'authorityRights' => [ 'checkuser-suggested-investigations' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => false,
			],
		];
	}

	public function testOutputWhenCaseIdFilterSet(): void {
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$firstCaseId = $this->addCaseWithTwoUsers();
		$secondCaseId = $this->addCaseWithTwoUsers();

		// Update the cases to have a specific reason, so that we can assert that the first case and not the
		// second case is shown in the page
		$this->getCaseManager()->setCaseStatus( $firstCaseId, CaseStatus::Open, 'first case reason' );
		$this->getCaseManager()->setCaseStatus( $secondCaseId, CaseStatus::Invalid, 'second case reason' );

		$context = $this->makeQqxContext();

		// Test that only the case ID filter is applied when using the case ID filter (T421312)
		$context->getRequest()->setVal( 'status', 'invalid' );
		$context->getRequest()->setVal( 'hideCasesWithNoBlockedUsers', 1 );

		$pager = $this->getPager( $context, [], $firstCaseId );

		$html = $pager->getFullOutput()->getContentHolder()->getAsHtmlString();

		// Test that only the first case is shown.
		// Two <tr> elements will be present when this happens (1 data row + 1 header row)
		$this->assertSame( 2, substr_count( $html, '<tr' ) );
		$this->assertStringContainsString( 'first case reason', $html );
		$this->assertStringNotContainsString( 'second case reason', $html );

		// When filtering by case ID, no columns should be sortable
		$this->assertStringNotContainsString( 'cdx-table__table__cell--has-sort', $html );

		// No link to the detail view should be present when on that detail view page, but the
		// case creation timestamp should still be shown
		$this->assertStringNotContainsString( 'Special:SuggestedInvestigations/detail/', $html );

		$context = RequestContext::getMain();
		$this->assertStringContainsString(
			$context->getLanguage()->userTimeAndDate( '20250403020100', $context->getUser() ),
			$html,
			'The case creation timestamp is not present in the table row for the case'
		);

		$this->assertStringNotContainsString(
			'cdx-table__header',
			$html,
			'Detailed view should not have the table header element'
		);
		$this->assertStringNotContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button',
			$html,
			'Detailed view should not have the filter button'
		);
	}

	public function testOutputWhenUsersHidden(): void {
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
			MainConfigNames::LanguageCode => 'qqx',
		] );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		// Get a case with one of the users blocked with a 'hideuser' block
		$this->addCaseWithTwoUsers();
		$this->placeHideUserBlock( self::$testUser1 );

		// Load the special page with a user who cannot see hidden users
		$context = $this->makeQqxContext();
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );

		$pager = $this->getPager( $context );

		$html = $pager->getFullOutput()->getContentHolder()->getAsHtmlString();

		$this->assertStringContainsString(
			'(rev-deleted-user)',
			$html,
			'First test username should be replaced with the rev-deleted-user message'
		);

		$this->assertStringNotContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser1->getName() ) .
			'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should not contain link to Special:CheckUser for the first user'
		);
		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser2->getName() ) .
			'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should contain link to Special:CheckUser for the second user'
		);

		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name2 .
			'&amp;reason=%28checkuser-suggestedinvestigations-user-investigate-reason-prefill',
			$html,
			'Should contain link to Special:Investigate in the case row with only the second user'
		);

		$this->assertStringNotContainsString(
			self::$testUser1->getName(),
			$html,
			'As the first test user is not visible by the viewing authority, ' .
				'their name should be not visible anywhere on the page'
		);
	}

	public function testInvestigateDisabledWhenAllUsersSuppressed(): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		// Get a case with all users blocked with a 'hideuser' block
		$this->addCaseWithTwoUsers();
		$this->placeHideUserBlock( self::$testUser1 );
		$this->placeHideUserBlock( self::$testUser2 );

		// Load the special page with a user who cannot see hidden users
		$context = $this->makeQqxContext();
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		$this->assertStringContainsString(
			'(rev-deleted-user)',
			$html,
			'All usernames should be replaced with the rev-deleted-user message'
		);

		$investigateButtonHtml = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-investigate-action'
		);
		$this->assertStringContainsString(
			'title="(checkuser-suggestedinvestigations-action-investigate)"',
			$investigateButtonHtml
		);
		$this->assertStringContainsString( 'cdx-button--fake-button--disabled', $investigateButtonHtml );

		$this->assertStringNotContainsString(
			self::$testUser1->getName(),
			$html,
			'As the first test user is not visible by the viewing authority, ' .
			'their name should be not visible anywhere on the page'
		);
		$this->assertStringNotContainsString(
			self::$testUser2->getName(),
			$html,
			'As the second test user is not visible by the viewing authority, ' .
			'their name should be not visible anywhere on the page'
		);
	}

	public function testInvestigateDisabledWhenTooManyUsers(): void {
		$caseId = $this->addCaseWithManyUsers();

		$context = $this->makeQqxContext();

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringNotContainsString( '?title=Special:Investigate', $html );

		$usersLimit = SpecialInvestigate::MAX_TARGETS;
		$this->assertStringContainsString(
			'title="(checkuser-suggestedinvestigations-action-investigate-disabled: ' . $usersLimit . ')"',
			$html
		);

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
	}

	/** @dataProvider provideStatusReasonDisplayedInPager */
	public function testStatusReasonDisplayedInPager(
		CaseStatus $caseStatus,
		string $reasonInDatabase,
		string $reasonDisplayedInPager
	) {
		$caseId = $this->addCaseWithTwoUsers();

		$this->getCaseManager()->setCaseStatus( $caseId, $caseStatus, $reasonInDatabase );

		$context = $this->makeQqxContext();

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		// Validate that the status reason contains the default for the invalid status
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html,
			'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( $reasonDisplayedInPager, $statusReasonCell );
	}

	public static function provideStatusReasonDisplayedInPager(): array {
		return [
			'Empty reason in database for invalid case' => [
				CaseStatus::Invalid, '', '(checkuser-suggestedinvestigations-status-reason-default-invalid)',
			],
			'Non-empty reason in database for invalid case' => [ CaseStatus::Invalid, 'testingabc', 'testingabc' ],
			'Empty reason in database for resolved case' => [ CaseStatus::Resolved, '', '' ],
			'Non-empty reason in database for open case' => [ CaseStatus::Open, 'testingabc', 'testingabc' ],
		];
	}

	public function testStatusReasonHasWikitext(): void {
		$wikitextReason = '[[Test]]';
		$this->testStatusReasonDisplayedInPager(
			CaseStatus::Open,
			$wikitextReason,
			$this->getServiceContainer()->getCommentFormatter()->format( $wikitextReason )
		);
	}

	public function testWhenStatusFilterIsSet(): void {
		$caseManager = $this->getCaseManager();

		$this->setUserEditCount( $this->getTestUser()->getUserIdentity(), 1 );

		// Create two cases, where one is then closed
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		$caseManager->setCaseStatus( $firstCaseId, CaseStatus::Resolved );

		// Load the pager with the 'status' query parameter set to 'open'
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'status', 'open' );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows the open case by checking
		// only the first case ID is present as a data attribute
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$html,
			'Filter button is not present in the page or has an unexpected label'
		);
		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$html,
			'The info chip indicating how many filters were applied was not present'
		);
		$this->assertActiveFiltersJsConfigVar( [ 'status' => [ 'open' ] ], $parserOutput );
	}

	public function testWhenUsernameFilterIsSet(): void {
		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		foreach ( [ $firstUser, $secondUser ] as $user ) {
			$this->setUserEditCount( $user, 1 );
		}

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Load the pager with the 'username' query set to the first user's username
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'username', $firstUser->getName() );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the first case by checking
		// only the first case ID is present as a data attribute
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'username' => [ $firstUser->getName() ] ], $parserOutput );
	}

	public function testWhenUsernameFilterUsesUnknownUsername(): void {
		// Create a case for an existing user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$this->getCaseManager()->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		// Load the pager with the 'username' query set to a string that isn't an existing username
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'username', __METHOD__ . wfRandomString() );

		$html = $this->getPager( $context )->getBody();

		// Expect that the table pager has no results by looking for the table_pager_empty
		// message key and checking that no data-case-id attributes exist in the page
		$this->assertStringContainsString( '(table_pager_empty)', $html );
		$this->assertStringNotContainsString( 'data-case-id', $html );
	}

	/** @dataProvider provideWhenUsernameFilterUsesHiddenUsername */
	public function testWhenUsernameFilterUsesHiddenUsername(
		array $performerPermissions,
		bool $expectVisible
	): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		// Create a case with one user, then hide that user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$user = $this->getMutableTestUser()->getUser();

		$this->setUserEditCount( $user, 1 );

		$caseId = $this->getCaseManager()->createCase( [ $user ], [ $signal ] );

		$this->placeHideUserBlock( $user );

		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'username', $user->getName() );
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( $performerPermissions ) );

		$html = $this->getPager( $context )->getBody();

		if ( $expectVisible ) {
			$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $html );
			$this->assertStringContainsString( $user->getName(), $html );
		} else {
			$this->assertStringContainsString( '(table_pager_empty)', $html );
			$this->assertStringNotContainsString( 'data-case-id', $html );
		}
	}

	public static function provideWhenUsernameFilterUsesHiddenUsername(): array {
		return [
			'without hideuser permission' => [ [ 'hideuser' ], false ],
			'with hideuser permission' => [ [], true ],
		];
	}

	public function testWhenUsernameFilterIncludesHiddenAndVisibleUsername(): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUser();
		$secondUser = $this->getMutableTestUser()->getUser();

		foreach ( [ $firstUser, $secondUser ] as $user ) {
			$this->setUserEditCount( $user, 1 );
		}

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Hide the first user
		$this->placeHideUserBlock( $firstUser );

		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'username', [ $firstUser->getName(), $secondUser->getName() ] );
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );

		$html = $this->getPager( $context )->getBody();

		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
	}

	/** @dataProvider provideLimitValues */
	public function testWhenHideCasesWithNoUserEditsFilterIsSetForLocalEditCounts( int $limit ) {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', false );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		// Mock that the first test user has 2 edits
		$this->setUserEditCount( $firstUser, 2 );

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Load the pager with the 'hideCasesWithNoUserEdits' query param set to 1
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 1 );
		$context->getRequest()->setVal( 'limit', $limit );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the first case, as only the first case
		// has users with edits in it.
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoUserEdits' => true ], $parserOutput );
		$this->assertFalse(
			$jsConfigVars['wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed'],
			'Value of JS config var wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed ' .
				' is not as expected'
		);
	}

	public function testWhenNoFiltersSetHideCasesWithNoUserEditsDefaultsToTrue(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', false );

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		$this->setUserEditCount( $firstUser, 2 );

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Load the pager with no query params set
		$context = $this->makeQqxContext();

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows the first case, as only the first case
		// has users with edits in it (hideCasesWithNoUserEdits defaults to true).
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [], $parserOutput );
	}

	public static function provideLimitValues(): array {
		return [
			'Limit of 1' => [ 1 ],
			'Limit of 2' => [ 2 ],
			'Limit of 10' => [ 10 ],
		];
	}

	/** @dataProvider provideLimitValues */
	public function testWhenHideCasesWithNoUserEditsFilterIsSetForGlobalEditCounts( int $limit ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$isGlobalContributionsEnabled = $this->getServiceContainer()->getSpecialPageFactory()
			->exists( 'GlobalContributions' );
		if ( !$isGlobalContributionsEnabled ) {
			$this->markTestSkipped( 'Test requires GlobalContributions dependencies to be met' );
		}

		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => true,
			MainConfigNames::LanguageCode => 'qqx',
		] );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		$firstCaseId = $this->getCaseManager()->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $this->getCaseManager()->createCase( [ $secondUser ], [ $signal ] );

		// Mock that the second test user has an edit and all others do not
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturnCallback(
				static fn ( CentralAuthUser $centralUser ) =>
					$secondUser->getName() === $centralUser->getName() ? 1 : 0
			);
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );

		// Load the pager with the 'hideCasesWithNoUserEdits' query param set to 1
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 1 );
		$context->getRequest()->setVal( 'limit', $limit );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the second case, as only the second case
		// has users with edits in it.
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoUserEdits' => true ], $parserOutput );
		$this->assertTrue(
			$jsConfigVars['wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed'],
			'Value of JS config var wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed ' .
			' is not as expected'
		);
	}

	public function testWhenHideCasesWithNoUserEditsFilterIsSetForMultipleMainQueries(): void {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', false );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		// Mock that the first test user has 2 edits
		$this->setUserEditCount( $firstUser, 2 );

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );
		$thirdCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );
		$fourthCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );

		// Load the pager with the 'hideCasesWithNoUserEdits' query param set to 1
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 1 );
		$context->getRequest()->setVal( 'limit', 2 );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager shows the first and fourth case
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $thirdCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $fourthCaseId . '"', $html );

		// Check that the rows are ordered correctly by ensuring that the first case ID is present
		// after the third using regex
		$this->assertMatchesRegularExpression(
			'/' . preg_quote( 'data-case-id="' . $fourthCaseId . '"', '/' ) . '[\s\S]*'
				. preg_quote( 'data-case-id="' . $firstCaseId . '"', '/' ) . '/',
			$html,
			'Case with ID of 1 should be before the case with ID of 3'
		);

		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoUserEdits' => true ], $parserOutput );
	}

	public function testWhenHideCasesWithNoBlockedUsersFilterIsSet(): void {
		// Create three cases where:
		// * The first case has a user that is not blocked
		// * The second case has a user that is blocked with an indefinite block
		// * The third case has a user that is blocked temporarily and an unblocked user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();
		$thirdUser = $this->getMutableTestUser()->getUserIdentity();
		$this->setUserEditCount( $secondUser, 1 );
		$this->setUserEditCount( $thirdUser, 1 );

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );
		$thirdCaseId = $caseManager->createCase( [ $thirdUser ], [ $signal ] );
		$caseManager->updateCase( $thirdCaseId, [ $firstUser ], [] );

		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$secondUser,
				$this->mockRegisteredUltimateAuthority(),
				'indefinite'
			)
			->placeBlock();
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$thirdUser,
				$this->mockRegisteredUltimateAuthority(),
				'3 months'
			)
			->placeBlock();

		// Load the pager with the 'hideCasesWithNoBlockedUsers' query param set to 1
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'hideCasesWithNoBlockedUsers', 1 );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager shows the second and third case, as these contain at least one user
		// with an active block
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $thirdCaseId . '"', $html );

		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoBlockedUsers' => true ], $parserOutput );
	}

	public function testWhenPHPFiltersLimitReached(): void {
		$context = $this->makeQqxContext();

		$pager = $this->getPager( $context );

		// Actually hitting the limit would be expensive for tests, as we would need to create around
		// 1,000 testing rows. Therefore, we should just fake that this has been reached.
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->phpFiltersLimitReached = true;

		// Added via IContextSource::getOutput::addHTML to make sure it appears above the Codex table, however,
		// we need to call ::getFullOutput first so that IContextSource::getOutput::addHTML is actually called
		$pager->getFullOutput();
		$html = $context->getOutput()->getHTML();

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-too-many-results-filtered-in-php)',
			$html,
			'PHP filter limit hit message should be present in the outputted HTML'
		);
		$this->assertStringContainsString(
			'ext-checkuser-suggestedinvestigations-warning-dismiss',
			$html,
			'The warning should be dismissable'
		);
	}

	/** @dataProvider provideWhenSignalFilterIsSet */
	public function testWhenSignalFilterIsSet( $urlName, $signals ): void {
		$this->setUserEditCount( $this->getTestUser()->getUserIdentity(), 1 );

		// Create two cases, with different signals
		$firstSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$secondSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'dev-signal-2',
			'Test value',
			false
		);
		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $firstSignal ] );
		$secondCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $secondSignal ] );

		// Load the pager with the 'signal' query parameter set to 'dev-signal-2'
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'signal', $urlName );

		$parserOutput = $this->getPager( $context, $signals )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows the case with the dev-signal-2 signal
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );

		$this->assertActiveFiltersJsConfigVar( [ 'signal' => [ 'dev-signal-2' ] ], $parserOutput );
	}

	public static function provideWhenSignalFilterIsSet(): array {
		return [
			'URL name is the database name of the signal' => [ 'dev-signal-2', [] ],
			'URL name is the database name of the signal with signals array defined' => [
				'dev-signal-2',
				[ [ 'name' => 'dev-signal-2', 'urlName' => 'signal-e3' ] ],
			],
			'Using the URL name of the signal with signals array defined' => [
				'signal-e3',
				[ [ 'name' => 'dev-signal-2', 'urlName' => 'signal-e3' ] ],
			],
		];
	}

	public function testWhenSignalFilterIsSetWithMultipleSignalsPerCase(): void {
		$this->setUserEditCount( $this->getTestUser()->getUserIdentity(), 1 );

		// Create two cases, with different signals
		$firstSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$secondSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'dev-signal-2',
			'Test value',
			true
		);
		$thirdSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'dev-signal-2',
			'Test value2',
			true
		);

		$caseManager = $this->getCaseManager();
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $firstSignal ] );
		$secondCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $secondSignal ] );
		$caseManager->updateCase( $secondCaseId, [], [ $thirdSignal ] );

		// Load the pager with the 'signal' query parameter set to 'dev-signal-2'
		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'signal', 'dev-signal-2' );

		$parserOutput = $this->getPager(
			$context,
			[ [ 'name' => 'dev-signal-2', 'urlName' => 'signal-e3' ] ]
		)->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows one case which should be the dev-signal-2 case
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertActiveFiltersJsConfigVar( [ 'signal' => [ 'dev-signal-2' ] ], $parserOutput );
	}

	public function testFilterIsRelayedInLimitForm(): void {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$this->setUserEditCount( $this->getTestUser()->getUserIdentity(), 1 );

		// Create two cases, where one is then closed
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );
		$caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		$caseManager->setCaseStatus( $firstCaseId, CaseStatus::Resolved );

		// Load the pager with the 'status' query parameter set to [ 'open', 'resolved' ] and an unknown parameter
		$context = $this->makeQqxContext();
		// So that we have the limit form shown in the output
		$context->getRequest()->setVal( 'limit', 1 );
		$context->getRequest()->setVal( 'status', [ 'open', 'resolved', 'unknown' => 'loremIpsum' ] );
		$context->getRequest()->setVal( 'unknownArrayField', [ 'test' ] );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		$parsedDom = DOMUtils::parseHTML( $html );
		$pager = DOMCompat::querySelector( $parsedDom, '.cdx-table-pager__start' );
		$this->assertNotNull( $pager );

		$this->assertNotNull( DOMCompat::querySelector( $pager, 'input[name="status[0]"][value="open"]' ) );
		$this->assertNotNull( DOMCompat::querySelector( $pager, 'input[name="status[1]"][value="resolved"]' ) );
		$this->assertNull( DOMCompat::querySelector( $pager, 'input[name="status[unknown]"]' ) );
		$this->assertNull( DOMCompat::querySelector( $pager, 'input[name="unknownArrayField[0]"]' ) );
	}

	/** @dataProvider provideLastUpdatedFilter */
	public function testLastUpdatedFilter( ?string $filterParam, bool $expectRecentCase, bool $expectOldCase ): void {
		ConvertibleTimestamp::setFakeTime( 1000000000 );
		$recentTimestamp = ConvertibleTimestamp::convert( TimestampFormat::MW, 1000000000 - 3600 );
		$oldTimestamp = ConvertibleTimestamp::convert( TimestampFormat::MW, 1000000000 - 100 * 86400 );

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL,
			'Test value',
			false
		);
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->setUserEditCount( $user, 1 );

		$caseManager = $this->getCaseManager();
		$oldCaseId = $caseManager->createCase( [ $user ], [ $signal ] );
		$recentCaseId = $caseManager->createCase( [ $user ], [ $signal ] );

		$this->setCaseUpdatedTimestamp( $oldCaseId, $oldTimestamp );
		$this->setCaseUpdatedTimestamp( $recentCaseId, $recentTimestamp );

		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 0 );
		if ( $filterParam !== null ) {
			$context->getRequest()->setVal( 'lastUpdated', $filterParam );
		}

		$html = $this->getPager( $context )->getBody();

		if ( $expectRecentCase ) {
			$this->assertStringContainsString(
				'data-case-id="' . $recentCaseId,
				$html,
				'Recent case should be visible'
			);
		} else {
			$this->assertStringNotContainsString(
				'data-case-id="' . $recentCaseId,
				$html,
				'Recent case should not be visible'
			);
		}

		if ( $expectOldCase ) {
			$this->assertStringContainsString(
				'data-case-id="' . $oldCaseId,
				$html,
				'Old case should be visible'
			);
		} else {
			$this->assertStringNotContainsString(
				'data-case-id="' . $oldCaseId,
				$html,
				'Old case should not be visible'
			);
		}
	}

	public static function provideLastUpdatedFilter(): array {
		return [
			'No filter — all cases returned' => [
				'filterParam' => null,
				'expectRecentCase' => true,
				'expectOldCase' => true,
			],
			'1 — only recent case within 1 day' => [
				'filterParam' => '1',
				'expectRecentCase' => true,
				'expectOldCase' => false,
			],
			'3 — only recent case within 3 days' => [
				'filterParam' => '3',
				'expectRecentCase' => true,
				'expectOldCase' => false,
			],
			'7 — only recent case within 7 days' => [
				'filterParam' => '7',
				'expectRecentCase' => true,
				'expectOldCase' => false,
			],
			'90 — only recent case within 90 days' => [
				'filterParam' => '90',
				'expectRecentCase' => true,
				'expectOldCase' => false,
			],
			'Invalid value treated as all time — all cases returned' => [
				'filterParam' => '5',
				'expectRecentCase' => true,
				'expectOldCase' => true,
			],
			'Zero treated as all time — all cases returned' => [
				'filterParam' => '0',
				'expectRecentCase' => true,
				'expectOldCase' => true,
			],
			'Negative value treated as all time — all cases returned' => [
				'filterParam' => '-7',
				'expectRecentCase' => true,
				'expectOldCase' => true,
			],
		];
	}

	public function testLastUpdatedFilterCountsTowardNumberOfFiltersApplied(): void {
		$this->addCaseWithTwoUsers();

		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'lastUpdated', '7' );

		$pager = $this->getPager( $context );
		$parserOutput = $pager->getFullOutput();
		$html = $parserOutput->getRawText();

		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$html,
			'The info chip indicating filters were applied should be present'
		);

		$this->assertActiveFiltersJsConfigVar(
			[ 'lastUpdated' => 7 ],
			$parserOutput
		);
	}

	public function testInvalidLastUpdatedFilterIsIgnored(): void {
		$this->addCaseWithTwoUsers();

		$context = $this->makeQqxContext();
		$context->getRequest()->setVal( 'lastUpdated', '5' );

		$pager = $this->getPager( $context );
		$parserOutput = $pager->getFullOutput();
		$html = $parserOutput->getRawText();

		$this->assertStringNotContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$html,
			'The info chip indicating filters were applied should not be present for an invalid filter'
		);
		$this->assertActiveFiltersJsConfigVar( [ 'lastUpdated' => null ], $parserOutput );
	}

	/**
	 * Asserts that the {@link ParserOutput} has the wgCheckUserSuggestedInvestigationsActiveFilters JS
	 * config var and that is matches the expected value
	 *
	 * @param array $expected An array of properties that override the default value for these properties
	 *   in the expected array
	 */
	private function assertActiveFiltersJsConfigVar( array $expected, ParserOutput $parserOutput ): void {
		$this->assertArrayEquals(
			array_merge( [
				'status' => [],
				'username' => [],
				'hideCasesWithNoUserEdits' => true,
				'hideCasesWithNoBlockedUsers' => false,
				'signal' => [],
				'lastUpdated' => null,
			], $expected ),
			$parserOutput->getJsConfigVars()['wgCheckUserSuggestedInvestigationsActiveFilters'],
			false,
			true,
			'Active filters on the page is not as expected'
		);
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getOuterHTML( $element[0] );
	}

	private function addCaseWithTwoUsers(): int {
		self::$testUser1 = $user1 = $this->getMutableTestUser()->getUser();
		self::$testUser2 = $user2 = $this->getMutableTestUser()->getUser();

		foreach ( [ $user1, $user2 ] as $user ) {
			$this->setUserEditCount( $user, 1 );
		}

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $this->getCaseManager()->createCase( [ $user1, $user2 ], [ $signal ] );
	}

	private function addCaseWithManyUsers(): int {
		$users = [];
		for ( $i = 0; $i < SpecialInvestigate::MAX_TARGETS + 1; $i++ ) {
			$users[] = $this->getMutableTestUser()->getUser();
		}

		foreach ( $users as $user ) {
			$this->setUserEditCount( $user, 1 );
		}

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $this->getCaseManager()->createCase( $users, [ $signal ] );
	}

	private function getCaseURLIdentifier( int $caseId ): string {
		$urlIdentifier = $this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		return dechex( $urlIdentifier );
	}

	private function getCaseDataFromDB(
		int $caseId,
		array $columns
	): object {
		return $this->newSelectQueryBuilder()
			->select( $columns )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchRow();
	}

	private function getPager(
		IContextSource $context,
		array $signals = [],
		?int $caseIdFilter = null
	): SuggestedInvestigationsCasesPager {
		return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsPagerFactory' )
			->createCasesPager( $context, $signals, $caseIdFilter );
	}

	private function makeQqxContext(): RequestContext {
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		return $context;
	}

	private function placeHideUserBlock( UserIdentity $user ): void {
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$user,
				$this->mockRegisteredUltimateAuthority(),
				'indefinite',
				'Test reason',
				[ 'isHideUser' => true ]
			)
			->placeBlock();
	}

	private function getCaseManager(): SuggestedInvestigationsCaseManagerService {
		return $this->getServiceContainer()->getService(
			'CheckUserSuggestedInvestigationsCaseManager'
		);
	}

	private function setCaseUpdatedTimestamp( int $caseId, string $timestamp ): void {
		$db = $this->getDb();
		$db->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_updated_timestamp' => $db->timestamp( $timestamp ) ] )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function setUserEditCount( UserIdentity $user, int $count ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $count ] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}
}
