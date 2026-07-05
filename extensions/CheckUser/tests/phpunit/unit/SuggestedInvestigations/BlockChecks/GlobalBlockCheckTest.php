<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck
 * @group CheckUser
 */
class GlobalBlockCheckTest extends MediaWikiUnitTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalBlock::class ) ) {
			$this->markTestSkipped( 'GlobalBlocking class is not found, skipping unit tests' );
		}
	}

	public function testApplyGlobalEarlyExit(): void {
		$check = new GlobalBlockCheck(
			$this->createNoOpMock( GlobalBlockLookup::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			false
		);

		$this->assertSame( [], $check->getIndefinitelyBlockedUserIds( [ 1 ] ) );
		$this->assertSame( [], $check->getBlockedUserIds( [ 1 ] ) );
	}

	public function testWhenUserIdsArrayIsEmpty(): void {
		$check = new GlobalBlockCheck(
			$this->createNoOpMock( GlobalBlockLookup::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			true
		);

		$this->assertSame( [], $check->getIndefinitelyBlockedUserIds( [] ) );
		$this->assertSame( [], $check->getBlockedUserIds( [] ) );
	}
}
