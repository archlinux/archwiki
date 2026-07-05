<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\ComparePager;
use MediaWiki\Extension\CheckUser\Investigate\Pagers\ComparePagerFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Investigate\Pagers\ComparePagerFactory
 * @group CheckUser
 * @group Database
 */
class ComparePagerFactoryTest extends MediaWikiIntegrationTestCase {

	use MockServiceDependenciesTrait;

	public function testCreatePager() {
		/** @var ComparePagerFactory $factory */
		$factory = $this->newServiceInstance( ComparePagerFactory::class, [] );
		$pager = $factory->createPager( RequestContext::getMain() );

		$this->assertInstanceOf( ComparePager::class, $pager );
	}
}
