<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends CheckUserPagerUnitTestBase {

	/** @inheritDoc */
	protected function getPagerClass(): string {
		return CheckUserGetIPsPager::class;
	}

	/** @dataProvider provideGetQueryInfoForCuChanges */
	public function testGetQueryInfoForCuChanges( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuChanges',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuChanges() {
		return [
			'Returns expected keys to arrays and includes cu_changes in tables' => [
				[
					// Fields should be an array
					'fields' => [
						'ip' => 'cuc_ip',
						'ip_hex' => 'cuc_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cuc_timestamp)',
						'last' => 'MAX(cuc_timestamp)',
					],
					// Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					// Should be all of these as arrays
					'conds' => [],
					// Should be all of these as arrays
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuLogEvent */
	public function testGetQueryInfoForCuLogEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuLogEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuLogEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				[
					// Fields should be an array
					'fields' => [
						'ip' => 'cule_ip',
						'ip_hex' => 'cule_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cule_timestamp)',
						'last' => 'MAX(cule_timestamp)',
					],
					// Tables array should have at least cu_log_event
					'tables' => [ 'cu_log_event' ],
					// All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuPrivateEvent */
	public function testGetQueryInfoForCuPrivateEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuPrivateEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuPrivateEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				[
					// Fields should be an array
					'fields' => [
						'ip' => 'cupe_ip',
						'ip_hex' => 'cupe_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cupe_timestamp)',
						'last' => 'MAX(cupe_timestamp)',
					],
					// Tables array should have at least cu_private_event
					'tables' => [ 'cu_private_event' ],
					// All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	public function testGetIndexField() {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->assertSame(
			'last',
			$object->getIndexField(),
			'::getIndexField did not return the expected value.'
		);
	}

	/** @dataProvider provideGetCountForIPActions */
	public function testGetCountForIPActions(
		$cuChangesCount, $cuLogEventCount, $cuPrivateEventCount, $expectedReturnValue
	) {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getCountForIPActionsPerTable' ] )
			->getMock();
		$object->expects( $this->exactly( 3 ) )
			->method( 'getCountForIPActionsPerTable' )
			->willReturnMap( [
				[ '127.0.0.1', 'cu_changes', $cuChangesCount ],
				[ '127.0.0.1', 'cu_log_event', $cuLogEventCount ],
				[ '127.0.0.1', 'cu_private_event', $cuPrivateEventCount ],
			] );
		$object = TestingAccessWrapper::newFromObject( $object );
		$this->assertSame(
			$expectedReturnValue,
			$object->getCountForIPActions( '127.0.0.1' ),
			'Return value of ::getCountForIPActions was not as expected.'
		);
	}

	public static function provideGetCountForIPActions() {
		return [
			'All ::getCountForIPActionsPerTable counts as null' => [ null, null, null, false ],
			'All ::getCountForIPActionsPerTable counts as array' => [
				[ 'total' => 1, 'by_this_target' => 0 ], [ 'total' => 2, 'by_this_target' => 1 ],
				[ 'total' => 3, 'by_this_target' => 2 ], 6,
			],
			'All but one ::getCountForIPActionsPerTable counts as array' => [
				[ 'total' => 1, 'by_this_target' => 0 ], null, [ 'total' => 3, 'by_this_target' => 1 ], 4,
			],
			'All ::getCountForIPActionsPerTable counts as array, total not higher' => [
				[ 'total' => 1, 'by_this_target' => 1 ], [ 'total' => 2, 'by_this_target' => 2 ],
				[ 'total' => 3, 'by_this_target' => 3 ], false,
			],
		];
	}

	/** @dataProvider provideGroupResultsByIndexField */
	public function testGroupResultsByIndexField( $results, $expectedReturnResults ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			$expectedReturnResults,
			$objectUnderTest->groupResultsByIndexField( $results ),
			true,
			true,
			'Return result of ::groupResultsByIndexField was not as expected.'
		);
	}

	public static function provideGroupResultsByIndexField() {
		$currentTimestamp = ConvertibleTimestamp::now();
		return [
			'One IP' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					]
				],
				[
					$currentTimestamp => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					] ]
				]
			],
			'One IP that is repeated' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 46,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					] ]
				]
			],
			'Multiple IPs with repeated IPs' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => '20231004094043',
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '20231004094042',
					],
					(object)[
						'ip' => '127.0.0.2',
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => null,
					],
					(object)[
						'ip' => 'fd12:3456:789a:1::',
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 123,
						'first' => '20221004094043',
						'last' => '20231004094043'
					],
					(object)[
						'ip' => 'fd12:3456:789a:1::',
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 12,
						'first' => '20211004094043',
						'last' => '20231004104043'
					],
					(object)[
						'ip' => '125.3.4.5',
						'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
						'count' => 11,
						'first' => '20211004094043',
						'last' => '20231004104043'
					]
				],
				[
					'20231004094043' => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 25,
						'first' => '20210903094043',
						'last' => '20231004094043',
					] ],
					'' => [ (object)[
						'ip' => '127.0.0.2',
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '',
					] ],
					'20231004104043' => [
						(object)[
							'ip' => 'fd12:3456:789a:1::',
							'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
							'count' => 135,
							'first' => '20211004094043',
							'last' => '20231004104043'
						],
						(object)[
							'ip' => '125.3.4.5',
							'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
							'count' => 11,
							'first' => '20211004094043',
							'last' => '20231004104043'
						],
					]
				]
			],
			'Same IP but different hex' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 1,
						'first' => null,
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						// Deliberately 127.0.0.2 to cause a different hex and properly test grouping.
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						// Deliberately 127.0.0.2 to cause a different hex and properly test grouping.
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 0,
						'first' => '',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [
						(object)[
							'ip' => '127.0.0.1',
							'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
							'count' => 13,
							'first' => '20220904094043',
							'last' => $currentTimestamp,
						],
						(object)[
							'ip' => '127.0.0.1',
							'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
							'count' => 34,
							'first' => '20220904094043',
							'last' => $currentTimestamp,
						],
					]
				]
			]
		];
	}
}
