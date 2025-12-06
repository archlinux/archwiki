<?php

namespace Cite\Tests\Unit;

/**
 * @covers \Cite\CiteFactory
 * @license GPL-2.0-or-later
 */
class CiteFactoryIntegrationTest extends \MediaWikiIntegrationTestCase {
	public function testNewCite() {
		$serviceContainer = $this->getServiceContainer();
		$parser = $serviceContainer->getParserFactory()->create();
		$this->assertNotNull( $serviceContainer->getService( 'Cite.CiteFactory' )->newCite( $parser ) );
	}
}
