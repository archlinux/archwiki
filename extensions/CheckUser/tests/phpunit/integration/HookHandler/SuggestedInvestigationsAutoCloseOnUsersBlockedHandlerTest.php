<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnUsersBlockedHandler;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnUsersBlockedHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseOnUsersBlockedHandlerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private SuggestedInvestigationsAutoCloseOnUsersBlockedHandler $handler;
	private SuggestedInvestigationsCaseManagerService $caseManagerService;
	private DatabaseBlockStore $blockStore;
	private JobQueueGroup $jobQueueGroup;

	protected function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->caseManagerService = $this->getServiceContainer()
			->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$this->blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$this->jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$this->handler = new SuggestedInvestigationsAutoCloseOnUsersBlockedHandler(
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$this->jobQueueGroup,
			new NullLogger()
		);
	}

	public function testNoJobPushedForClosedCases(): void {
		$testUser = $this->getMutableTestUser()->getUserIdentity();
		$caseId = $this->createCaseForUser( $testUser );
		$this->caseManagerService->setCaseStatus( $caseId, CaseStatus::Resolved, 'test reason' );

		$block = $this->createIndefiniteSitewideBlock( $testUser );

		$this->handler->onBlockIpComplete(
			$block,
			$this->getTestSysop()->getUser(),
			null
		);

		$this->assertSame(
			0,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->getSize(),
			'No job should be pushed for closed cases'
		);
	}

	private function createCaseForUser( UserIdentity $user ): int {
		return $this->caseManagerService->createCase(
			[ $user ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'TestSignal', 'value', false ) ]
		);
	}

	private function createIndefiniteSitewideBlock( UserIdentity $user ): DatabaseBlock {
		$block = $this->blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => 'infinity',
		] );
		$this->blockStore->insertBlock( $block );

		return $block;
	}
}
