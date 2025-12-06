<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Maintenance\PurgeOldLogData;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\MainConfigSchema;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\PurgeOldLogData
 */
class PurgeOldLogDataTest extends MaintenanceBaseTestCase {

	private const FAKE_TIME = '20200115000000';
	private const MAX_AGE = 3600;

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return PurgeOldLogData::class;
	}

	/**
	 * Adds rows to abuse_filter_log for a test where rows should be purged.
	 */
	private function addRowsForTest(): void {
		// Pretend that old_wikitext is a protected variable. This allows testing that protected variable values are
		// properly purged.
		$this->setTemporaryHook( 'AbuseFilterCustomProtectedVariables', static function ( &$variables ) {
			$variables[] = 'old_wikitext';
		} );

		$defaultRow = [
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
		];
		$oldTS = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::convert( TS_UNIX, self::FAKE_TIME ) - 2 * self::MAX_AGE
		);

		// Create five abuse_filter_log rows, where three are considered expired and two are not considered expired.
		// One of each type have protected variables added to their variable dump to test that purging.
		// One additional expired row has invalid JSON in the afl_var_dump column to test that handling.
		$rows = [
			[
				'afl_id' => 1, 'afl_timestamp' => $this->getDb()->timestamp( $oldTS ),
				'afl_var_dump' => '{invalidjson}',
			],
			[
				'afl_id' => 2, 'afl_timestamp' => $this->getDb()->timestamp( $oldTS ),
				'afl_var_dump' => AbuseFilterServices::getVariablesBlobStore()
					->storeVarDump( VariableHolder::newFromArray(
						[ 'old_wikitext' => 'abc', 'new_wikitext' => 'abcd' ]
					) ),
			],
			[
				'afl_id' => 3, 'afl_timestamp' => $this->getDb()->timestamp( $oldTS ),
				'afl_ip_hex' => '',
			],
			[
				'afl_id' => 4,
				'afl_timestamp' => $this->getDb()->timestamp( self::FAKE_TIME ),
				'afl_var_dump' => AbuseFilterServices::getVariablesBlobStore()
					->storeVarDump( VariableHolder::newFromArray(
						[ 'old_wikitext' => 'abc', 'new_wikitext' => 'abcd' ]
					) ),
			],
			[
				'afl_id' => 5, 'afl_timestamp' => $this->getDb()->timestamp( self::FAKE_TIME ),
				'afl_ip_hex' => '',
			],
		];
		$rows = array_map( static function ( $row ) use ( $defaultRow ) {
			$row = $row + $defaultRow;
			ksort( $row );
			return $row;
		}, $rows );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	public function testExecute() {
		$this->addRowsForTest();

		// Pretend that old_wikitext is a protected variable. This allows testing that protected variable values are
		// purged.
		$this->setTemporaryHook( 'AbuseFilterCustomProtectedVariables', static function ( &$variables ) {
			$variables[] = 'old_wikitext';
		} );

		// Run the script
		ConvertibleTimestamp::setFakeTime( self::FAKE_TIME );
		$this->maintenance->setConfig( new HashConfig( [
			'AbuseFilterLogIPMaxAge' => self::MAX_AGE,
			'AbuseFilterLogProtectedVariablesMaxAge' => self::MAX_AGE,
			'StatsdServer' => MainConfigSchema::getDefaultValue( 'StatsdServer' )
		] ) );
		$this->maintenance->loadWithArgv( [ '--batch-size', 1 ] );
		$this->maintenance->execute();

		// Verify that afl_ip_hex has been purged for all but the row that is not expired and had an IP.
		$this->newSelectQueryBuilder()
			->select( [ 'afl_id', 'afl_ip_hex' ] )
			->from( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, '' ],
				[ 2, '' ],
				[ 3, '' ],
				[ 4, IPUtils::toHex( '1.1.1.1' ) ],
				[ 5, '' ],
			] );

		// Verify that the protected variable value the log that was expired has been blanked, but the non-expired
		// one has been left alone
		$rows = $this->newSelectQueryBuilder()
			->select( [ 'afl_id', 'afl_var_dump', 'afl_ip_hex' ] )
			->from( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $rows as $row ) {
			// Check this has happened for the VariableHolder instance created when reading the row.
			switch ( (int)$row->afl_id ) {
				case 2:
					$expectedVarsArray = [ 'old_wikitext' => true, 'new_wikitext' => 'abcd' ];
					break;
				case 4:
					$expectedVarsArray = [ 'old_wikitext' => 'abc', 'new_wikitext' => 'abcd' ];
					break;
				case 1:
				case 3:
				case 5:
					$expectedVarsArray = [];
					break;
				default:
					$this->fail( 'Unexpected abuse_filter_log row' );
			}

			$vars = AbuseFilterServices::getVariablesBlobStore()->loadVarDump( $row );
			$varsArray = AbuseFilterServices::getVariablesManager()->dumpAllVars( $vars );
			$this->assertSame( $expectedVarsArray, $varsArray );

			// Check that the row should or should not have JSON (depending on if the row had protected variables
			// and/or was supposed to be purged).
			if ( in_array( (int)$row->afl_id, [ 1, 4 ] ) ) {
				$this->assertStringStartsWith( '{', $row->afl_var_dump );
			} else {
				$this->assertStringStartsNotWith( '{', $row->afl_var_dump );
			}
		}

		// Verify that the output is as expected
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Invalid JSON in afl_var_dump for row with ID 1. Skipping this row', $actualOutput
		);
		$this->assertStringContainsString(
			'Purging afl_ip_hex column in rows that are expired in abuse_filter_log', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Purged 2 IPs', $actualOutput );
		$this->assertStringContainsString(
			'Purging protected variables from afl_var_dump', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Purged 1 var dumps', $actualOutput );
	}

	/** @dataProvider provideExecuteForNoRows */
	public function testExecuteForNoRows( $protectedVariablesMaxAge ) {
		// Run the script
		$this->maintenance->setConfig( new HashConfig( [
			'AbuseFilterLogIPMaxAge' => 1234,
			'AbuseFilterLogProtectedVariablesMaxAge' => $protectedVariablesMaxAge,
			'StatsdServer' => MainConfigSchema::getDefaultValue( 'StatsdServer' )
		] ) );
		$this->maintenance->execute();

		// Verify that the output is as expected
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Purging afl_ip_hex column in rows that are expired in abuse_filter_log', $actualOutput
		);
		$this->assertStringContainsString( 'Done. Purged 0 IPs', $actualOutput );

		if ( $protectedVariablesMaxAge == 0 ) {
			$this->assertStringNotContainsString(
				'Purging protected variables from afl_var_dump', $actualOutput
			);
			$this->assertStringNotContainsString( 'Done. Purged 0 var dumps', $actualOutput );
		} else {
			$this->assertStringContainsString(
				'Purging protected variables from afl_var_dump', $actualOutput
			);
			$this->assertStringContainsString( 'Done. Purged 0 var dumps', $actualOutput );
		}
	}

	public static function provideExecuteForNoRows() {
		return [
			'Protected variables are purged' => [ 1234 ],
			'Protected variables are not purged' => [ 0 ],
		];
	}
}
