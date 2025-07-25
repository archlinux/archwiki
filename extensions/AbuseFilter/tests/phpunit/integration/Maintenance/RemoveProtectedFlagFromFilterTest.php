<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Maintenance\RemoveProtectedFlagFromFilter;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\RemoveProtectedFlagFromFilter
 */
class RemoveProtectedFlagFromFilterTest extends MaintenanceBaseTestCase {
	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return RemoveProtectedFlagFromFilter::class;
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$defaultRow = [
			'af_actor' => 1,
			'af_timestamp' => $this->getDb()->timestamp( '20000101000000' ),
			'af_enabled' => 1,
			'af_comments' => '',
			'af_public_comments' => 'Test filter',
			'af_hit_count' => 0,
			'af_throttled' => 0,
			'af_deleted' => 0,
			'af_global' => 0,
			'af_group' => 'default',
			'af_pattern' => '',
			'af_actions' => '',
		];
		$rows = [
			[
				'af_id' => 1,
				'af_hidden' => Flags::FILTER_PUBLIC
			] + $defaultRow,
			[
				'af_id' => 2,
				'af_hidden' => Flags::FILTER_HIDDEN
			] + $defaultRow,
			[
				'af_id' => 3,
				'af_hidden' => Flags::FILTER_USES_PROTECTED_VARS
			] + $defaultRow,
			[
				'af_id' => 4,
				'af_hidden' => Flags::FILTER_USES_PROTECTED_VARS | Flags::FILTER_HIDDEN
			] + $defaultRow,
		];
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	public function testExecuteNonexistentFilter() {
		$filter = 100;
		$this->expectCallToFatalError();
		$this->maintenance->loadParamsAndArgs( null, null, [ $filter ] );
		$this->maintenance->execute();
	}

	/**
	 * @dataProvider provideUnprotectedFilter
	 */
	public function testExecuteUnprotectedFilter( $filter ) {
		$this->expectOutputString( "Filter $filter is not protected. Nothing to update.\n" );
		$this->maintenance->loadParamsAndArgs( null, null, [ $filter ] );
		$this->assertFalse( $this->maintenance->execute() );
	}

	public static function provideUnprotectedFilter() {
		return [
			'Fail on public filter' => [
				'filterId' => 1,
			],
			'Fail on unprotected, private filter' => [
				'filterId' => 2,
			],
		];
	}

	/**
	 * @dataProvider provideProtectedFilter
	 */
	public function testExecuteProtectedFilter( $filter ) {
		$this->maintenance->loadParamsAndArgs( null, null, [ $filter ] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public static function provideProtectedFilter() {
		return [
			'Remove protected flag from protected filter' => [
				'filterId' => 3,
				'expectedNewPrivacyLevel' => Flags::FILTER_PUBLIC,
			],
			'Remove protected flag from private, protected filter' => [
				'filterId' => 4,
				'expectedNewPrivacyLevel' => Flags::FILTER_HIDDEN,
			],
		];
	}
}
