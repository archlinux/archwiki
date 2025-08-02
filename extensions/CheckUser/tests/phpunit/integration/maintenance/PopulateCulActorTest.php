<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCulActor;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCulActor
 */
class PopulateCulActorTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulActor::class;
	}

	public function testWhenCuLogTableEmpty() {
		$this->maintenance->execute();
		$this->expectOutputRegex( '/The cu_log table seems to be empty/' );
	}

	public function testWhenCuLogTableDoesNotHaveCulUserColumn() {
		// Add a entry to the cu_log table. The data does not matter, it is only used to skip the check
		// to see if the cu_log table is empty.
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'ipusers', 'ip', '1.2.3.4', 'test'
		);
		// Run the maintenance script which should indicate that the migration cannot run due to the missing
		// cul_user column.
		$this->maintenance->execute();
		$this->expectOutputRegex( '/The cul_user field does not exist which is needed for migration/' );
	}
}
