<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\FixTrailingSpacesInLogs;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Maintenance\FixTrailingSpacesInLogs
 * @group Database
 */
class FixTrailingSpacesInLogsTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return FixTrailingSpacesInLogs::class;
	}

	public function testExecute() {
		// Set up the testing data.
		$this->addDBDataWhenRowsExist();
		// Call the method under test
		/** @var TestingAccessWrapper $objectUnderTest */
		$objectUnderTest = $this->maintenance;
		$objectUnderTest->setBatchSize( 4 );
		$objectUnderTest->execute();
		// Verify that no trailing spaces are present in the cul_target_text column of any row
		$this->assertSame(
			0,
			(int)$this->getDb()->newSelectQueryBuilder()
				->field( 'COUNT(*)' )
				->table( 'cu_log' )
				->where( $this->getDb()->expr(
					'cul_target_text',
					IExpression::LIKE,
					new LikeValue( $this->getDb()->anyString(), ' ' )
				) )
				->fetchField()
		);
	}

	public function testExecuteWhenNoRowsExist() {
		// Run the maintenance script
		$this->assertTrue( $this->maintenance->execute() );
		// Verify that the script outputted that there was nothing to process
		$this->expectOutputString(
			"Removing trailing spaces from cu_log entries...\ncu_log is empty; nothing to process.\n"
		);
	}

	public function addDBDataWhenRowsExist() {
		// Add a few testing entries to the cu_log table
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		for ( $i = 0; $i < 2; $i++ ) {
			$checkUserLogService->addLogEntry(
				$this->getTestSysop()->getUser(), 'userips', 'user', 'Testing', '1234 - [[test]]'
			);
			$checkUserLogService->addLogEntry(
				$this->getTestSysop()->getUser(), 'useredits', 'user', 'Testing', '1234 - [[test]]'
			);
		}
		// Modify the target text to add trailing spaces
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_log' )
			->set( [ 'cul_target_text' => 'Testing ' ] )
			->where( [ 'cul_target_text' => 'Testing' ] )
			->execute();
		// Add some testing entries which do not have trailing spaces
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'userips', 'user', 'Test', '1234 - [[test]]'
		);
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'useredits', 'user', 'Test', '1234 - [[test]]'
		);
	}
}
