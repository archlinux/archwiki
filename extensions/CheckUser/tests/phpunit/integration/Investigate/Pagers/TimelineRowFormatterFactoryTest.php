<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatterFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatterFactory
 * @group CheckUser
 */
class TimelineRowFormatterFactoryTest extends MediaWikiIntegrationTestCase {

	use MockServiceDependenciesTrait;

	public function testCreateRowFormatter() {
		/** @var TimelineRowFormatterFactory $factory */
		$factory = $this->newServiceInstance( TimelineRowFormatterFactory::class, [] );
		$formatter = $factory->createRowFormatter(
			$this->createMock( User::class ),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);

		$this->assertInstanceOf( TimelineRowFormatter::class, $formatter );
	}
}
