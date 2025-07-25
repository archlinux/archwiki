<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use LogicException;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\RecentChanges\RecentChange;

/**
 * All methods except ::getDefaultRecentChangeAttribs require the test class to be a
 * database integration test class.
 */
trait CheckUserCommonTraitTest {
	/**
	 * Converts any timestamp in the expected row values to the correct format for the current database type.
	 *
	 * @param array $fields
	 * @param array $expectedRow
	 * @return array
	 */
	public function convertTimestampInExpectedRowToDbFormat( array $fields, array $expectedRow ): array {
		if ( !$this->needsDB() ) {
			throw new LogicException(
				'When testing with timestamps in DB formats, the test cases\'s ' .
				'needsDB() method should return true. Use @group Database.'
			);
		}
		foreach ( $fields as $index => $field ) {
			if ( in_array( $field, [ 'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp' ] ) ) {
				$expectedRow[$index] = $this->getDb()->timestamp( $expectedRow[$index] );
			}
		}
		return $expectedRow;
	}

	/**
	 * A function used to insert a RecentChange into the correct table when testing.
	 * Called by the individual tests themselves. This method requires database support, which can be enabled
	 * with "@group Database", or by returning true from needsDB().
	 *
	 * @param array $rcAttribs The attribs for the RecentChange object
	 * @param array $fields The fields to select from the DB when using assertSelect()
	 * @param array &$expectedRow The expected values for the fields from the DB when using assertSelect()
	 * @return RecentChange
	 */
	public function commonTestsUpdateCheckUserData(
		array $rcAttribs, array $fields, array &$expectedRow
	): RecentChange {
		if ( !$this->needsDB() ) {
			throw new LogicException( 'When testing with logs, the test cases\'s needsDB()' .
				' method should return true. Use @group Database.' );
		}
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		$this->convertTimestampInExpectedRowToDbFormat( $fields, $expectedRow );
		( new Hooks() )->updateCheckUserData( $rc );
		return $rc;
	}

	/**
	 * Creates a log entry for testing. This method requires database support, which can be enabled
	 * with "@group Database", or by returning true from needsDB().
	 *
	 * @return int The ID for the created log entry
	 */
	public function newLogEntry(): int {
		if ( !$this->needsDB() ) {
			throw new LogicException( 'When testing with logs, the test cases\'s needsDB()' .
				' method should return true. Use @group Database.' );
		}
		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$logEntry->setTarget( $this->getExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		return $logEntry->insert();
	}

	/**
	 * Asserts that a table has the expected number of rows matching
	 * the given conditions. This method requires database support, which can be enabled
	 * with "@group Database", or by returning true from needsDB().
	 *
	 * @param int $expectedRowCount The expected row count
	 * @param string $table The table to select from
	 * @param string $idField The primary key for that table
	 * @param string $message The message to be used for an assertion failure.
	 * @param array $where Any conditions to apply (default no conditions; optional)
	 * @return void
	 */
	public function assertRowCount(
		int $expectedRowCount, string $table, string $idField, string $message, array $where = []
	) {
		if ( !$this->needsDB() ) {
			throw new LogicException( 'When testing with logs, the test cases\'s needsDB()' .
				' method should return true. Use @group Database.' );
		}
		$this->assertSame(
			$expectedRowCount,
			$this->getDb()->newSelectQueryBuilder()
				->field( $idField )
				->table( $table )
				->where( $where )
				->fetchRowCount(),
			$message
		);
	}

	/**
	 * Provides default attributes for a recent change.
	 * @return array
	 */
	public static function getDefaultRecentChangeAttribs() {
		// From RecentChangeTest.php's provideAttribs
		return [
			'rc_timestamp' => wfTimestamp( TS_MW ),
			'rc_namespace' => NS_USER,
			'rc_title' => 'Tony',
			'rc_type' => RC_EDIT,
			'rc_source' => RecentChange::SRC_EDIT,
			'rc_minor' => 0,
			'rc_cur_id' => 77,
			'rc_user' => 858173476,
			'rc_user_text' => 'Tony',
			'rc_comment' => '',
			'rc_comment_text' => '',
			'rc_comment_data' => null,
			'rc_this_oldid' => 70,
			'rc_last_oldid' => 71,
			'rc_bot' => 0,
			'rc_ip' => '',
			'rc_patrolled' => 0,
			'rc_new' => 0,
			'rc_old_len' => 80,
			'rc_new_len' => 88,
			'rc_deleted' => 0,
			'rc_logid' => 0,
			'rc_log_type' => null,
			'rc_log_action' => '',
			'rc_params' => '',
		];
	}
}
