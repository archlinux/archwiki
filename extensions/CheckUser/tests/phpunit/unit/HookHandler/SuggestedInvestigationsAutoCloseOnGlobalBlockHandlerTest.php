<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Block\Block;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\JobQueue\JobQueue;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseOnGlobalBlockHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;
	private SuggestedInvestigationsAutoCloseOnGlobalBlockHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalBlock::class ) ) {
			$this->markTestSkipped( 'GlobalBlocking extension is not available' );
		}

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->handler = new SuggestedInvestigationsAutoCloseOnGlobalBlockHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger(),
			$this->crossWikiJobDispatcher
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [
			'isExtensionEnabled' => false, 'targetUserId' => 1,
			'isIndefinite' => true, 'blockType' => Block::TYPE_USER,
		];
		yield 'block target is null' => [
			'isExtensionEnabled' => true, 'targetUserId' => null,
			'isIndefinite' => true, 'blockType' => Block::TYPE_USER,
		];
		yield 'block is not indefinite' => [
			'isExtensionEnabled' => true, 'targetUserId' => 1,
			'isIndefinite' => false, 'blockType' => Block::TYPE_USER,
		];
		yield 'block target is an IP address' => [
			'isExtensionEnabled' => true, 'targetUserId' => 1,
			'isIndefinite' => true, 'blockType' => Block::TYPE_IP,
		];
	}

	/**
	 * @dataProvider provideEarlyReturnCases
	 */
	public function testEarlyReturn(
		bool $isExtensionEnabled,
		int|null $targetUserId,
		bool $isIndefinite,
		int $blockType
	): void {
		$this->caseLookup
			->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( $isExtensionEnabled );
		$this->caseLookup
			->expects( $this->never() )
			->method( 'getOpenCaseIdsForUser' );

		$this->jobQueueGroup
			->expects( $this->never() )
			->method( 'lazyPush' );

		$this->crossWikiJobDispatcher
			->expects( $this->never() )
			->method( 'dispatch' );

		$this->handler->onGlobalBlockingGlobalBlockAudit(
			$this->getGlobalBlockMock( $targetUserId, $isIndefinite, $blockType )
		);
	}

	public static function provideHappyPathCases(): iterable {
		yield 'user with local account' => [ 'targetUserId' => 42, 'hasLocalAccount' => true ];
		yield 'user without local account' => [ 'targetUserId' => 0, 'hasLocalAccount' => false ];
	}

	/**
	 * @dataProvider provideHappyPathCases
	 */
	public function testCrossWikiAlwaysDispatchedAndLocalJobOnlyForLocalAccount(
		int $targetUserId,
		bool $hasLocalAccount
	): void {
		$this->caseLookup
			->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( true );

		if ( $hasLocalAccount ) {

			$this->caseLookup
				->expects( $this->once() )
				->method( 'getOpenCaseIdsForUser' )
				->with( $targetUserId )
				->willReturn( [ 1 ] );
			$this->jobQueueGroup
				->expects( $this->once() )
				->method( 'get' )
				->with( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )
				->willReturn( $this->createMock( JobQueue::class ) );
			$this->jobQueueGroup
				->expects( $this->once() )
				->method( 'lazyPush' );

		} else {

			$this->caseLookup
				->expects( $this->never() )
				->method( 'getOpenCaseIdsForUser' );
			$this->jobQueueGroup
				->expects( $this->never() )
				->method( 'lazyPush' );

		}

		$this->crossWikiJobDispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->with( 'TestUser' );

		$this->handler->onGlobalBlockingGlobalBlockAudit(
			$this->getGlobalBlockMock( $targetUserId, true )
		);
	}

	private function getGlobalBlockMock(
		int|null $targetUserId,
		bool $isIndefinite,
		int $blockType = Block::TYPE_USER
	): GlobalBlock {
		$block = $this->createMock( GlobalBlock::class );
		$block->expects( $this->atMost( 1 ) )
			->method( 'getTargetUserIdentity' )
			->willReturn( $targetUserId !== null ? new UserIdentityValue( $targetUserId, 'TestUser' ) : null );
		$block->expects( $this->atMost( 1 ) )
			->method( 'isIndefinite' )
			->willReturn( $isIndefinite );
		$block->expects( $this->atMost( 1 ) )
			->method( 'getType' )
			->willReturn( $blockType );

		return $block;
	}

}
