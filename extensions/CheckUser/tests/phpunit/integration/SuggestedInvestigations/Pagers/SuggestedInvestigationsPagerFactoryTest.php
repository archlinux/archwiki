<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Pagers;

use InvalidArgumentException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsRevisionsPager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsPagerFactoryTest extends MediaWikiIntegrationTestCase {
	public function testCreateRevisionPager() {
		$this->assertInstanceOf(
			SuggestedInvestigationsRevisionsPager::class,
			$this->getObjectUnderTest()->createRevisionPager(
				RequestContext::getMain(),
				[],
				[ 123, 1234 ],
				UserIdentityValue::newRegistered( 1, 'TestUser1234' )
			),
			'SuggestedInvestigationsPagerFactory::createRevisionPager should create an ' .
				'SuggestedInvestigationsRevisionsPager instance'
		);
	}

	public function testCreateRevisionPagerWithNoRevisionIds() {
		$this->expectException( InvalidArgumentException::class );
		$this->getObjectUnderTest()->createRevisionPager( RequestContext::getMain(), [], [] );
	}

	public function testCreateCasesPager() {
		$this->assertInstanceOf(
			SuggestedInvestigationsCasesPager::class,
			$this->getObjectUnderTest()->createCasesPager( RequestContext::getMain(), [], null ),
			'SuggestedInvestigationsPagerFactory::createCasesPager should create an ' .
			'SuggestedInvestigationsCasesPager instance'
		);
	}

	private function getObjectUnderTest(): SuggestedInvestigationsPagerFactory {
		return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsPagerFactory' );
	}
}
