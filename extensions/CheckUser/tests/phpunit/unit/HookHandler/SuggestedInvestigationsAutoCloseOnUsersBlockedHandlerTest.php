<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnUsersBlockedHandler;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueue;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnUsersBlockedHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseOnUsersBlockedHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private SuggestedInvestigationsAutoCloseOnUsersBlockedHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->handler = new SuggestedInvestigationsAutoCloseOnUsersBlockedHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger()
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [ false, 1, true, true ];
		yield 'block target is null' => [ true, null, true, true ];
		yield 'block is not sitewide' => [ true, 1, false, true ];
		yield 'block is not indefinite' => [ true, 1, true, false ];
	}

	/**
	 * @dataProvider provideEarlyReturnCases
	 */
	public function testEarlyReturn(
		bool $isExtensionEnabled,
		int|null $targetUserId,
		bool $isSitewide,
		bool $isIndefinite
	): void {
		$this->caseLookup->expects( $this->once() )->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( $isExtensionEnabled );
		$this->caseLookup->expects( $this->never() )->method( 'getOpenCaseIdsForUser' );
		$this->jobQueueGroup->expects( $this->never() )->method( 'lazyPush' );

		$this->handler->onBlockIpComplete(
			$this->getDbBlockMock( $targetUserId, $isSitewide, $isIndefinite ),
			$this->createMock( User::class ),
			null
		);
	}

	public function testNoJobPushedWhenUserHasNoOpenCases(): void {
		$this->caseLookup->expects( $this->once() )->method( 'areSuggestedInvestigationsEnabled' )->willReturn( true );
		$this->caseLookup->expects( $this->once() )->method( 'getOpenCaseIdsForUser' )
			->with( 1 )
			->willReturn( [] );
		$this->jobQueueGroup->expects( $this->never() )->method( 'lazyPush' );

		$this->handler->onBlockIpComplete(
			$this->getDbBlockMock( 1, true, true ),
			$this->createMock( User::class ),
			null
		);
	}

	public function testJobPushedForEachOpenCase(): void {
		$this->caseLookup->expects( $this->once() )->method( 'areSuggestedInvestigationsEnabled' )->willReturn( true );
		$this->caseLookup->expects( $this->once() )->method( 'getOpenCaseIdsForUser' )
			->with( 1 )
			->willReturn( [ 10, 20 ] );
		$this->setupJobQueueGroupForDelayedJobs();

		$this->jobQueueGroup->expects( $this->exactly( 2 ) )
			->method( 'lazyPush' )
			->with( $this->callback( function ( IJobSpecification $spec ) {
				static $expectedCaseIds = [ 10, 20 ];
				$expectedCaseId = array_shift( $expectedCaseIds );

				return $spec->getType() === SuggestedInvestigationsAutoCloseForCaseJob::TYPE
					&& $spec->getParams()['caseId'] === $expectedCaseId;
			} ) );

		$this->handler->onBlockIpComplete(
			$this->getDbBlockMock( 1, true, true ),
			$this->createMock( User::class ),
			null
		);
	}

	private function getDbBlockMock( int|null $targetUserId, bool $isSitewide, bool $isIndefinite ): DatabaseBlock {
		$block = $this->createMock( DatabaseBlock::class );
		$block->expects( $this->atMost( 1 ) )->method( 'getTargetUserIdentity' )
			->willReturn( $targetUserId !== null ? new UserIdentityValue( $targetUserId, 'TestUser' ) : null );
		$block->expects( $this->atMost( 1 ) )->method( 'isSitewide' )->willReturn( $isSitewide );
		$block->expects( $this->atMost( 1 ) )->method( 'isIndefinite' )->willReturn( $isIndefinite );

		return $block;
	}

	private function setupJobQueueGroupForDelayedJobs(): void {
		// createMock() stubs all methods including final ones, so delayedJobsEnabled() returns false
		$jobQueue = $this->createMock( JobQueue::class );
		$this->jobQueueGroup->expects( $this->exactly( 2 ) )->method( 'get' )
			->with( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )
			->willReturn( $jobQueue );
	}
}
