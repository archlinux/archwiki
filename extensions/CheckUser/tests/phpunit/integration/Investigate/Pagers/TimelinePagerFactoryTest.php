<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelinePagerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePagerFactory
 * @group CheckUser
 * @group Database
 */
class TimelinePagerFactoryTest extends MediaWikiIntegrationTestCase {

	use MockServiceDependenciesTrait;

	public function testCreatePager() {
		$factory = $this->newServiceInstance( TimelinePagerFactory::class, [
			'rowFormatterFactory' => $this->getServiceContainer()->get( 'CheckUserTimelineRowFormatterFactory' ),
		] );
		$pager = $factory->createPager( RequestContext::getMain() );

		$this->assertInstanceOf( TimelinePager::class, $pager );
	}
}
