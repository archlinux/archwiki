<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory
 * @group CheckUser
 * @group Database
 */
class GlobalContributionsPagerFactoryTest extends MediaWikiIntegrationTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
	}

	public function testCreatePager() {
		// Tests that the factory creates a GlobalContributionsPager instance and does not throw an exception.
		$this->assertInstanceOf(
			GlobalContributionsPager::class,
			$this->getServiceContainer()->get( 'CheckUserGlobalContributionsPagerFactory' )
				->createPager(
					RequestContext::getMain(),
					[],
					new UserIdentityValue( 0, '127.0.0.1' )
				),
			'CheckUserGlobalContributionsPagerFactory::createPager should create a GlobalContributionsPager instance'
		);
	}
}
