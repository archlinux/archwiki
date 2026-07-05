<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\QueueAutoCloseSICases;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentityValue;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\QueueAutoCloseSICases
 */
class QueueAutoCloseSICasesTest extends MaintenanceBaseTestCase {
	use SuggestedInvestigationsTestTrait;

	private SuggestedInvestigationsCaseManagerService $caseManager;
	private int $userId = 0;

	public function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->caseManager = $this->getServiceContainer()
			->get( 'CheckUserSuggestedInvestigationsCaseManager' );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass(): string {
		return QueueAutoCloseSICases::class;
	}

	public function testWhenSuggestedInvestigationsIsDisabled(): void {
		$this->disableSuggestedInvestigations();
		$this->mockJobQueueGroup( 0 );

		$this->maintenance->execute();

		$this->assertStringContainsString(
			'Nothing to do',
			$this->getActualOutputForAssertion()
		);
	}

	public function testWhenNoOpenCasesExist(): void {
		$this->mockJobQueueGroup( 0 );

		$this->maintenance->execute();

		$this->assertStringContainsString(
			'Queued 0',
			$this->getActualOutputForAssertion()
		);
	}

	public function testQueuesJobsOnlyForOpenCases(): void {
		$this->createOpenCase();
		$this->createOpenCase();
		$this->resolveCase( $this->createOpenCase() );

		// 2 open cases fit in a single batch → 1 push() call
		$this->mockJobQueueGroup( 1 );

		$this->maintenance->execute();

		$this->assertStringContainsString(
			'Queued 2',
			$this->getActualOutputForAssertion()
		);
	}

	public function testBatchingProcessesAllOpenCases(): void {
		$this->createOpenCase();
		$this->createOpenCase();
		$this->createOpenCase();

		// 3 cases with --batch-size 2 → 2 batch push() calls with: [1,2] and [3]
		$this->mockJobQueueGroup( 2 );

		$this->maintenance->loadWithArgv( [ '--batch-size', '2' ] );
		$this->maintenance->execute();

		$this->assertStringContainsString(
			'Done. Queued 3 auto-close',
			$this->getActualOutputForAssertion()
		);
	}

	private function mockJobQueueGroup( int $expectedPushCount ): void {
		$mock = $this->createMock( JobQueueGroup::class );
		$mock->expects( $this->exactly( $expectedPushCount ) )
			->method( 'push' );

		$this->setService( 'JobQueueGroup', $mock );
	}

	private function createOpenCase(): int {
		$this->userId++;

		return $this->caseManager->createCase(
			[ UserIdentityValue::newRegistered( $this->userId, 'TestUser' . $this->userId ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				'test-signal',
				'value',
				false
			) ]
		);
	}

	private function resolveCase( int $caseId ): void {
		$this->caseManager->setCaseStatus( $caseId, CaseStatus::Resolved, 'test reason' );
	}
}
