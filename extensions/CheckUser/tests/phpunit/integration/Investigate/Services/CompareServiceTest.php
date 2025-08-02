<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Tests\Integration\Investigate\CompareTabTestDataTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Tests\Unit\Libs\Rdbms\AddQuoterMock;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\Platform\MySQLPlatform;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Services\CompareService
 * @covers \MediaWiki\CheckUser\Investigate\Services\ChangeService
 */
class CompareServiceTest extends MediaWikiIntegrationTestCase {

	use CompareTabTestDataTrait;

	protected function setUp(): void {
		// Pin time to avoid failure when next second starts - T317411
		ConvertibleTimestamp::setFakeTime( '20220904094043' );
	}

	private function getCompareService(): CompareService {
		return $this->getServiceContainer()->get( 'CheckUserCompareService' );
	}

	/**
	 * Sanity check for the subqueries built by getQueryInfo. Checks for the presence
	 * of valid targets and the presence of the expected per-target limit. Whitespace
	 * is not always predictable so look for the bare minimum in the SQL string.
	 *
	 * Invalid targets are tested in ComparePagerTest::testDoQuery.
	 *
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo( $options, $expected ) {
		$this->overrideConfigValue( 'CheckUserInvestigateMaximumRowCount', $options['limit'] );

		$db = $this->getMockBuilder( Database::class )
			->onlyMethods( [
				'dbSchema',
				'tablePrefix',
			] )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$db->method( 'strencode' )
			->willReturnArgument( 0 );
		$db->method( 'dbSchema' )
			->willReturn( '' );
		$db->method( 'tablePrefix' )
			->willReturn( '' );
		$wdb = TestingAccessWrapper::newFromObject( $db );
		$wdb->platform = new MySQLPlatform( new AddQuoterMock() );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturn( $db );
		$dbProvider->method( 'getPrimaryDatabase' )
			->willReturn( $db );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )
			->willReturn( 11111 );

		$user2 = $this->createMock( UserIdentity::class );
		$user2->method( 'getId' )
			->willReturn( 22222 );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )
			->willReturnMap(
				[
					[ 'User1', 0, $user, ],
					[ 'User2', 0, $user2, ],
				]
			);

		$compareService = new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			$dbProvider,
			$userIdentityLookup,
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
		);

		$queryInfo = $compareService->getQueryInfo(
			$options['targets'],
			$options['excludeTargets'],
			$options['start']
		);

		foreach ( $expected['targets'] as $target ) {
			$this->assertStringContainsString( $target, $queryInfo['tables']['a'] );
		}

		foreach ( $expected['excludeTargets'] as $excludeTarget ) {
			$this->assertStringContainsString( $excludeTarget, $queryInfo['tables']['a'] );
		}

		$this->assertStringContainsString( 'LIMIT ' . $expected['limit'], $queryInfo['tables']['a'] );

		$start = $expected['start'];
		if ( $start !== '' ) {
			$start = $this->getDb()->timestamp( $start );
		}
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$this->assertStringContainsString( $table, $queryInfo['tables']['a'] );
			$columnPrefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];
			if ( $start === '' ) {
				$this->assertStringNotContainsString( $columnPrefix . 'timestamp >=', $queryInfo['tables']['a'] );
			} else {
				$this->assertStringContainsString(
					$columnPrefix . "timestamp >= '$start'", $queryInfo['tables']['a']
				);
			}
		}
	}

	public static function provideGetQueryInfo() {
		return [
			'Valid username, excluded IP' => [
				[
					'targets' => [ 'User1' ],
					'excludeTargets' => [ '0:0:0:0:0:0:0:1' ],
					'limit' => 100000,
					'start' => '',
				],
				[
					'targets' => [ '11111' ],
					'excludeTargets' => [ 'v6-00000000000000000000000000000001' ],
					'limit' => '33334',
					'start' => ''
				],
			],
			'Valid username, excluded IP, with start' => [
				[
					'targets' => [ 'User1' ],
					'excludeTargets' => [ '0:0:0:0:0:0:0:1' ],
					'limit' => 10000,
					'start' => '20230405060708',
				],
				[
					'targets' => [ '11111' ],
					'excludeTargets' => [ 'v6-00000000000000000000000000000001' ],
					'limit' => '3334',
					'start' => '20230405060708'
				],
			],
			'Single valid IP, excluded username' => [
				[
					'targets' => [ '0:0:0:0:0:0:0:1' ],
					'excludeTargets' => [ 'User1' ],
					'limit' => 100000,
					'start' => '',
				],
				[
					'targets' => [ 'v6-00000000000000000000000000000001' ],
					'excludeTargets' => [ '11111' ],
					'limit' => '33334',
					'start' => ''
				],
			],
			'Valid username and IP, excluded username and IP' => [
				[
					'targets' => [ 'User1', '1.2.3.4' ],
					'excludeTargets' => [ 'User2', '1.2.3.5' ],
					'limit' => 100,
					'start' => '',
				],
				[
					'targets' => [ '11111', '01020304' ],
					'excludeTargets' => [ '22222', '01020305' ],
					'limit' => '17',
					'start' => ''
				],
			],
			'Two valid IPs' => [
				[
					'targets' => [ '0:0:0:0:0:0:0:1', '1.2.3.4' ],
					'excludeTargets' => [],
					'limit' => 100000,
					'start' => '',
				],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304'
					],
					'excludeTargets' => [],
					'limit' => '16667',
					'start' => ''
				],
			],
			'Valid IP addresses and IP range' => [
				[
					'targets' => [
						'0:0:0:0:0:0:0:1',
						'1.2.3.4',
						'1.2.3.4/16',
					],
					'excludeTargets' => [],
					'limit' => 100000,
					'start' => '',
				],
				[
					'targets' => [
						'v6-00000000000000000000000000000001',
						'01020304',
						'01020000',
						'0102FFFF',
					],
					'excludeTargets' => [],
					'limit' => '11112',
					'start' => ''
				],
			],
			'IP range outside of range limits with valid user target' => [
				[
					'targets' => [ 'User1', '1.2.3.4/1', ], 'excludeTargets' => [], 'limit' => 100000,
					'start' => '',
				],
				[ 'targets' => [ '11111' ], 'excludeTargets' => [], 'limit' => 16667, 'start' => '' ],
			],
		];
	}

	public function testGetQueryInfoNoTargets() {
		$this->expectException( \LogicException::class );

		$this->getCompareService()->getQueryInfo( [], [], '' );
	}

	/**
	 * @dataProvider provideTotalActionsFromIP
	 */
	public function testGetTotalActionsFromIP( $data, $expected ) {
		$result = $this->getCompareService()->getTotalActionsFromIP( $data['ip'] );

		$this->assertEquals( $expected, $result );
	}

	public static function provideTotalActionsFromIP() {
		return [
			'IP address with multiple users' => [ [ 'ip' => IPUtils::toHex( '1.2.3.5' ) ], 4 ],
			'IP address no users' => [ [ 'ip' => IPUtils::toHex( '8.7.6.5' ) ], 0 ],
		];
	}

	/**
	 * @dataProvider provideGetTargetsOverLimit
	 */
	public function testGetTargetsOverLimit( $data, $expected ) {
		if ( isset( $data['limit'] ) ) {
			$this->overrideConfigValue( 'CheckUserInvestigateMaximumRowCount', $data['limit'] );
		}

		$result = $this->getCompareService()->getTargetsOverLimit(
			$data['targets'] ?? [],
			$data['excludeTargets'] ?? [],
			$this->getDb()->timestamp()
		);

		if ( $this->getDb()->unionSupportsOrderAndLimit() ) {
			$this->assertEquals( $expected, $result );
		} else {
			$this->assertArrayEquals( [], $result );
		}
	}

	public static function provideGetTargetsOverLimit() {
		return [
			'Empty targets array' => [ [], [] ],
			'Targets are all within limits' => [
				[ 'targets' => [ '1.2.3.4', 'User1', '1.2.3.5' ], 'limit' => 100, ], [],
			],
			'One target is over limit' => [
				[
					'targets' => [ '1.2.3.4', 'User1', '1.2.3.5' ],
					'excludeTargets' => [ '1.2.3.5' ],
					'limit' => 10,
				],
				[ '1.2.3.4' ],
			],
			'Two targets are over limit' => [
				[ 'targets' => [ '1.2.3.4', '1.2.3.5' ], 'limit' => 1 ],
				[ '1.2.3.4', '1.2.3.5' ],
			],
		];
	}

	public function addDBDataOnce() {
		$this->addTestingDataToDB();
	}
}
