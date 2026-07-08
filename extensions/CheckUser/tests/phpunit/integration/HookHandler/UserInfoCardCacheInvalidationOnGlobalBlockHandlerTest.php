<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalBlockHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalBlockHandler
 * @group CheckUser
 */
class UserInfoCardCacheInvalidationOnGlobalBlockHandlerTest extends MediaWikiIntegrationTestCase {

	private UserInfoCardBlockStatusCache $blockStatusCache;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$this->blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
	}

	private function getHandler(): UserInfoCardCacheInvalidationOnGlobalBlockHandler {
		return new UserInfoCardCacheInvalidationOnGlobalBlockHandler(
			$this->blockStatusCache,
		);
	}

	public function testOnGlobalBlockingGlobalBlockAuditForUserBlock(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobal' )
			->with( 'TestUser' );

		$this->getHandler()->onGlobalBlockingGlobalBlockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalBlockAuditForNonRegisteredTarget(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, '127.0.0.1' ) );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateGlobal' );

		$this->getHandler()->onGlobalBlockingGlobalBlockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalUnblockAuditForUserBlock(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobal' )
			->with( 'TestUser' );

		$this->getHandler()->onGlobalBlockingGlobalUnblockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalUnblockAuditForNonRegisteredTarget(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, '127.0.0.1' ) );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateGlobal' );

		$this->getHandler()->onGlobalBlockingGlobalUnblockAudit( $globalBlock );
	}
}
