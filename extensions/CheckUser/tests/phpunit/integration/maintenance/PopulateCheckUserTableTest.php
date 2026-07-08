<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateCheckUserTable
 */
class PopulateCheckUserTableTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCheckUserTable::class;
	}

	public function testNoPopulationOnEmptyRecentChangesTable() {
		$this->assertTrue(
			$this->maintenance->execute(),
			'Maintenance script should have returned true.'
		);
		$this->expectOutputString( "recentchanges is empty; nothing to add.\n" );
		$this->assertRowCount(
			0,
			'cu_private_event',
			'cupe_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0,
			'cu_changes',
			'cuc_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0,
			'cu_log_event',
			'cule_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
	}

	/**
	 * @dataProvider provideTestPopulation
	 */
	public function testPopulation(
		bool $putIPinRCConfigValue,
		int $numberOfRows,
		int $expectedCuChangesCount,
		int $expectedCuLogEventCount
	) {
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.68' );
		$this->overrideConfigValue( MainConfigNames::PutIPinRC, $putIPinRCConfigValue );

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
			$numberOfRows + 1,
			'recentchanges',
			'*',
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
			$expectedCuLogEventCount,
			'cu_log_event',
			'cule_id',
			'Incorrect number of entries in cu_log_event after population.'
		);
		$this->assertRowCount(
			$expectedCuChangesCount,
			'cu_changes',
			'cuc_id',
			'Incorrect number of entries in cu_changes after population.'
		);
		$this->assertRowCount(
			1,
			'cu_private_event',
			'cupe_id',
			'Population script should add one entry to cu_private_event which occurs when the rc_logid ' .
			'is invalid.'
		);

		// Check that the IP from the recentchanges table is copied over correctly
		$expectedIPHex = $putIPinRCConfigValue ? IPUtils::toHex( '1.2.3.68' ) : null;
		$this->newSelectQueryBuilder()
			->select( 'cuc_ip_hex' )
			->from( 'cu_changes' )
			->assertFieldValues( array_fill( 0, $expectedCuChangesCount, $expectedIPHex ) );
		$this->newSelectQueryBuilder()
			->select( 'cule_ip_hex' )
			->from( 'cu_log_event' )
			->assertFieldValues( array_fill( 0, $expectedCuLogEventCount, $expectedIPHex ) );
		$this->newSelectQueryBuilder()
			->select( 'cupe_ip_hex' )
			->from( 'cu_private_event' )
			->assertFieldValue( $expectedIPHex );

		// Check that the user agent ID field is correctly populated
		$expectedUserAgentColumnValue = $this->getDb()->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->where( [ 'cuua_text' => '' ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse(
			$expectedUserAgentColumnValue,
			'cu_useragent row for an empty User-Agent string is missing'
		);
		$this->newSelectQueryBuilder()
			->select( 'cuc_agent_id' )
			->from( 'cu_changes' )
			->caller( __METHOD__ )
			->assertFieldValues( array_fill( 0, $expectedCuChangesCount, $expectedUserAgentColumnValue ) );
		$this->newSelectQueryBuilder()
			->select( 'cule_agent_id' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->assertFieldValues( array_fill( 0, $expectedCuLogEventCount, $expectedUserAgentColumnValue ) );
		$this->newSelectQueryBuilder()
			->select( 'cupe_agent_id' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->assertFieldValue( $expectedUserAgentColumnValue );
	}

	public static function provideTestPopulation() {
		return [
			'recentchanges row count 4 with IPs in recentchanges' => [
				'putIPinRCConfigValue' => true,
				'numberOfRows' => 4,
				'expectedCuChangesCount' => 2,
				'expectedCuLogEventCount' => 2,
			],
			'recentchanges row count 2 with no IPs in recentchanges' => [
				'putIPinRCConfigValue' => false,
				'numberOfRows' => 2,
				'expectedCuChangesCount' => 1,
				'expectedCuLogEventCount' => 1,
			],
		];
	}

	public function testPopulationForExcludedSources() {
		$recentChangesStore = $this->getServiceContainer()->getRecentChangeStore();

		// Insert a SRC_CATEGORIZE entry
		$rc = new RecentChange();
		$rc->setAttribs( array_merge(
			$this->getDefaultRecentChangeAttribs(),
			[ 'rc_source' => RecentChange::SRC_CATEGORIZE ]
		) );
		$recentChangesStore->insertRecentChange( $rc );

		// Insert a RecentChange with an unknown source
		$rc = new RecentChange();
		$rc->setAttribs( array_merge(
			$this->getDefaultRecentChangeAttribs(),
			[ 'rc_source' => 'unknown-abc' ]
		) );
		$recentChangesStore->insertRecentChange( $rc );

		// Clear cu_changes, cu_private_event and cu_log_event because entries may have been inserted
		// by CheckUserInsert through the above test code.
		// We only want to test that the maintenance script does not add the above entries to
		// the CheckUser result tables.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );

		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( 'cuc_id' )
			->from( 'cu_changes' )
			->caller( __METHOD__ )
			->assertEmptyResult();
		$this->newSelectQueryBuilder()
			->select( 'cule_id' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->assertEmptyResult();
		$this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}
}
