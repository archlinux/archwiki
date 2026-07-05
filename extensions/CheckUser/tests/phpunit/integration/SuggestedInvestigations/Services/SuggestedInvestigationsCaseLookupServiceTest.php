<?php
/**
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

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCase
 * @group Database
 */
class SuggestedInvestigationsCaseLookupServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private static int $openCase;
	private static int $secondOpenCase;
	private static int $closedCase;
	private static int $badStatusCase;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testGetCasesWhenSuggestedInvestigationsDisabled() {
		$this->disableSuggestedInvestigations();
		$service = $this->createService();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Suggested Investigations is not enabled' );
		$service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false )
		);
	}

	/** @dataProvider provideLookupForOpenCaseWithNoFilter */
	public function testLookupForOpenCaseWithNoFilter( array $equivalentNamesForMerging ) {
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'Lorem',
			'ipsum',
			false,
			equivalentNamesForMerging: $equivalentNamesForMerging
		);

		$service = $this->createService();
		$cases = $service->getCasesForSignal( $signal );

		$this->assertCount( 1, $cases );
		$this->assertSame( self::$openCase, $cases[0]->getId() );
	}

	public static function provideLookupForOpenCaseWithNoFilter(): array {
		return [
			'No equivalent names in signal' => [ [] ],
			'Equivalent names in signal would have included extra cases if checked' => [ [ 'Ipsum' ] ],
		];
	}

	/** @dataProvider provideLookupForClosedCaseWithFilter */
	public function testLookupForClosedCaseWithFilter( bool $onlyOpen ) {
		$service = $this->createService();

		$statusFilter = $onlyOpen ? [ CaseStatus::Open ] : [ CaseStatus::Open, CaseStatus::Resolved ];

		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
			$statusFilter
		);

		if ( $onlyOpen ) {
			$this->assertCount( 0, $cases );
		} else {
			$this->assertCount( 1, $cases );
			$this->assertSame( self::$closedCase, $cases[0]->getId() );
			$this->assertSame( CaseStatus::Resolved, $cases[0]->getStatus() );
			$this->assertSame( 'Test reason', $cases[0]->getReason() );
			$this->assertSame( 0, $cases[0]->getStatusChangedBy() );
		}
	}

	public static function provideLookupForClosedCaseWithFilter(): array {
		return [
			'Looks up only for open cases' => [ 'onlyOpen' => true ],
			'Looks up for all cases' => [ 'onlyOpen' => false ],
		];
	}

	public function testLookupForCaseWithEmptyFilter() {
		$service = $this->createService();

		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
			[]
		);

		$this->assertCount( 0, $cases );
	}

	public function testLookupWithBadCaseStatus() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'Invalid status "{status}" of a Suggested Investigations case with id "{caseId}"',
				[
					'status' => 99,
					'caseId' => self::$badStatusCase,
				]
			);
		$this->setLogger( 'CheckUser', $logger );

		$service = $this->createService();
		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Bad status', 'value', false )
		);

		$this->assertCount( 0, $cases );
	}

	public function testGetCasesForSignalWithNegativeSignalMatch() {
		$service = $this->createService();
		$signal = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'Lorem' );

		$this->expectException( InvalidArgumentException::class );
		$service->getCasesForSignal( $signal );
	}

	public function testGetMergeableCasesForSignalWithNegativeSignalMatch() {
		$service = $this->createService();
		$signal = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'Lorem' );

		$this->expectException( InvalidArgumentException::class );
		$service->getMergeableCasesForSignal( $signal );
	}

	/** @dataProvider provideGetMergeableCasesForSignal */
	public function testGetMergeableCasesForSignal(
		array $equivalentNamesForMerging,
		callable $expectedCaseIdsCallback
	) {
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'Lorem',
			'ipsum',
			false,
			equivalentNamesForMerging: $equivalentNamesForMerging
		);

		$service = $this->createService();
		$actualCases = $service->getMergeableCasesForSignal( $signal );
		$actualCaseIds = array_map( static fn ( $case ) => $case->getId(), $actualCases );

		$this->assertArrayEquals( $expectedCaseIdsCallback(), $actualCaseIds );
	}

	public static function provideGetMergeableCasesForSignal(): array {
		return [
			'No equivalent names in signal' => [ [], static fn () => [ static::$openCase ] ],
			'Equivalent names in signal will not find any other cases' => [
				[ 'test', 'testabc' ], static fn () => [ static::$openCase ],
			],
			'Equivalent names in signal will include an extra case' => [
				[ 'Ipsum', 'test' ], static fn () => [ static::$openCase, static::$secondOpenCase ],
			],
		];
	}

	/** @dataProvider provideGetCaseIdForUrlIdentifierForBadResult */
	public function testGetCaseIdForUrlIdentifierForBadResult( $urlIdentifier ) {
		$service = $this->createService();
		$this->assertFalse( $service->getCaseIdForUrlIdentifier( $urlIdentifier ) );
	}

	public static function provideGetCaseIdForUrlIdentifierForBadResult(): array {
		return [
			'URL identifier is not a valid hexadecimal string' => [ 'abcjkl' ],
			'URL identifier does not match any existing case' => [ 'abcdef12' ],
		];
	}

	public function testGetCaseIdForUrlIdentifierForGoodResult() {
		$openCaseUrlIdentifierAsInteger = $this->newSelectQueryBuilder()
			->select( [ 'sic_url_identifier' ] )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => self::$openCase ] )
			->caller( __METHOD__ )
			->fetchField();
		$openCaseUrlIdentifier = dechex( $openCaseUrlIdentifierAsInteger );

		$service = $this->createService();
		$this->assertSame( self::$openCase, $service->getCaseIdForUrlIdentifier( $openCaseUrlIdentifier ) );
	}

	public function testGetUserIdsInCase(): void {
		$service = $this->createService();
		$userIds = $service->getUserIdsInCase( self::$openCase );
		$this->assertSame( [ 1, 2 ], $userIds );

		$userIds = $service->getUserIdsInCase( self::$secondOpenCase );
		$this->assertSame( [ 2 ], $userIds );

		$userIds = $service->getUserIdsInCase( self::$closedCase );
		$this->assertSame( [ 1 ], $userIds );
	}

	public function testGetUserIdsInCaseForNonExistentCase(): void {
		$service = $this->createService();
		$userIds = $service->getUserIdsInCase( 999999 );
		$this->assertSame( [], $userIds );
	}

	public function testGetCaseStatusThrowsWhenNoRowFound(): void {
		$service = $this->createService();
		$caseId = 999999;

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "No case found with id $caseId" );

		$service->getCaseStatus( $caseId );
	}

	public function testGetOpenCaseIdsForUser(): void {
		$service = $this->createService();

		$caseIds = $service->getOpenCaseIdsForUser( 1 );
		$this->assertSame( [ self::$openCase ], $caseIds );

		$caseIds = $service->getOpenCaseIdsForUser( 2 );
		$this->assertEqualsCanonicalizing( [ self::$openCase, self::$secondOpenCase ], $caseIds );
	}

	public function testIsExtensionEnabled(): void {
		$service = $this->createService();

		$this->assertTrue( $service->areSuggestedInvestigationsEnabled() );
	}

	public function testIsExtensionDisabled(): void {
		$this->disableSuggestedInvestigations();

		$service = $this->createService();

		$this->assertFalse( $service->areSuggestedInvestigationsEnabled() );
	}

	public function testGetOpenCaseIdsForUserWithNoCases(): void {
		$service = $this->createService();

		$caseIds = $service->getOpenCaseIdsForUser( 999999 );
		$this->assertSame( [], $caseIds );
	}

	public function testIsUserInAnyCaseWhenNoCasesFound(): void {
		$service = $this->createService();
		$this->assertFalse( $service->isUserInAnyCase( new UserIdentityValue( 234, 'TestUser' ) ) );
	}

	public function testIsUserInAnyCaseWhenCasesFound(): void {
		$service = $this->createService();
		$this->assertTrue( $service->isUserInAnyCase( new UserIdentityValue( 1, 'Test user 1' ) ) );
	}

	/** @dataProvider provideGetUserIdsWithCases */
	public function testGetUserIdsWithCases(
		array $inputUserIds,
		array $statusesFilter,
		array $expectedUserIds
	): void {
		$service = $this->createService();

		$this->assertEqualsCanonicalizing(
			$expectedUserIds,
			$service->getUserIdsWithCases( $inputUserIds, $statusesFilter )
		);
	}

	public static function provideGetUserIdsWithCases(): array {
		return [
			'open cases filter returns users in open cases' => [
				'inputUserIds' => [ 1, 2, 999 ],
				'statusesFilter' => [ CaseStatus::Open ],
				'expectedUserIds' => [ 1, 2 ],
			],
			'empty filter returns users in cases of any status' => [
				'inputUserIds' => [ 1, 2, 999 ],
				'statusesFilter' => [],
				'expectedUserIds' => [ 1, 2 ],
			],
			'empty user IDs input returns empty array' => [
				'inputUserIds' => [],
				'statusesFilter' => [],
				'expectedUserIds' => [],
			],
			'resolved filter returns only users in resolved cases' => [
				'inputUserIds' => [ 1, 2, 999 ],
				'statusesFilter' => [ CaseStatus::Resolved ],
				'expectedUserIds' => [ 1 ],
			],
		];
	}

	public function testGetUserIdsWithCasesThrowsWhenSIDisabled(): void {
		$this->disableSuggestedInvestigations();

		$service = $this->createService();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Suggested Investigations is not enabled' );
		$service->getUserIdsWithCases( [ 1 ] );
	}

	public function addDBDataOnce(): void {
		$this->enableSuggestedInvestigations();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$user1 = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$user2 = UserIdentityValue::newRegistered( 2, 'Test user 2' );

		self::$openCase = $caseManager->createCase(
			[ $user1, $user2 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
			]
		);
		// Add the same signal again to the open case, but use a different trigger ID to test that
		// ::getCasesForSignal only returns the open case once
		$caseManager->updateCase(
			self::$openCase,
			[],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult(
					'Lorem',
					'ipsum',
					false,
					123,
					'revision'
				),
			]
		);

		self::$secondOpenCase = $caseManager->createCase(
			[ $user2 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Ipsum', 'ipsum', false ),
			]
		);

		self::$closedCase = $caseManager->createCase(
			[ $user1 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
			]
		);
		$caseManager->setCaseStatus( self::$closedCase, CaseStatus::Resolved, 'Test reason' );

		self::$badStatusCase = $caseManager->createCase(
			[ $user1 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Bad status', 'value', false ),
			]
		);
		// Directly manipulate the DB to set a bad status
		$this->getDB()->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_status' => 99 ] )
			->where( [ 'sic_id' => self::$badStatusCase ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function createService(): SuggestedInvestigationsCaseLookupService {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' );
	}
}
