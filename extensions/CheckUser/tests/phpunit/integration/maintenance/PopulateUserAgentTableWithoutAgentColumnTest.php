<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable
 */
class PopulateUserAgentTableWithoutAgentColumnTest extends MaintenanceBaseTestCase {
	use CheckUserCommonTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateUserAgentTable::class;
	}

	public function testWhenAgentColumnsDoNotExist() {
		$this->assertTrue(
			$this->maintenance->execute(),
			'::execute needs to return true to avoid install.php failing to execute'
		);

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'The cupe_agent field does not exist in cu_private_event which is needed for the migration',
			$actualOutput
		);
	}
}
