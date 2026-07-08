<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Jobs;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForUserJob
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseForUserJobTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private JobQueueGroup $jobQueueGroup;

	public function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();

		$this->jobQueueGroup = $this->getServiceContainer()->getService( 'JobQueueGroup' );
	}

	public function testRunWithRegisteredUserAndOpenCases(): void {
		$testUser = $this->getMutableTestUser()->getUserIdentity();
		$this->createTestCases( $testUser, 2 );

		$job = $this->createCrossWikiJob( $testUser->getName() );

		$this->assertTrue(
			$job->run(),
			'Job should execute successfully'
		);

		$this->assertEquals(
			2,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->getSize(),
			'Expected 2 auto close jobs to be added to the job queue'
		);
	}

	private function createTestCases( UserIdentity $user, int $count ): void {
		/** @var SuggestedInvestigationsCaseManagerService $caseManagerService */
		$caseManagerService = $this->getServiceContainer()
			->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$this->assertInstanceOf( SuggestedInvestigationsCaseManagerService::class, $caseManagerService );

		for ( $i = 0; $i < $count; $i++ ) {
			$caseManagerService->createCase(
				[ $user ],
				[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Signal', 'value', false ) ]
			);
		}
	}

	private function createCrossWikiJob( string $username ): SuggestedInvestigationsAutoCloseForUserJob {
		return new SuggestedInvestigationsAutoCloseForUserJob(
			[ 'username' => $username ],
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$this->jobQueueGroup,
			$this->getServiceContainer()->getService( 'CheckUserLogger' )
		);
	}
}
