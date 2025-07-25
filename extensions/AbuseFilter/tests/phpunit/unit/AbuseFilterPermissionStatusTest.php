<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use MediaWiki\Block\Block;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWikiUnitTestCase;

/**
 * Modified copy of CheckUserPermissionStatusTest from mediawiki/extensions/CheckUser
 *
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus
 */
class AbuseFilterPermissionStatusTest extends MediaWikiUnitTestCase {
	public function testNewBlockedError(): void {
		$block = $this->createMock( Block::class );
		$permStatus = AbuseFilterPermissionStatus::newBlockedError( $block );

		$this->assertStatusNotGood( $permStatus );
		$this->assertSame( $block, $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testNewPermissionError(): void {
		$permStatus = AbuseFilterPermissionStatus::newPermissionError( 'some-permission' );

		$this->assertStatusNotGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertSame( 'some-permission', $permStatus->getPermission() );
	}

	public function testNewFatal(): void {
		$permStatus = AbuseFilterPermissionStatus::newFatal( 'some-error' );

		$this->assertStatusNotGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testNewGood(): void {
		$permStatus = AbuseFilterPermissionStatus::newGood();

		$this->assertStatusGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testSetBlock() {
		$permStatus = AbuseFilterPermissionStatus::newGood();
		$block = $this->createMock( Block::class );
		$permStatus->setBlock( $block );

		$this->assertStatusNotGood( $permStatus );
		$this->assertSame( $block, $permStatus->getBlock() );
		$this->assertNull( $permStatus->getPermission() );
	}

	public function testSetPermission() {
		$permStatus = AbuseFilterPermissionStatus::newGood();
		$permStatus->setPermission( 'test' );

		$this->assertStatusNotGood( $permStatus );
		$this->assertNull( $permStatus->getBlock() );
		$this->assertSame( 'test', $permStatus->getPermission() );
	}
}
