<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesWithoutReadOldColumnTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return MoveLogEntriesFromCuChanges::class;
	}

	public function testWhenReadOldColumnDoesNotExist() {
		// Insert one testing row to cu_changes to skip the empty table check.
		$expectedRow = [];
		$this->commonTestsUpdateCheckUserData(
			array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_EDIT ] ),
			[],
			$expectedRow
		);
		// Run the script, and verify it does not run but outputs the message about not running if the
		// cuc_only_for_read_old column does not exist.
		$this->assertTrue(
			$this->maintenance->execute(),
			'execute() should have returned true so that the script can be skipped in the future.'
		);
		$this->expectOutputString(
			"cu_changes cannot hold log entries; nothing to move.\n"
		);
		// Test no moving happened in the database
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'Rows were moved to cu_private_event when they should not have been moved.'
		);
		$this->assertRowCount(
			1, 'cu_changes', 'cuc_id',
			'Rows were removed from cu_changes even though there was no move.'
		);
	}
}
