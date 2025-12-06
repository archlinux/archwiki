<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\Maintenance\PurgeOldLogData;
use MediaWiki\Extension\AbuseFilter\Maintenance\PurgeOldLogIPData;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\PurgeOldLogIPData
 */
class PurgeOldLogIPDataTest extends MaintenanceBaseTestCase {

	/** @var MockObject|Maintenance|TestingAccessWrapper */
	protected $maintenance;

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return PurgeOldLogIPData::class;
	}

	protected function createMaintenance() {
		$maintenanceScript = $this->getMockBuilder( PurgeOldLogIPData::class )
			->onlyMethods( [ 'createChild' ] )
			->getMock();
		return TestingAccessWrapper::newFromObject( $maintenanceScript );
	}

	public function testExecuteCallsNewMaintenanceScript() {
		// Create a mock child maintenance class that expects to be executed.
		$mockMaintenance = $this->getMockBuilder( PurgeOldLogData::class )
			->onlyMethods( [ 'execute' ] )
			->getMock();
		$mockMaintenance->expects( $this->once() )
			->method( 'execute' );
		$this->maintenance->method( 'createChild' )
			->with( PurgeOldLogData::class )
			->willReturn( $mockMaintenance );

		// Run the deprecated alias script
		$this->maintenance->loadWithArgv( [ '--batch-size', 12345 ] );
		$this->maintenance->execute();

		// Expect that the batch-size was copied over successfully.
		$mockMaintenance = TestingAccessWrapper::newFromObject( $mockMaintenance );
		$this->assertSame( 12345, $mockMaintenance->getBatchSize() );
	}
}
