<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Maintenance;

use MediaWiki\Extension\AbuseFilter\Maintenance\PopulateAbuseFilterLogIPHex;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\PopulateAbuseFilterLogIPHex
 */
class PopulateAbuseFilterLogIPHexWithoutAflIpTest extends MaintenanceBaseTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Avoid slow tests caused by the code sleeping between batches.
		$this->maintenance->setOption( 'sleep', 0 );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateAbuseFilterLogIPHex::class;
	}

	public function testMigrationWhenAflIpColumnDoesNotExist() {
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating afl_ip_hex in abuse_filter_log with value from afl_ip', $actualOutput
		);
		$this->assertStringContainsString(
			'Nothing to do as afl_ip does not exist in abuse_filter_log', $actualOutput
		);
	}
}
