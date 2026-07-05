<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalLockHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalLockHandler
 * @group CheckUser
 */
class UserInfoCardCacheInvalidationOnGlobalLockHandlerTest extends MediaWikiIntegrationTestCase {

	private UserInfoCardBlockStatusCache $blockStatusCache;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
	}

	private function getHandler(): UserInfoCardCacheInvalidationOnGlobalLockHandler {
		return new UserInfoCardCacheInvalidationOnGlobalLockHandler(
			$this->blockStatusCache,
		);
	}

	public function testOnCentralAuthGlobalUserLockStatusChangedToLocked(): void {
		$centralAuthUser = $this->createMock( CentralAuthUser::class );
		$centralAuthUser->method( 'getName' )->willReturn( 'TestUser' );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobal' )
			->with( 'TestUser' );

		$this->getHandler()->onCentralAuthGlobalUserLockStatusChanged( $centralAuthUser, true );
	}

	public function testOnCentralAuthGlobalUserLockStatusChangedToUnlocked(): void {
		$centralAuthUser = $this->createMock( CentralAuthUser::class );
		$centralAuthUser->method( 'getName' )->willReturn( 'TestUser' );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobal' )
			->with( 'TestUser' );

		$this->getHandler()->onCentralAuthGlobalUserLockStatusChanged( $centralAuthUser, false );
	}
}
