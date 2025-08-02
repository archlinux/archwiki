<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory
 * @group CheckUser
 * @group Database
 */
class PreliminaryCheckPagerFactoryTest extends MediaWikiIntegrationTestCase {

	use MockServiceDependenciesTrait;

	public function testCreatePager() {
		$factory = $this->newServiceInstance( PreliminaryCheckPagerFactory::class, [] );
		$pager = $factory->createPager( RequestContext::getMain() );

		$this->assertInstanceOf( PreliminaryCheckPager::class, $pager );
	}
}
