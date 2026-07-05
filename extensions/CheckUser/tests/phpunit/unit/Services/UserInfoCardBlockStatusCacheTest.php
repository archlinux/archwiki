<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache
 * @group CheckUser
 */
class UserInfoCardBlockStatusCacheTest extends MediaWikiUnitTestCase {

	private WANObjectCache $wanCache;
	private CompositeIndefiniteBlockChecker $localBlockChecker;
	private CompositeIndefiniteBlockChecker $globalBlockChecker;
	private UserIdentityLookup $userIdentityLookup;

	protected function setUp(): void {
		parent::setUp();
		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->localBlockChecker = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$this->globalBlockChecker = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$this->userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$this->userIdentityLookup->method( 'getUserIdentityByName' )
			->with( 'TestUser' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );
	}

	private function newService(): UserInfoCardBlockStatusCache {
		return new UserInfoCardBlockStatusCache(
			$this->wanCache,
			$this->localBlockChecker,
			$this->globalBlockChecker,
			$this->userIdentityLookup,
			StatsFactory::newNull(),
		);
	}

	private function getLocalCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-local-blocked', $username );
	}

	private function getGlobalCacheKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-global-blocked', $username );
	}

	public function testInvalidateLocal(): void {
		$this->wanCache->set( $this->getLocalCacheKey( 'TestUser' ), 1 );
		$service = $this->newService();
		$service->invalidateLocal( 'TestUser' );

		$this->assertFalse(
			$this->wanCache->get( $this->getLocalCacheKey( 'TestUser' ) ),
			'Local WANObjectCache entry should be deleted after invalidateLocal()'
		);
	}

	public function testInvalidateGlobal(): void {
		$this->wanCache->set( $this->getGlobalCacheKey( 'TestUser' ), 1 );
		$service = $this->newService();
		$service->invalidateGlobal( 'TestUser' );

		$this->assertFalse(
			$this->wanCache->get( $this->getGlobalCacheKey( 'TestUser' ) ),
			'Global WANObjectCache entry should be deleted after invalidateGlobal()'
		);
	}

	public function testIsIndefinitelyBlocked_LocallyBlocked(): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 42 ] )
			->willReturn( [] );

		$service = $this->newService();
		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );
	}

	public function testIsIndefinitelyBlocked_GloballyBlocked(): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 42 ] )
			->willReturn( [ 42 ] );
		$this->globalBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 42 ] )
			->willReturn( [] );

		$service = $this->newService();
		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );
	}

	public function testIsIndefinitelyBlocked_NotBlocked(): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 42 ] )
			->willReturn( [ 42 ] );
		$this->globalBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->with( [ 42 ] )
			->willReturn( [ 42 ] );

		$service = $this->newService();
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );
	}

	public function testIsIndefinitelyBlocked_NotBlockedIsCached(): void {
		$this->localBlockChecker->expects( $this->once() )
			->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );
		$this->globalBlockChecker->expects( $this->once() )
			->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );

		$service = $this->newService();
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );

		// Second call should use process cache (pcTTL), not re-query
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );

		// Verify WANObjectCache also cached the false result (stored as 0)
		$this->assertSame(
			0,
			$this->wanCache->get( $this->getLocalCacheKey( 'TestUser' ) ),
			'Local WANObjectCache should cache 0 for unblocked users'
		);
		$this->assertSame(
			0,
			$this->wanCache->get( $this->getGlobalCacheKey( 'TestUser' ) ),
			'Global WANObjectCache should cache 0 for unblocked users'
		);
	}

	public function testIsIndefinitelyBlocked_UsesProcessCache(): void {
		$this->localBlockChecker->expects( $this->once() )
			->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [] );

		$service = $this->newService();
		$service->isIndefinitelyBlockedOrLocked( 'TestUser' );
		// Second call should not hit the block checker again (pcTTL)
		$service->isIndefinitelyBlockedOrLocked( 'TestUser' );
	}

	public function testInvalidateLocal_AfterQuery(): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );
		$this->globalBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );

		$service = $this->newService();
		// Populate cache
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );

		// Invalidate should clear local WANObjectCache entry
		$service->invalidateLocal( 'TestUser' );
		$this->assertFalse(
			$this->wanCache->get( $this->getLocalCacheKey( 'TestUser' ) ),
			'Local WANObjectCache entry should be deleted after invalidateLocal()'
		);
	}

	public function testInvalidateGlobal_AfterQuery(): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );
		$this->globalBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturn( [ 42 ] );

		$service = $this->newService();
		// Populate cache
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'TestUser' ) );

		// Invalidate should clear global WANObjectCache entry
		$service->invalidateGlobal( 'TestUser' );
		$this->assertFalse(
			$this->wanCache->get( $this->getGlobalCacheKey( 'TestUser' ) ),
			'Global WANObjectCache entry should be deleted after invalidateGlobal()'
		);
	}

	public function testIsIndefinitelyBlocked_UnknownUser(): void {
		$unknownLookup = $this->createMock( UserIdentityLookup::class );
		$unknownLookup->method( 'getUserIdentityByName' )
			->willReturn( null );

		$service = new UserInfoCardBlockStatusCache(
			$this->wanCache,
			$this->localBlockChecker,
			$this->globalBlockChecker,
			$unknownLookup,
			StatsFactory::newNull(),
		);

		$this->localBlockChecker->expects( $this->never() )
			->method( 'getUserIdsNotIndefinitelyBlocked' );
		$this->globalBlockChecker->expects( $this->never() )
			->method( 'getUserIdsNotIndefinitelyBlocked' );

		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( 'UnknownUser' ) );
	}
}
