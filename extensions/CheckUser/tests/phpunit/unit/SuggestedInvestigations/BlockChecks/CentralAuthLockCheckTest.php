<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck
 * @group CheckUser
 */
class CentralAuthLockCheckTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalUserSelectQueryBuilderFactory::class ) ) {
			$this->markTestSkipped( 'CentralAuth not available' );
		}
	}

	public function testEmptyInput(): void {
		$check = new CentralAuthLockCheck(
			$this->createNoOpMock( GlobalUserSelectQueryBuilderFactory::class ),
			$this->createNoOpMock( UserIdentityLookup::class )
		);

		$this->assertSame( [], $check->getIndefinitelyBlockedUserIds( [] ) );
		$this->assertSame( [], $check->getBlockedUserIds( [] ) );
	}
}
