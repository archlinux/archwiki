<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends CheckUserPagerUnitTestBase {

	/** @inheritDoc */
	protected function getPagerClass(): string {
		return CheckUserGetIPsPager::class;
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
		$cuChangesCount,
		$cuLogEventCount,
		$cuPrivateEventCount,
		$expectedReturnValue
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
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [ (object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					] ],
				],
			],
			'One IP that is repeated' => [
				[
					(object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [ (object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 46,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					] ],
				],
			],
			'Multiple IPs with repeated IPs' => [
				[
					(object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => '20231004094043',
					],
					(object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '20231004094042',
					],
					(object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => null,
					],
					(object)[
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 123,
						'first' => '20221004094043',
						'last' => '20231004094043',
					],
					(object)[
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 12,
						'first' => '20211004094043',
						'last' => '20231004104043',
					],
					(object)[
						'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
						'count' => 11,
						'first' => '20211004094043',
						'last' => '20231004104043',
					],
				],
				[
					'20231004094043' => [ (object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 25,
						'first' => '20210903094043',
						'last' => '20231004094043',
					] ],
					'' => [ (object)[
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '',
					] ],
					'20231004104043' => [
						(object)[
							'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
							'count' => 135,
							'first' => '20211004094043',
							'last' => '20231004104043',
						],
						(object)[
							'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
							'count' => 11,
							'first' => '20211004094043',
							'last' => '20231004104043',
						],
					],
				],
			],
		];
	}
}
