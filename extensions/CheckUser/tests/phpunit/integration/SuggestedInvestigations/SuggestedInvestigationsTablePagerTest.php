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

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations;

use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager
 * @group Database
 */
class SuggestedInvestigationsTablePagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;

	private static User $testUser1;
	private static User $testUser2;
	private const SIGNAL = 'signalname';

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testQuery() {
		$caseId = $this->addCaseWithTwoUsers();
		$pager = $this->getPager( RequestContext::getMain() );

		$results = $pager->reallyDoQuery( '', 10, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 1, $results->numRows() );

		$row = $results->fetchObject();
		$this->assertSame( $caseId, (int)$row->sic_id );
		$this->assertSame( '0', $row->sic_status );
		$this->assertSame( '', $row->sic_status_reason );
		$this->assertArrayEquals(
			[ self::$testUser1->getName(), self::$testUser2->getName() ],
			$row->users,
		);
		$this->assertArrayEquals(
			[ [ 'name' => self::SIGNAL, 'value' => 'Test value' ] ],
			$row->signals,
		);
	}

	public function testOutput() {
		$caseId = $this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );
		$html = $pager->getBody();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user-check:', $html );
		$this->assertStringContainsString( 'Special:CheckUser/' . self::$testUser1->getName(), $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-status-open)', $html );

		$name1 = urlencode( self::$testUser1->getName() );
		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name1 . '%0A' . $name2,
			$html
		);
		$this->assertStringContainsString( 'title="(checkuser-suggestedinvestigations-action-investigate)"', $html );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status="open"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status-reason=""', $changeStatusButtonHtml );

		// Validate that both the status reason and status cells have the associated suggested investigations case
		// ID as data attributes.
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusReasonCell );

		$statusCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusCell );
	}

	public function testOutputWhenUsersHidden() {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		// Get a case with one of the users blocked with a 'hideuser' block
		$this->addCaseWithTwoUsers();
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$testUser1,
				$this->mockRegisteredUltimateAuthority(),
				'indefinite',
				'Test reason',
				[ 'isHideUser' => true ]
			)
			->placeBlock();

		// Load the special page with a user who cannot see hidden users
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );

		$pager = $this->getPager( $context );
		$html = $pager->getFullOutput()->getContentHolder()->getAsHtmlString();

		$this->assertStringContainsString(
			'(rev-deleted-user)',
			$html,
			'First test username should be replaced with the rev-deleted-user message'
		);

		$this->assertStringNotContainsString(
			'Special:CheckUser/' . self::$testUser1->getName(),
			$html,
			'Should not contain link to Special:CheckUser for the first user'
		);
		$this->assertStringContainsString(
			'Special:CheckUser/' . self::$testUser2->getName(),
			$html,
			'Should contain link to Special:CheckUser for the second user'
		);

		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name2,
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

	public function testInvestigateDisabledWhenTooManyUsers() {
		$caseId = $this->addCaseWithManyUsers();

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );
		$html = $pager->getBody();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringNotContainsString( '?title=Special:Investigate', $html );

		$usersLimit = SpecialInvestigate::MAX_TARGETS;
		$this->assertStringContainsString(
			'title="(checkuser-suggestedinvestigations-action-investigate-disabled: ' . $usersLimit . ')"',
			$html );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
	}

	/** @dataProvider provideDefaultReasonWhenStatusIsInvalid */
	public function testDefaultReasonWhenStatusIsInvalid( $reasonInDatabase, $reasonDisplayedInPager ) {
		$caseId = $this->addCaseWithTwoUsers();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->setCaseStatus( $caseId, CaseStatus::Invalid, $reasonInDatabase );

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );
		$html = $pager->getBody();

		// Validate that the status reason contains the default for the invalid status
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( $reasonDisplayedInPager, $statusReasonCell );
	}

	public static function provideDefaultReasonWhenStatusIsInvalid(): array {
		return [
			'Empty reason in database' => [ '', '(checkuser-suggestedinvestigations-status-reason-default-invalid)' ],
			'Non-empty reason in database' => [ 'testingabc', 'testingabc' ],
		];
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

	private function addCaseWithTwoUsers() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		self::$testUser1 = $user1 = $this->getMutableTestUser()->getUser();
		self::$testUser2 = $user2 = $this->getMutableTestUser()->getUser();

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $caseManager->createCase( [ $user1, $user2 ], [ $signal ] );
	}

	private function addCaseWithManyUsers() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$users = [];
		for ( $i = 0; $i < SpecialInvestigate::MAX_TARGETS + 1; $i++ ) {
			$users[] = $this->getMutableTestUser()->getUser();
		}

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $caseManager->createCase( $users, [ $signal ] );
	}

	private function getPager( IContextSource $context ): SuggestedInvestigationsTablePager {
		return new SuggestedInvestigationsTablePager(
			$this->getServiceContainer()->getConnectionProvider(),
			$this->getServiceContainer()->getUserLinkRenderer(),
			$this->getServiceContainer()->getUserFactory(),
			$context
		);
	}
}
