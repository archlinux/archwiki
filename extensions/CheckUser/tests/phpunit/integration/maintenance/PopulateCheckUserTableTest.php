<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCheckUserTable
 */
class PopulateCheckUserTableTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCheckUserTable::class;
	}

	protected function testNoPopulationOnEmptyRecentChangesTable() {
		$this->assertTrue(
			$this->maintenance->execute(),
			'Maintenance script should have returned true.'
		);
		$this->expectOutputString( 'recentchanges is empty; nothing to add.' );
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0, 'cu_log_event', 'cule_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
	}

	/**
	 * @dataProvider provideTestPopulation
	 */
	public function testPopulation(
		$numberOfRows, $expectedCuChangesCount, $expectedCuChangesReadOldRowsCount, $expectedCuLogEventCount
	) {
		// Set up recentchanges table
		for ( $i = 0; $i < $numberOfRows / 2; $i++ ) {
			$this->editPage( Title::newFromDBkey( 'CheckUserTestPage' ), 'Testing123' . $i );
			// Log action
			$logEntry = new ManualLogEntry( 'foo', 'bar' );
			$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
			$logEntry->setTarget( $this->getExistingTestPage() );
			$logEntry->setComment( 'Testing' );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid );
		}
		// Add one entry with invalid log ID
		$logEntry = new ManualLogEntry( 'foo', 'bar' );
		$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$logEntry->setTarget( $this->getExistingTestPage() );
		$logEntry->setComment( 'Testing' );
		// Add rc_logid to the recent change, but don't insert the log
		$logEntry->publish( 1233455334 );
		// Check that the recentchanges table has entries.
		$this->assertRowCount(
			// Plus one is for the row with rc_logid as an invalid ID.
			$numberOfRows + 1, 'recentchanges', '*',
			'recentchanges table not set up correctly for the test.'
		);
		// Clear cu_changes, cu_private_event and cu_log_event for the test
		//  because entries would have been added by Hooks.php for the above code
		//  that set-up the recentchanges table.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		$maintenance = $this->maintenance;
		$this->assertTrue(
			$maintenance->execute(),
			'execute() should have returned true as moving entries should have completed successfully.'
		);
		$this->assertRowCount(
			$expectedCuLogEventCount, 'cu_log_event', 'cule_id',
			'Incorrect number of entries in cu_log_event after population.'
		);
		$this->assertRowCount(
			$expectedCuChangesCount, 'cu_changes', 'cuc_id',
			'Incorrect number of entries in cu_changes after population.'
		);
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'Population script should add one entry to cu_private_event which occurs when the rc_logid ' .
			'is invalid.'
		);
	}

	public static function provideTestPopulation() {
		return [
			'recentchanges row count 4' => [
				4, 2, null, 2
			],
		];
	}
}
