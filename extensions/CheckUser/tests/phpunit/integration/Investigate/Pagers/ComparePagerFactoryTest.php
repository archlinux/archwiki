<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\ComparePager;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePagerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\ComparePagerFactory
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
