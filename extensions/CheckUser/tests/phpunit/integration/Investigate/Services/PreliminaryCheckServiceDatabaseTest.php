<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Services;

use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for PreliminaryCheckService class
 *
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService
 * @covers \MediaWiki\CheckUser\Investigate\Services\ChangeService
 */
class PreliminaryCheckServiceDatabaseTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	/** @dataProvider provideIsUserBlocked */
	public function testIsUserBlocked( $block ) {
		$testUser = $this->getTestUser()->getUser();
		if ( $block ) {
			$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
				$testUser,
				$this->mockRegisteredUltimateAuthority(),
				'infinity'
			)->placeBlock();
		}
		$objectUnderTest = $this->getServiceContainer()->get( 'CheckUserPreliminaryCheckService' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			$block,
			$objectUnderTest->isUserBlocked( $testUser->getId(), WikiMap::getCurrentWikiId() )
		);
	}

	public static function provideIsUserBlocked() {
		return [
			'User is blocked' => [ true ],
			'User is not blocked' => [ false ]
		];
	}
}
