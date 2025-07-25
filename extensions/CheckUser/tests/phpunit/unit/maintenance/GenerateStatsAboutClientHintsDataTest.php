<?php

namespace MediaWiki\CheckUser\Tests\Unit\Maintenance;

use MediaWiki\CheckUser\Maintenance\GenerateStatsAboutClientHintsData;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\GenerateStatsAboutClientHintsData
 */
class GenerateStatsAboutClientHintsDataTest extends MediaWikiUnitTestCase {
	public function testExecute() {
		$objectUnderTest = $this->getMockBuilder( GenerateStatsAboutClientHintsData::class )
			->onlyMethods( [ 'generateCounts', 'output' ] )
			->getMock();
		$objectUnderTest->expects( $this->once() )
			->method( 'generateCounts' )
			->with( WikiMap::getCurrentWikiDbDomain(), 30 )
			->willReturn( [ 'test' => 'value' ] );
		$objectUnderTest->expects( $this->once() )
			->method( 'output' )
			->with( "{\"test\":\"value\"}\n" );
		$objectUnderTest->setOption( 'averages-accuracy', 30 );
		$objectUnderTest->execute();
	}
}
