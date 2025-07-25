<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStoreFactory;
use MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler
 */
class PerformRetroactiveAutoblockHandlerTest extends MediaWikiUnitTestCase {
	public function testOnPerformRetroactiveAutoblockForNonExistentUser() {
		// Create a DatabaseBlock mock instance that pretends that the target of the block is a non-existent user
		// (i.e. a user with ID 0).
		$block = $this->createMock( DatabaseBlock::class );
		$block->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, 'Testing1234' ) );
		// Call the method under test with the mock DatabaseBlock.
		$objectUnderTest = new PerformRetroactiveAutoblockHandler(
			$this->createMock( IConnectionProvider::class ),
			$this->createMock( DatabaseBlockStoreFactory::class ),
			new HashConfig( [ 'CheckUserMaximumIPsToAutoblock' => 1 ] )
		);
		$blockIds = [];
		$this->assertTrue( $objectUnderTest->onPerformRetroactiveAutoblock( $block, $blockIds ) );
		$this->assertCount(
			0, $blockIds, 'No autoblocks should be performed if the existing block target is a non-existent user'
		);
	}

	public function testOnPerformRetroactiveAutoblockWhenMaximumIPBlockSetToZero() {
		// Call the method under test with wgCheckUserMaximumIPsToAutoblock set to zero.
		$objectUnderTest = new PerformRetroactiveAutoblockHandler(
			$this->createMock( IConnectionProvider::class ),
			$this->createMock( DatabaseBlockStoreFactory::class ),
			new HashConfig( [ 'CheckUserMaximumIPsToAutoblock' => 0 ] )
		);
		$blockIds = [];
		$this->assertTrue( $objectUnderTest->onPerformRetroactiveAutoblock(
			$this->createMock( DatabaseBlock::class ), $blockIds )
		);
		$this->assertCount(
			0, $blockIds, 'No autoblocks should be performed if the maximum number of autoblocks is set to 0.'
		);
	}
}
