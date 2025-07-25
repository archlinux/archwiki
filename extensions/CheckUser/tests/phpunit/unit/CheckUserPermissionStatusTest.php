<?php
namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\CheckUserPermissionStatus
 */
class CheckUserPermissionStatusTest extends MediaWikiUnitTestCase {
	public function testNewBlockedError(): void {
		$block = $this->createMock( Block::class );
		$permStatus = CheckUserPermissionStatus::newBlockedError( $block );

		$this->assertStatusNotGood( $permStatus );
		$this->assertSame( $block, $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testNewPermissionError(): void {
		$permStatus = CheckUserPermissionStatus::newPermissionError( 'some-permission' );

		$this->assertStatusNotGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertSame( 'some-permission', $permStatus->getPermission() );
	}

	public function testNewFatal(): void {
		$permStatus = CheckUserPermissionStatus::newFatal( 'some-error' );

		$this->assertStatusNotGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testNewGood(): void {
		$permStatus = CheckUserPermissionStatus::newGood();

		$this->assertStatusGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}
}
