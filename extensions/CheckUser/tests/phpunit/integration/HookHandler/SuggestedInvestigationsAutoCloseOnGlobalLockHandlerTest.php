<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use CentralAuthTestUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TestUser;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalLockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseOnGlobalLockHandlerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private JobQueueGroup $jobQueueGroup;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$this->enableSuggestedInvestigations();
		$this->jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->setService( 'CheckUserCrossWikiAutoCloseJobDispatcher', $this->crossWikiJobDispatcher );
	}

	public function testJobPushedForOpenCase(): void {
		$testUser = $this->getMutableTestUser();
		$this->createCaseForUser( $testUser->getUserIdentity() );
		$centralAuthUser = $this->createCentralAuthUser( $testUser );

		$this->setGroupPermissions( 'sysop', 'centralauth-lock', true );
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestSysop()->getUser() );

		$this->crossWikiJobDispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->with( $testUser->getUserIdentity()->getName() );

		// Triggers SuggestedInvestigationsAutoCloseOnGlobalLockHandler::onCentralAuthGlobalUserLockStatusChanged
		$centralAuthUser->adminLockHide( true, null, 'test lock', $context );

		$this->assertSame(
			1,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->getSize(),
			'A job should be pushed for an open case'
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

	private function createCentralAuthUser( TestUser $testUser ): CentralAuthUser {
		$caTestUser = CentralAuthTestUser::newFromTestUser( $testUser );
		$caTestUser->save( $this->getDb() );

		return $caTestUser->getCentralUser();
	}
}
