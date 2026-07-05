<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Jobs;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseForCaseJobTest extends MediaWikiUnitTestCase {

	private const CASE_ID = 123;

	public function testNewSpecWithDelayedJobsEnabled(): void {
		$spec = SuggestedInvestigationsAutoCloseForCaseJob::newSpec( self::CASE_ID, true );

		$this->assertSame( SuggestedInvestigationsAutoCloseForCaseJob::TYPE, $spec->getType() );
		$this->assertSame( self::CASE_ID, $spec->getParams()['caseId'] );
		$this->assertArrayHasKey( 'jobReleaseTimestamp', $spec->getParams() );
	}

	public function testNewSpecWithDelayedJobsDisabled(): void {
		$spec = SuggestedInvestigationsAutoCloseForCaseJob::newSpec( self::CASE_ID, false );

		$this->assertSame( SuggestedInvestigationsAutoCloseForCaseJob::TYPE, $spec->getType() );
		$this->assertSame( self::CASE_ID, $spec->getParams()['caseId'] );
		$this->assertArrayNotHasKey( 'jobReleaseTimestamp', $spec->getParams() );
	}

	public function testClosedCaseEarlyReturn(): void {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Resolved );

		$caseLookUpMock->expects( $this->never() )
			->method( 'getUserIdsInCase' );

		$job = $this->createJobWithMocks(
			$this->createNoOpMock( SuggestedInvestigationsCaseManagerService::class ),
			$caseLookUpMock
		);

		$job->run();
	}

	public function testCaseWithNoUsers(): void {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Open );

		$caseLookUpMock->expects( $this->once() )
			->method( 'getUserIdsInCase' )
			->with( self::CASE_ID )
			->willReturn( [] );

		$job = $this->createJobWithMocks(
			$this->createNoOpMock( SuggestedInvestigationsCaseManagerService::class ),
			$caseLookUpMock
		);

		$job->run();
	}

	public function testCaseNotClosedWhenNotAllUsersIndefinitelyBlocked(): void {
		$caseLookUpMock = $this->getCaseLookUpMockFound();

		$blockCheckerMock = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$blockCheckerMock->expects( $this->once() )
			->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 1, 2 ] )
			->willReturn( [ 2 ] );

		$job = $this->createJobWithMocks(
			$this->createNoOpMock( SuggestedInvestigationsCaseManagerService::class ),
			$caseLookUpMock,
			$blockCheckerMock
		);

		$job->run();
	}

	public function testCaseAutoClosedOk(): void {
		$caseLookUpMock = $this->getCaseLookUpMockFound();

		$blockCheckerMock = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$blockCheckerMock->expects( $this->once() )
			->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 1, 2 ] )
			->willReturn( [] );

		$caseManagerMock = $this->createMock( SuggestedInvestigationsCaseManagerService::class );
		$caseManagerMock->expects( $this->once() )
			->method( 'setCaseStatus' )
			->with( self::CASE_ID, CaseStatus::Resolved, $this->isType( 'string' ), null );

		$job = $this->createJobWithMocks(
			$caseManagerMock,
			$caseLookUpMock,
			$blockCheckerMock
		);

		$this->assertTrue( $job->run() );
	}

	private function getCaseLookUpMockFound(): SuggestedInvestigationsCaseLookupService&MockObject {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Open );

		$caseLookUpMock->expects( $this->once() )
			->method( 'getUserIdsInCase' )
			->with( self::CASE_ID )
			->willReturn( [ 1, 2 ] );

		return $caseLookUpMock;
	}

	private function createJobWithMocks(
		SuggestedInvestigationsCaseManagerService $caseManager,
		SuggestedInvestigationsCaseLookupService $caseLookup,
		?CompositeIndefiniteBlockChecker $blockChecker = null
	): SuggestedInvestigationsAutoCloseForCaseJob {
		return new SuggestedInvestigationsAutoCloseForCaseJob(
			[ 'caseId' => self::CASE_ID ],
			$caseManager,
			$caseLookup,
			$blockChecker ?? $this->createMock( CompositeIndefiniteBlockChecker::class ),
			$this->createMock( LoggerInterface::class ),
			new FakeQqxMessageLocalizer()
		);
	}
}
