<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCulActor;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCulActor
 */
class PopulateCulActorWithoutUpdateAppliedTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulActor::class;
	}

	public function testExecuteForSingleRow() {
		$testTarget = $this->getTestUser()->getUserIdentity();
		$testPerformer = $this->getTestSysop()->getUser();
		// Create a test cu_log entry with a cul_user value and an empty cul_actor value
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log' )
			->row( [
				'cul_timestamp' => $this->getDb()->timestamp( ConvertibleTimestamp::time() ),
				'cul_actor' => 0,
				'cul_user' => $testPerformer->getId(),
				'cul_type' => 'user',
				'cul_target_id' => $testTarget->getId(),
				'cul_target_text' => $testTarget->getName(),
				'cul_reason_id' => 0,
				'cul_reason_plaintext_id' => 0
			] )
			->caller( __METHOD__ )
			->execute();
		// Run the maintenance script
		$this->maintenance->execute();
		// Check that cul_actor is correct
		$this->newSelectQueryBuilder()
			->select( 'cul_actor' )
			->from( 'cu_log' )
			->caller( __METHOD__ )
			->assertFieldValue( $testPerformer->getActorId() );
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// Create the cul_user column in cu_log using the SQL patch file associated with the current
		// DB type.
		return [
			'scripts' => [
				__DIR__ . '/patches/' . $db->getType() . '/patch-cu_log-add-cul_user.sql',
			],
			'drop' => [],
			'create' => [],
			'alter' => [ 'cu_log' ],
		];
	}
}
