<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\BlockCheckInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeBlockChecker;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeBlockChecker
 * @group CheckUser
 */
class CompositeBlockCheckerTest extends MediaWikiUnitTestCase {

	public function testGetUserIdsNotBlockedBlockedAcrossMultipleChecks(): void {
		$localCheck = $this->createMock( BlockCheckInterface::class );
		$localCheck->expects( $this->once() )
			->method( 'getBlockedUserIds' )
			->with( [ 1, 2 ] )
			->willReturn( [ 1 ] );

		$globalCheck = $this->createMock( BlockCheckInterface::class );
		$globalCheck->expects( $this->once() )
			->method( 'getBlockedUserIds' )
			->with( [ 2 ] )
			->willReturn( [ 2 ] );

		$checker = new CompositeBlockChecker( [ $localCheck, $globalCheck ] );

		$this->assertSame( [], $checker->getUserIdsNotBlocked( [ 1, 2 ] ) );
	}

	public function testGetUserIdsNotBlockedReturnsUnblockedUsers(): void {
		$check = $this->createMock( BlockCheckInterface::class );
		$check->expects( $this->once() )
			->method( 'getBlockedUserIds' )
			->with( [ 1, 2 ] )
			->willReturn( [ 1 ] );

		$checker = new CompositeBlockChecker( [ $check ] );

		$this->assertSame( [ 2 ], $checker->getUserIdsNotBlocked( [ 1, 2 ] ) );
	}

	public function testNoChecksReturnsAllUsersAsUnblocked(): void {
		$checker = new CompositeBlockChecker( [] );

		$this->assertSame( [ 1, 2 ], $checker->getUserIdsNotBlocked( [ 1, 2 ] ) );
	}
}
