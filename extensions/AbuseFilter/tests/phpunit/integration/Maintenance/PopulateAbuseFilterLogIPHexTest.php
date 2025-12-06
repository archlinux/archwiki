<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Maintenance;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Maintenance\PopulateAbuseFilterLogIPHex;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IMaintainableDatabase;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\PopulateAbuseFilterLogIPHex
 */
class PopulateAbuseFilterLogIPHexTest extends MaintenanceBaseTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Avoid slow tests caused by the code sleeping between batches.
		$this->maintenance->setOption( 'sleep', 0 );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateAbuseFilterLogIPHex::class;
	}

	public function testMigrationWhenNoAbuseFilterLogRows() {
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating afl_ip_hex in abuse_filter_log with value from afl_ip', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Migrated 0 rows', $actualOutput );
	}

	/**
	 * Creates an abuse_filter_log row suitable for insertion into the database, where default values are used
	 * if a value is not specified for a column.
	 *
	 * @param array $overrides The specific values we are testing (if not set then defaults are used)
	 * @return array The abuse_filter_log row to insert
	 */
	private function getAbuseFilterLogRow( array $overrides = [] ): array {
		return array_merge( [
			'afl_ip' => '1.1.1.1',
			'afl_ip_hex' => IPUtils::toHex( '1.1.1.1' ),
			'afl_global' => 0,
			'afl_filter_id' => 1,
			'afl_user' => 1,
			'afl_user_text' => 'User',
			'afl_action' => 'edit',
			'afl_actions' => '',
			'afl_var_dump' => AbuseFilterServices::getVariablesBlobStore()
				->storeVarDump( VariableHolder::newFromArray( [] ) ),
			'afl_namespace' => 0,
			'afl_title' => 'Title',
			'afl_wiki' => null,
			'afl_deleted' => 0,
			'afl_rev_id' => 42,
			'afl_timestamp' => $this->getDb()->timestamp(),
		], $overrides );
	}

	public function testMigrationWhenNoAbuseFilterLogRowsContainIPs() {
		// Insert an abuse_filter_log row which has no IP
		$rowNotNeedingMigration = $this->getAbuseFilterLogRow( [ 'afl_ip' => '', 'afl_ip_hex' => '' ] );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->row( $rowNotNeedingMigration )
			->caller( __METHOD__ )
			->execute();

		// Test that the script ignores abuse_filter_log rows with no IP set, as there is nothing to migrate
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating afl_ip_hex in abuse_filter_log with value from afl_ip', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Migrated 0 rows', $actualOutput );

		// Verify that the abuse_filter_log row was not changed if no migration was needed
		$this->assertArrayEquals(
			$rowNotNeedingMigration,
			(array)$this->newSelectQueryBuilder()
				->select( array_keys( $rowNotNeedingMigration ) )
				->from( 'abuse_filter_log' )
				->where( [ 'afl_id' => 1 ] )
				->caller( __METHOD__ )
				->fetchRow(),
			false, true
		);
	}

	public function testMigration() {
		// Insert some testing abuse_filter_log rows, where:
		// * Some rows have afl_ip_hex and afl_ip set to non-empty strings
		// * Some rows have just afl_ip set to a non-empty string
		// * Some rows where both afl_ip_hex and afl_ip are empty strings
		$initialRows = [
			$this->getAbuseFilterLogRow( [ 'afl_ip' => '', 'afl_ip_hex' => '' ] ),
			$this->getAbuseFilterLogRow( [
				'afl_ip' => '1.2.3.4',
				'afl_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
			] ),
			$this->getAbuseFilterLogRow( [
				'afl_ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
				'afl_ip_hex' => '',
			] ),
			$this->getAbuseFilterLogRow( [
				'afl_ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
				'afl_ip_hex' => IPUtils::toHex( '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ),
			] ),
			$this->getAbuseFilterLogRow( [ 'afl_ip' => '4.5.6.7', 'afl_ip_hex' => '' ] ),
			$this->getAbuseFilterLogRow( [ 'afl_ip' => '', 'afl_ip_hex' => '' ] ),
			$this->getAbuseFilterLogRow( [ 'afl_ip' => '200.200.200.200', 'afl_ip_hex' => '' ] ),
		];
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->rows( $initialRows )
			->caller( __METHOD__ )
			->execute();

		// Execute the migration script and verify that three rows are migrated
		$this->maintenance->loadWithArgv( [ '--batch-size', 2 ] );
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating afl_ip_hex in abuse_filter_log with value from afl_ip', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Migrated 3 rows', $actualOutput );

		// Verify that the data has actually been migrated as reported
		foreach ( $initialRows as $index => $expectedRow ) {
			if ( $expectedRow['afl_ip'] ) {
				$expectedRow['afl_ip_hex'] = IPUtils::toHex( $expectedRow['afl_ip'] );
			}

			$this->assertArrayEquals(
				$expectedRow,
				(array)$this->getDb()->newSelectQueryBuilder()
					->select( array_keys( $expectedRow ) )
					->from( 'abuse_filter_log' )
					->where( [ 'afl_id' => $index + 1 ] )
					->caller( __METHOD__ )
					->fetchRow(),
				false, true
			);
		}
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ): array {
		// Create the afl_ip column in abuse_filter_log using the SQL patch file associated with the current
		// DB type.
		return [
			'scripts' => [ __DIR__ . '/patches/' . $db->getType() . '/patch-abuse_filter_log-add-afl_ip.sql' ],
			'alter' => [ 'abuse_filter_log' ],
		];
	}
}
