<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges
 */
class DeleteReadOldRowsInCuChangesWithoutReadOldColumnTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return DeleteReadOldRowsInCuChanges::class;
	}

	public function testExecuteWhenReadOldColumnDoesNotExist() {
		// Add a testing row to cu_changes to skip the empty table check.
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
			'::execute should have returned true as the script should be logged as completed.'
		);
		$this->expectOutputString(
			"cu_changes cannot hold entries only for use when reading old; nothing to delete.\n"
		);
	}
}
