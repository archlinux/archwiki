<?php

namespace MediaWiki\CheckUser\Tests\Integration\IPContributions;

use InvalidArgumentException;
use MediaWiki\CheckUser\IPContributions\IPContributionsPager;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\IPContributions\IPContributionsPagerFactory
 * @group CheckUser
 * @group Database
 */
class IPContributionsPagerFactoryTest extends MediaWikiIntegrationTestCase {
	public function testCreatePager() {
		// Tests that the factory creates an IPContributionsPager instance and does not throw an exception.
		$this->assertInstanceOf(
			IPContributionsPager::class,
			$this->getServiceContainer()->get( 'CheckUserIPContributionsPagerFactory' )
				->createPager(
					RequestContext::getMain(),
					[],
					new UserIdentityValue( 0, '127.0.0.1' )
				),
			'CheckUserIPContributionsPagerFactory::createPager should create an IPContributionsPager instance'
		);
	}

	public function testCreatePagerInvalidTarget() {
		$this->expectException( InvalidArgumentException::class );
		$this->getServiceContainer()->get( 'CheckUserIPContributionsPagerFactory' )
			->createPager(
				RequestContext::getMain(),
				[],
				$this->getTestUser()->getUser()
			);
	}
}
