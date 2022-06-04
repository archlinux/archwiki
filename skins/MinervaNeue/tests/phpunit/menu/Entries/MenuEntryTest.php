<?php

namespace Tests\MediaWiki\Minerva\Menu\Entries;

use MediaWiki\Minerva\Menu\Entries\MenuEntry;

/**
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Menu\Entries\MenuEntry
 */
class MenuEntryTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::getName()
	 * @covers ::getCSSClasses()
	 * @covers ::getComponents()
	 */
	public function testMenuEntryConstruction() {
		$name = 'test';
		$isJSOnly = true;
		$entry = new MenuEntry( $name, $isJSOnly );
		$this->assertSame( $name, $entry->getName() );
		$this->assertArrayEquals( [ 'jsonly' ], $entry->getCSSClasses() );
		$this->assertSame( [], $entry->getComponents() );
	}
}
