<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Filter;

use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\HistoryFilter;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\Filter\HistoryFilter
 */
class HistoryFilterTest extends MediaWikiUnitTestCase {
	public function testGetID() {
		$historyID = 163;
		$filter = new HistoryFilter(
			$this->createMock( Specs::class ),
			$this->createMock( Flags::class ),
			[],
			$this->createMock( LastEditInfo::class ),
			1,
			$historyID
		);

		$this->assertSame( $historyID, $filter->getHistoryID() );
	}
}
