<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationHandler
 * @group CheckUser
 */
class UserInfoCardCacheInvalidationHandlerTest extends MediaWikiIntegrationTestCase {

	private UserInfoCardBlockStatusCache $blockStatusCache;

	protected function setUp(): void {
		parent::setUp();
		$this->blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
	}

	private function getHandler(): UserInfoCardCacheInvalidationHandler {
		return new UserInfoCardCacheInvalidationHandler(
			$this->blockStatusCache,
		);
	}

	private function createMockBlock(
		?int $targetUserId,
		bool $isSitewide
	): DatabaseBlock {
		$block = $this->createMock( DatabaseBlock::class );
		if ( $targetUserId !== null ) {
			$targetUser = new UserIdentityValue( $targetUserId, 'TestUser' );
			$block->method( 'getTargetUserIdentity' )->willReturn( $targetUser );
		} else {
			$block->method( 'getTargetUserIdentity' )->willReturn( null );
		}
		$block->method( 'isSitewide' )->willReturn( $isSitewide );
		return $block;
	}

	public function testOnBlockIpCompleteSitewideBlock(): void {
		$block = $this->createMockBlock( 42, true );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateLocal' )
			->with( 'TestUser' );

		$this->getHandler()->onBlockIpComplete(
			$block,
			$this->createMock( User::class ),
			null
		);
	}

	public function testOnBlockIpCompletePartialBlock(): void {
		$block = $this->createMockBlock( 42, false );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateLocal' );

		$this->getHandler()->onBlockIpComplete(
			$block,
			$this->createMock( User::class ),
			null
		);
	}

	public function testOnBlockIpCompleteIpBlock(): void {
		$block = $this->createMockBlock( null, true );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateLocal' );

		$this->getHandler()->onBlockIpComplete(
			$block,
			$this->createMock( User::class ),
			null
		);
	}

	public function testOnUnblockUserComplete(): void {
		$block = $this->createMock( DatabaseBlock::class );
		$block->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateLocal' )
			->with( 'TestUser' );

		$this->getHandler()->onUnblockUserComplete(
			$block,
			$this->createMock( User::class )
		);
	}

	public function testOnUnblockUserCompleteIpBlock(): void {
		$block = $this->createMock( DatabaseBlock::class );
		$block->method( 'getTargetUserIdentity' )->willReturn( null );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateLocal' );

		$this->getHandler()->onUnblockUserComplete(
			$block,
			$this->createMock( User::class )
		);
	}
}
