<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable;
use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Services\ServiceContainer;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable
 */
class PopulateUserAgentTableTest extends MaintenanceBaseTestCase {
	use CheckUserCommonTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateUserAgentTable::class;
	}

	public function testWhenCheckUserInsertIsNotDefined() {
		// Mock that the CheckUserInsert service is not defined (which happens during install.php)
		// by making the service container have no services
		/** @var MockObject&PopulateUserAgentTable $maintenance */
		$maintenance = $this->getMockBuilder( $this->getMaintenanceClass() )
			->onlyMethods( [ 'getServiceContainer' ] )
			->getMock();
		$maintenance->method( 'getServiceContainer' )
			->willReturn( new ServiceContainer() );

		$this->assertTrue(
			$maintenance->execute(),
			'::execute needs to return true to avoid install.php failing to execute'
		);

		// ::execute should not have resulted in an updatelog row, as we want the script
		// to be run during an update.php run to actually migrate any rows that need
		// migration
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'updatelog' )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testSuccessfulPopulation() {
		$preExistingUserAgentTableId = $this->setUpDatabaseForPopulationTest();

		// Check the DB has been set up correctly for the test,
		// so that we can see a change in the DB after the script runs
		$this->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValue( $preExistingUserAgentTableId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_changes' )
			->where( $this->getDb()->expr( 'cuc_agent_id', '!=', [ 0, $preExistingUserAgentTableId ] ) )
			->caller( __METHOD__ )
			->assertEmptyResult();

		$this->maintenance->execute();

		// Check that the cu_useragent table now has been populated
		$this->newSelectQueryBuilder()
			->select( 'cuua_text' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValues( [ 'test1', 'test2', 'test3' ] );

		// Check the CheckUser result tables have their agent_id columns populated as expected
		$this->newSelectQueryBuilder()
			->select( [ 'cuc_id', 'cuua_text' ] )
			->from( 'cu_changes' )
			->join( 'cu_useragent', null, 'cuc_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test1' ],
				[ 2, 'test2' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cule_id', 'cuua_text' ] )
			->from( 'cu_log_event' )
			->join( 'cu_useragent', null, 'cule_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test2' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cupe_id', 'cuua_text' ] )
			->from( 'cu_private_event' )
			->join( 'cu_useragent', null, 'cupe_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test3' ],
				[ 2, 'test3' ],
			] );

		// Assert on the output
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			"Populating cuc_agent_id in cu_changes\n" .
				"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cule_agent_id in cu_log_event\n" .
				"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cupe_agent_id in cu_private_event\n" .
				"... 2 rows populated\nDone. Populated 2 rows.",
			$actualOutput
		);
	}

	/**
	 * Sets up the database to have rows which need their agent_id column populating
	 * so we can test the population functionality of the maintenance script
	 *
	 * @return int The ID of the cu_useragent row that is pre-existing before
	 *   the maintenance script is run
	 */
	private function setUpDatabaseForPopulationTest(): int {
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$target = $this->getTestUser()->getUserIdentity();

		// Avoid the creation of the test user causing pre-existing entries in the
		// CheckUser result tables, as we will later assert on the row IDs
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );

		// Write some testing entries to cu_changes, cu_log_event, and cu_private_event.
		// These entries have the *_agent column populated manually in this method, with the
		// *_agent_id column populated for us by the CheckUserInsert service
		$this->insertCheckUserDataRow(
			'test1',
			[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(), 'rc_this_oldid' => 123 ],
			'cu_changes',
			[ 'cuc_this_oldid' => 123 ]
		);

		$this->insertCheckUserDataRow(
			'test2',
			[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(), 'rc_this_oldid' => 1234 ],
			'cu_changes',
			[ 'cuc_this_oldid' => 1234 ]
		);

		$logId = $this->newLogEntry();
		$this->insertCheckUserDataRow(
			'test2',
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => $logId,
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			],
			'cu_log_event',
			[ 'cule_log_id' => $logId ]
		);

		$this->insertCheckUserDataRow(
			'test3',
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => 0,
				'rc_log_type' => 'test', 'rc_log_action' => 'test2',
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			],
			'cu_private_event',
			[ 'cupe_log_action' => 'test2' ]
		);

		$this->insertCheckUserDataRow(
			'test3',
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => 0,
				'rc_log_type' => 'test2', 'rc_log_action' => 'test3',
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			],
			'cu_private_event',
			[ 'cupe_log_action' => 'test3' ]
		);

		// Drop all entries from cu_useragent except from the one for 'test1'
		// and replace the associated references with 0 to simulate the
		// row having been written when the user agent table migration was write old
		$existingUserAgentTableId = $this->getDb()->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->where( [ 'cuua_text' => 'test1' ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'cu_useragent' )
			->where( $this->getDb()->expr( 'cuua_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_changes' )
			->set( [ 'cuc_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cuc_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_log_event' )
			->set( [ 'cule_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cule_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_private_event' )
			->set( [ 'cupe_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cupe_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();

		return $existingUserAgentTableId;
	}

	/**
	 * Creates an event in one of the CheckUser result tables, along with populating the *_agent column
	 * with the value of the User-Agent header.
	 */
	private function insertCheckUserDataRow(
		string $userAgent,
		array $rcAttribs,
		string $table,
		array $tableUniqueCond
	): void {
		// Create the testing event using the provided RecentChanges attribs
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', $userAgent );
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			$rcAttribs
		) );
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$checkUserInsert->updateCheckUserData( $rc );

		// Manually set the *_agent column for the newly created event so that we can test the value
		// of this column is populated into the cu_useragent table and *_agent_id column
		$this->getDb()->newUpdateQueryBuilder()
			->update( $table )
			->set( [ CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . 'agent' => $userAgent ] )
			->where( $tableUniqueCond )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulPopulationWhenAgentColumnValuesAreIsNull() {
		$preExistingUserAgentTableId = $this->setUpDatabaseForPopulationTest();

		// null as the agent column can happen when
		// the row is created by populateCheckUserTable.php
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_changes' )
			->set( [ 'cuc_agent' => null ] )
			->where( $this->getDb()->expr( 'cuc_agent_id', '!=', $preExistingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_log_event' )
			->set( [ 'cule_agent' => null ] )
			->where( $this->getDb()->expr( 'cule_agent_id', '!=', $preExistingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_private_event' )
			->set( [ 'cupe_agent' => null ] )
			->where( $this->getDb()->expr( 'cupe_agent_id', '!=', $preExistingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();

		// Check the DB has been set up correctly for the test,
		// so that we can see a change in the DB after the script runs
		$this->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValue( $preExistingUserAgentTableId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_changes' )
			->where(
				$this->getDb()
					->expr( 'cuc_agent_id', '!=', $preExistingUserAgentTableId )
					->and( 'cuc_agent', '!=', null )
			)
			->caller( __METHOD__ )
			->assertEmptyResult();

		$this->maintenance->execute();

		// Check that the cu_useragent table now has been populated
		$this->newSelectQueryBuilder()
			->select( 'cuua_text' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValues( [ '', 'test1' ] );

		// Check the CheckUser result tables have their agent_id columns populated as expected
		$this->newSelectQueryBuilder()
			->select( [ 'cuc_id', 'cuua_text' ] )
			->from( 'cu_changes' )
			->join( 'cu_useragent', null, 'cuc_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test1' ],
				[ 2, '' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cule_id', 'cuua_text' ] )
			->from( 'cu_log_event' )
			->join( 'cu_useragent', null, 'cule_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, '' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cupe_id', 'cuua_text' ] )
			->from( 'cu_private_event' )
			->join( 'cu_useragent', null, 'cupe_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, '' ],
				[ 2, '' ],
			] );

		// Assert on the output
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			"Populating cuc_agent_id in cu_changes\n" .
			"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cule_agent_id in cu_log_event\n" .
			"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cupe_agent_id in cu_private_event\n" .
			"... 2 rows populated\nDone. Populated 2 rows.",
			$actualOutput
		);
	}

	/** @inheritDoc */
	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// The script relies on the state of the CheckUser result tables before the
		// cu_useragent table migration was finished. Therefore, we need to add
		// back the *_agent columns to each result table for the test to work.
		$sqlPatchesDir = __DIR__ . '/patches/' . $db->getType();
		return [
			'scripts' => [
				$sqlPatchesDir . '/patch-cu_changes-add-cuc_agent.sql',
				$sqlPatchesDir . '/patch-cu_private_event-add-cupe_agent.sql',
				$sqlPatchesDir . '/patch-cu_log_event-add-cule_agent.sql',
			],
			'alter' => [ 'cu_private_event', 'cu_changes' ],
		];
	}
}
