<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return MoveLogEntriesFromCuChanges::class;
	}

	protected function commonTestNoMove( $expectedCuChangesCount, $expectedCuPrivateRowCount = 0 ) {
		$this->assertRowCount(
			$expectedCuPrivateRowCount, 'cu_private_event', 'cupe_id',
			'Rows were moved to cu_private_event when they should not have been moved.'
		);
		$this->assertRowCount(
			$expectedCuChangesCount, 'cu_changes', 'cuc_id',
			'Rows were removed from cu_changes even though there was no move.'
		);
	}

	protected function commonTestMoved(
		$expectedCuChangesRowCount, $expectedCuChangesRowCountWithOnlyReadOld, $expectedCuPrivateRowCount
	) {
		$this->assertRowCount(
			$expectedCuPrivateRowCount, 'cu_private_event', 'cupe_id',
			'Rows were moved to cu_private_event when they should not have been moved.'
		);
		$this->assertRowCount(
			$expectedCuChangesRowCountWithOnlyReadOld, 'cu_changes', 'cuc_id',
			'Rows were not successfully marked as being only for READ_OLD in cu_changes.',
			[ 'cuc_only_for_read_old' => 1 ]
		);
		$this->assertRowCount(
			$expectedCuChangesRowCount, 'cu_changes', 'cuc_id',
			'Rows were removed from cu_changes when they should not have been.'
		);
	}

	public function testNoMoveIfCuChangesEmpty() {
		// Run the script
		$this->assertTrue(
			$this->maintenance->execute(),
			'execute() should have returned true so that the script can be skipped in the future.'
		);
		// Test no moving happened
		$this->commonTestNoMove( 0 );
	}

	/** @dataProvider provideBatchSize */
	public function testBatchSize( $numberOfRows, $batchSize ) {
		// TODO: Update this to be able to handle inserting testing data into cu_changes for old log entries,
		// including using the SQL patches?
		// Set up cu_changes
		$expectedRow = [];
		for ( $i = 0; $i < $numberOfRows / 2; $i++ ) {
			// Insert rows for edits, which do not need moving.
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_EDIT ] ),
				[],
				$expectedRow
			);
			// Insert rows for log entries, which need moving.
			$attribs = self::getDefaultRecentChangeAttribs();
			$rcRow = [
				'cuc_namespace'  => $attribs['rc_namespace'],
				'cuc_title'      => $attribs['rc_title'],
				'cuc_minor'      => $attribs['rc_minor'],
				'cuc_actiontext' => 'test action text',
				'cuc_comment'    => 'test comment',
				'cuc_this_oldid' => $attribs['rc_this_oldid'],
				'cuc_last_oldid' => $attribs['rc_last_oldid'],
				'cuc_type'       => RC_LOG,
				'cuc_page_id'    => $attribs['rc_cur_id'],
				'cuc_timestamp'  => $this->getDb()->timestamp( $attribs['rc_timestamp'] ),
			];

			/** @var CheckUserInsert $checkUserInsert */
			$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
			$checkUserInsert->insertIntoCuChangesTable(
				$rcRow, __METHOD__, new UserIdentityValue( $attribs['rc_user'], $attribs['rc_user_text'] )
			);
		}
		$this->assertRowCount(
			$numberOfRows, 'cu_changes', 'cuc_id',
			'Database not set up correctly for the test'
		);
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		// Make a copy to prevent syntax error warnings for accessing protected method setBatchSize.
		$maintenance = $this->maintenance;
		$maintenance->setBatchSize( $batchSize );
		$this->assertTrue(
			$maintenance->execute(),
			'execute() should have returned true as moving entries should have completed successfully.'
		);
		// Test entries were moved
		$this->commonTestMoved( $numberOfRows, $numberOfRows / 2, $numberOfRows / 2 );
	}

	public static function provideBatchSize() {
		return [
			'cu_changes row count 3 and batch size 1' => [
				6, 4
			],
			'cu_changes row count 10 and batch size 5' => [
				10, 5
			],
			'cu_changes row count 10 and batch size 100' => [
				10, 100
			],
		];
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// Create the now removed columns (cuc_only_for_read_old, cuc_actiontext, and cuc_private) in cu_changes
		// necessary for the script to run.
		$sqlPatchesDir = __DIR__ . '/patches/' . $db->getType();
		return [
			'scripts' => [
				$sqlPatchesDir . '/patch-cu_changes-add-cuc_only_for_read_old.sql',
				$sqlPatchesDir . '/patch-cu_changes-add-cuc_actiontext.sql',
				$sqlPatchesDir . '/patch-cu_changes-add-cuc_private.sql',
			],
			'alter' => [ 'cu_changes' ],
		];
	}
}
