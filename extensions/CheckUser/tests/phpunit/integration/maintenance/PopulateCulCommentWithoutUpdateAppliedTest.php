<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateCulComment
 */
class PopulateCulCommentWithoutUpdateAppliedTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulComment::class;
	}

	public function testDoDBUpdatesWhenCulReasonDoesNotExist(): void {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(),
			'ipusers',
			'ip',
			'127.0.0.1',
			'test'
		);

		$this->assertTrue( $this->maintenance->doDBUpdates() );

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'The cul_reason field does not exist which is needed for migration',
			$actualOutput
		);
	}
}
