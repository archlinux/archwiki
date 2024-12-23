<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Hooks;

use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;
use MediaWiki\Extension\AbuseFilter\Hooks\Handlers\ChangeTagsHandler;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Hooks\Handlers\ChangeTagsHandler
 */
class ChangeTagsHandlerTest extends MediaWikiUnitTestCase {

	public function testOnListDefinedTags() {
		$condsLimitTag = 'conds-limit';
		$filtersTags = [ 'foo', 'bar' ];
		$manager = $this->createMock( ChangeTagsManager::class );
		$manager->method( 'getCondsLimitTag' )->willReturn( $condsLimitTag );
		$manager->method( 'getTagsDefinedByFilters' )->willReturn( $filtersTags );
		$handler = new ChangeTagsHandler( $manager );
		$tags = $initial = [ 'some-tag' ];
		$handler->onListDefinedTags( $tags );
		$this->assertArrayEquals( [ ...$initial, $condsLimitTag, ...$filtersTags ], $tags );
	}

	public function testOnChangeTagsListActive() {
		$condsLimitTag = 'conds-limit';
		$activeFiltersTags = [ 'foo', 'bar' ];
		$manager = $this->createMock( ChangeTagsManager::class );
		$manager->method( 'getCondsLimitTag' )->willReturn( $condsLimitTag );
		$manager->method( 'getTagsDefinedByActiveFilters' )->willReturn( $activeFiltersTags );
		$handler = new ChangeTagsHandler( $manager );
		$tags = $initial = [ 'some-tag' ];
		$handler->onChangeTagsListActive( $tags );
		$this->assertArrayEquals( [ ...$initial, $condsLimitTag, ...$activeFiltersTags ], $tags );
	}
}
