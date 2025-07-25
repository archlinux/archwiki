<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges
 */
class DeleteReadOldRowsInCuChangesTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return DeleteReadOldRowsInCuChanges::class;
	}

	private function addRows( $numberOfReadOldRows, $numberOfNormalRows ) {
		$rows = [];
		$testUser = $this->getTestUser()->getUser();
		for ( $i = 0; $i < $numberOfReadOldRows; $i++ ) {
			$rows[] = [
				'cuc_actor' => $testUser->getActorId(), 'cuc_only_for_read_old' => 1, 'cuc_type' => RC_LOG,
				'cuc_ip'  => '1.2.3.4', 'cuc_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'cuc_timestamp'  => $this->getDb()->timestamp(), 'cuc_comment_id' => 0,
			];
		}

		for ( $i = 0; $i < $numberOfNormalRows; $i++ ) {
			$rows[] = [
				'cuc_actor' => $testUser->getActorId(), 'cuc_only_for_read_old' => 0, 'cuc_type' => RC_EDIT,
				'cuc_ip'  => '1.2.3.4', 'cuc_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'cuc_timestamp'  => $this->getDb()->timestamp(), 'cuc_comment_id' => 0,
			];
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $rows )
			->execute();
	}

	public function testNoDeleteIfCuChangesEmpty() {
		// Run the script
		$this->assertTrue(
			$this->maintenance->execute(),
			'::execute should have returned true as the script should have run successfully.'
		);
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'The row count in an empty cu_changes should not have changed after calling ::execute.'
		);
		$this->expectOutputString( "cu_changes is empty; nothing to delete.\n" );
	}

	/** @dataProvider provideRowCountsAndBatchSize */
	public function testExecute( $numberOfReadOldRows, $numberOfNormalRows, $batchSize ) {
		// Set up cu_changes
		$this->addRows( $numberOfReadOldRows, $numberOfNormalRows );
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		// Make a copy to prevent syntax error warnings for accessing protected method setBatchSize.
		$maintenance = $this->maintenance;
		$maintenance->setBatchSize( $batchSize );
		$this->assertTrue(
			$maintenance->execute(),
			'::execute should have returned true as deleting entries only for use when reading old should ' .
			'have completed successfully.'
		);
		// Test entries were moved
		$this->assertRowCount(
			$numberOfNormalRows, 'cu_changes', 'cuc_id',
			'The row count in an empty cu_changes was not as expected after calling ::execute.'
		);
	}

	public static function provideRowCountsAndBatchSize() {
		return [
			'cu_changes read old row count 3, normal row count 2, and batch size 1' => [ 3, 2, 1 ],
			'cu_changes read old row count 2, normal row count 4, and batch size 10' => [ 2, 4, 10 ],
		];
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// Create the cuc_only_for_read_old column in cu_changes using the SQL patch file associated with the current
		// DB type.
		return [
			'scripts' => [ __DIR__ . '/patches/' . $db->getType() . '/patch-cu_changes-add-cuc_only_for_read_old.sql' ],
			'alter' => [ 'cu_changes' ],
		];
	}
}
