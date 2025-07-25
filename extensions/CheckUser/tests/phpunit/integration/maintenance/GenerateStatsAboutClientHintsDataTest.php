<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\GenerateStatsAboutClientHintsData;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Maintenance\GenerateStatsAboutClientHintsData
 */
class GenerateStatsAboutClientHintsDataTest extends MaintenanceBaseTestCase {
	use CheckUserClientHintsCommonTraitTest;

	protected function getMaintenanceClass() {
		return GenerateStatsAboutClientHintsData::class;
	}

	public function testGenerateCounts() {
		$returnArray = $this->maintenance->generateCounts( WikiMap::getCurrentWikiDbDomain(), 1000 );
		$this->assertArrayEquals(
			[
				'totalRowCount' => [
					'cu_useragent_clienthints_map' => 68,
					'cu_useragent_clienthints' => 15
				],
				'invalidMapRowsCount' => 1,
				// This is ((11 * 5) + (4 * 3) + 1)/8
				// which is 11 mapping rows for the first 5 reference IDs,
				// with one invalid map row for reference ID 1, and 4 mapping
				// rows for 3 reference IDs.
				'averageMapRowsPerReferenceId' => 8.5,
				'mapTableRowCountBreakdown' => [
					'brands' => [
						'Not.A/Brand 8' => 5,
						'Chromium 114' => 5,
						'Google Chrome 114' => 5,
						'Testing 8.7' => 3,
					],
					'fullVersionList' => [
						'Not.A/Brand 8.0.0.0' => 5,
						'Chromium 114.0.5735.199' => 5,
						'Google Chrome 114.0.5735.199' => 5,
					],
					'mobile' => [ 0 => 5, 1 => 3 ],
					'architecture' => [ 'x86' => 5 ],
					'bitness' => [ '64' => 5 ],
					'platform' => [ 'Windows' => 5, 'Test' => 3 ],
					'platformVersion' => [ '15.0.0' => 5, '5.4' => 3 ],
				],
				'averageItemsPerNamePerReferenceId' => [
					'architecture' => 1,
					'bitness' => 1,
					'brands' => 2.25,
					'fullVersionList' => 3,
					'mobile' => 1,
					'platform' => 1,
					'platformVersion' => 1,
				]
			],
			$returnArray,
			false,
			true
		);
	}

	public function addDBData() {
		/** @var UserAgentClientHintsManager $services */
		$services = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$exampleClientHintsData = $this->getExampleClientHintsDataObjectFromJsApi();
		// Insert Client Hints data for reference IDs 1 to 5 (inclusive) using the standard example Client Hints data
		for ( $i = 1; $i < 6; $i++ ) {
			$services->insertClientHintValues( $exampleClientHintsData, $i, 'revision' );
		}
		// Add 4 items using different Client Hints data
		$differentExampleClientHintsData = $this->getExampleClientHintsDataObjectFromJsApi(
			null,
			null,
			[
				[
					"brand" => "Testing",
					"version" => 8.7
				]
			],
			[],
			true,
			null,
			"Test",
			"5.4"
		);
		// Use reference IDs in range 6 to 8 (inclusive).
		for ( $i = 6; $i < 9; $i++ ) {
			$services->insertClientHintValues( $differentExampleClientHintsData, $i, 'revision' );
		}
		// One invalid map row
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent_clienthints_map' )
			->row( [ 'uachm_uach_id' => 1234, 'uachm_reference_id' => 1, 'uachm_reference_type' => 0 ] )
			->caller( __METHOD__ )
			->execute();
	}
}
