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

use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;

/**
 * @covers MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService
 * @group Database
 */
class SuggestedInvestigationsCaseLookupServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private static int $openCase;
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

	public function testLookupForOpenCaseWithNoFilter() {
		$service = $this->createService();

		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false )
		);

		$this->assertCount( 1, $cases );
		$this->assertSame( self::$openCase, $cases[0]->getId() );
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
		}
	}

	public function provideLookupForClosedCaseWithFilter(): array {
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

	public function addDBDataOnce() {
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
