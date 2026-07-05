<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\IndefiniteBlockCheckInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker
 * @group CheckUser
 */
class CompositeIndefiniteBlockCheckerTest extends MediaWikiUnitTestCase {

	public function testGetUserIdsNotIndefinitelyBlockedBlockedAcrossMultipleChecks(): void {
		$localCheck = $this->createMock( IndefiniteBlockCheckInterface::class );
		$localCheck->expects( $this->once() )
			->method( 'getIndefinitelyBlockedUserIds' )
			->with( [ 1, 2 ] )
			->willReturn( [ 1 ] );

		$globalCheck = $this->createMock( IndefiniteBlockCheckInterface::class );
		$globalCheck->expects( $this->once() )
			->method( 'getIndefinitelyBlockedUserIds' )
			->with( [ 2 ] )
			->willReturn( [ 2 ] );

		$checker = new CompositeIndefiniteBlockChecker( [ $localCheck, $globalCheck ] );

		$this->assertSame( [], $checker->getUserIdsNotIndefinitelyBlocked( [ 1, 2 ] ) );
	}

	public function testGetUserIdsNotIndefinitelyBlockedReturnsUnblockedUsers(): void {
		$check = $this->createMock( IndefiniteBlockCheckInterface::class );
		$check->expects( $this->once() )
			->method( 'getIndefinitelyBlockedUserIds' )
			->with( [ 1, 2 ] )
			->willReturn( [ 1 ] );

		$checker = new CompositeIndefiniteBlockChecker( [ $check ] );

		$this->assertSame( [ 2 ], $checker->getUserIdsNotIndefinitelyBlocked( [ 1, 2 ] ) );
	}

	public function testNoChecksReturnsAllUsersAsUnblocked(): void {
		$checker = new CompositeIndefiniteBlockChecker( [] );

		$this->assertSame( [ 1, 2 ], $checker->getUserIdsNotIndefinitelyBlocked( [ 1, 2 ] ) );
	}
}
