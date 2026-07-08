<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\BlockChecks;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\LocalBlockCheck;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\LocalBlockCheck
 * @group CheckUser
 */
class LocalBlockCheckTest extends MediaWikiUnitTestCase {

	private DatabaseBlockStore&MockObject $blockStore;
	private LocalBlockCheck $localBlockCheck;

	protected function setUp(): void {
		parent::setUp();

		$this->blockStore = $this->createMock( DatabaseBlockStore::class );
		$this->localBlockCheck = new LocalBlockCheck( $this->blockStore );
	}

	public function testGetIndefinitelyBlockedUserIdsBlockChecks(): void {
		$indefiniteSitewideBlock = $this->createBlockMock( 1, true, true );
		$temporaryBlock = $this->createBlockMock( 2, true, false );
		$partialBlock = $this->createBlockMock( 3, false, true );

		$this->blockStore->expects( $this->once() )
			->method( 'newListFromConds' )
			->willReturn( [ $indefiniteSitewideBlock, $temporaryBlock, $partialBlock ] );

		$this->assertSame( [ 1 ], $this->localBlockCheck->getIndefinitelyBlockedUserIds( [ 1, 2, 3, 4 ] ) );
	}

	public function testGetBlockedUserIdsBlockChecks(): void {
		$indefiniteSitewideBlock = $this->createBlockMock( 1, true, true );
		$temporaryBlock = $this->createBlockMock( 2, true, false );
		$partialBlock = $this->createBlockMock( 3, false, true );

		$this->blockStore->expects( $this->once() )
			->method( 'newListFromConds' )
			->willReturn( [ $indefiniteSitewideBlock, $temporaryBlock, $partialBlock ] );

		$this->assertSame( [ 1, 2, 3 ], $this->localBlockCheck->getBlockedUserIds( [ 1, 2, 3, 4 ] ) );
	}

	private function createBlockMock( int $userId, bool $isSitewide, bool $isIndefinite ): DatabaseBlock&MockObject {
		$userIdentity = new UserIdentityValue( $userId, 'TestUser' . $userId );

		$block = $this->createMock( DatabaseBlock::class );
		$block->expects( $this->once() )
			->method( 'getTargetUserIdentity' )
			->willReturn( $userIdentity );
		$block->method( 'isSitewide' )
			->willReturn( $isSitewide );
		$block->method( 'isIndefinite' )
			->willReturn( $isIndefinite );

		return $block;
	}
}
