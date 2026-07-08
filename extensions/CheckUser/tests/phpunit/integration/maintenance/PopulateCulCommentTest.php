<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Services\NoSuchServiceException;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateCulComment
 */
class PopulateCulCommentTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulComment::class;
	}

	/**
	 * The schema override does not work for postgres, so skip these tests if using postgres
	 */
	protected function setUp(): void {
		$this->markTestSkippedIfDbType( 'postgres' );
		parent::setUp();
	}

	public function testDoDBUpdatesWhenNoRowsToUpdate(): void {
		$this->assertTrue( $this->maintenance->doDBUpdates() );

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'The cu_log table seems to be empty', $actualOutput );
	}

	public function testDoDBUpdatesWhenCheckUserLogServiceNotDefined(): void {
		// Simulate CheckUserLogService not being defined
		$this->setService(
			'CheckUserLogService',
			static fn () => throw new NoSuchServiceException( 'CheckUserLogService' )
		);

		$this->testDoDBUpdatesSingleRow( 'Test abc', 'Test abc' );
	}

	/** @dataProvider provideAddLogEntryReasonId */
	public function testDoDBUpdatesSingleRow( $reason, $plaintextReason ) {
		$testTarget = $this->getTestUser()->getUserIdentity();
		// Create a test cu_log entry with a cul_reason value.
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log' )
			->row( [
				'cul_timestamp' => $this->getDb()->timestamp( ConvertibleTimestamp::time() ),
				'cul_actor' => $this->getTestSysop()->getUser()->getActorId(),
				'cul_type' => 'user',
				'cul_target_id' => $testTarget->getId(),
				'cul_target_text' => $testTarget->getName(),
				'cul_reason' => $reason,
				'cul_reason_id' => 0,
				'cul_reason_plaintext_id' => 0,
			] )
			->execute();
		// Run the maintenance script
		$this->createMaintenance()->doDBUpdates();
		// Check that cul_reason is correct
		$this->newSelectQueryBuilder()
			->select( 'cul_reason' )
			->from( 'cu_log' )
			->assertFieldValue( $reason );
		// Get the ID to the comment table stored in cu_log
		$row = $this->getDb()->newSelectQueryBuilder()
			->fields( [ 'cul_reason_id', 'cul_reason_plaintext_id' ] )
			->table( 'cu_log' )
			->fetchRow();
		// Check that the comment IDs are for rows that have the correct
		//  expected reason.
		$this->assertSame(
			$reason,
			$this->getDb()->newSelectQueryBuilder()
				->field( 'comment_text' )
				->table( 'comment' )
				->where( [ 'comment_id' => $row->cul_reason_id ] )
				->fetchField(),
			'The cul_reason_id is for the wrong comment.'
		);
		$this->assertSame(
			$plaintextReason,
			$this->getDb()->newSelectQueryBuilder()
				->field( 'comment_text' )
				->table( 'comment' )
				->where( [ 'comment_id' => $row->cul_reason_plaintext_id ] )
				->fetchField(),
			'The cul_reason_plaintext_id is for the wrong comment.'
		);
	}

	public static function provideAddLogEntryReasonId() {
		return [
			[ 'Testing 1234', 'Testing 1234' ],
			[ 'Testing 1234 [[test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [[:mw:Testing|test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [test]', 'Testing 1234 [test]' ],
			[ 'Testing 1234 [https://example.com]', 'Testing 1234 [https://example.com]' ],
			[ 'Testing 1234 [[test]', 'Testing 1234 [[test]' ],
			[ 'Testing 1234 [test]]', 'Testing 1234 [test]]' ],
			[ 'Testing 1234 <var>', 'Testing 1234 <var>' ],
			[ 'Testing 1234 {{test}}', 'Testing 1234 {{test}}' ],
			[ 'Testing 12345 [[{{test}}]]', 'Testing 12345 [[{{test}}]]' ],
		];
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// Create the cul_reason column in cu_log using the SQL patch file associated with the current
		// DB type.
		return [
			'scripts' => [
				__DIR__ . '/patches/' . $db->getType() . '/patch-cu_log-add-cul_reason.sql',
			],
			'drop' => [],
			'create' => [],
			'alter' => [ 'cu_log' ],
		];
	}
}
