<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseOnGlobalBlockHandlerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private JobQueueGroup $jobQueueGroup;
	private GlobalBlockManager $globalBlockManager;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$this->enableSuggestedInvestigations();
		$this->jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$this->globalBlockManager = GlobalBlockingServices::wrap( $this->getServiceContainer() )
			->getGlobalBlockManager();
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->setService( 'CheckUserCrossWikiAutoCloseJobDispatcher', $this->crossWikiJobDispatcher );
	}

	public function testJobPushedForOpenCase(): void {
		$testUser = $this->getMutableTestUser()->getUserIdentity();
		$this->createCaseForUser( $testUser );

		$this->crossWikiJobDispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->with( $testUser->getName() );

		// this will trigger SuggestedInvestigationsAutoCloseOnGlobalBlockHandler::onGlobalBlockingGlobalBlockAudit
		$this->globalBlockManager->block(
			$testUser->getName(),
			'test global block',
			'infinity',
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame(
			1,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->getSize(),
			'A job should be pushed for an open case'
		);
	}

	public function testCrossWikiJobDispatchedWhenUserHasNoLocalAccount(): void {
		$username = 'NonExistentUser12345';

		$this->crossWikiJobDispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->with( $username );

		$nonLocalUserBlock = new GlobalBlock( [
			'target' => $this->getServiceContainer()->getBlockTargetFactory()->newFromString( $username ),
			'expiry' => 'infinity',
			'isAutoblock' => false,
			'enableAutoblock' => false,
			'xff' => false,
			'id' => 0,
			'byCentralId' => 0,
			'createAccount' => false,
			'blockEmail' => false,
		] );
		$this->getServiceContainer()->getHookContainer()->run(
			'GlobalBlockingGlobalBlockAudit',
			[ $nonLocalUserBlock ]
		);

		$this->assertSame(
			0,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->getSize(),
			'No local job should be enqueued for a user with no local account'
		);
	}

	private function createCaseForUser( UserIdentity $user ): int {
		return $this->getServiceContainer()
			->getService( 'CheckUserSuggestedInvestigationsCaseManager' )
			->createCase(
				[ $user ],
				[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'TestSignal', 'value', false ) ]
			);
	}
}
