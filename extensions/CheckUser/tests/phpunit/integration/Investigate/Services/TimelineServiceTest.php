<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Services;

use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Services\TimelineService
 * @covers \MediaWiki\CheckUser\Investigate\Services\ChangeService
 */
class TimelineServiceTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetQueryInfo
	 */
	public function testGetQueryInfo(
		array $targets,
		array $excludeTargets,
		bool $excludeTempAccounts,
		string $start,
		int $limit,
		array $expected
	): void {
		$user1 = $this->createMock( UserIdentity::class );
		$user1->method( 'getId' )
			->willReturn( 11111 );

		$user2 = $this->createMock( UserIdentity::class );
		$user2->method( 'getId' )
			->willReturn( 22222 );

		$tempUser1 = $this->createMock( UserIdentity::class );
		$tempUser1->method( 'getId' )
			->willReturn( 33333 );
		$tempUser2 = $this->createMock( UserIdentity::class );
		$tempUser2->method( 'getId' )
			->willReturn( 44444 );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )
			->willReturnMap(
				[
					[ 'User1', 0, $user1 ],
					[ 'User2', 0, $user2 ],
					[ '~2025-1', 0, $tempUser1 ],
					[ '~2025-2', 0, $tempUser2 ],
				]
			);

		$tempUserConfig = $this->getServiceContainer()->getTempUserConfig();
		$timelineService = new TimelineService(
			$this->getServiceContainer()->getConnectionProvider(),
			$userIdentityLookup,
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' ),
			$tempUserConfig
		);

		$queryInfo = $timelineService->getQueryInfo(
			$targets,
			$excludeTargets,
			$excludeTempAccounts,
			$start,
			$limit
		);

		foreach ( $expected['targets'] as $target ) {
			$this->assertStringContainsString( $target, $queryInfo['tables']['a'] );
		}

		foreach ( $expected['excludedTargets'] ?? [] as $excludedTarget ) {
			$this->assertStringContainsString( $excludedTarget, $queryInfo['tables']['a'] );
		}

		foreach ( $expected['conds'] as $cond ) {
			$this->assertStringContainsString( $cond, $queryInfo['tables']['a'] );
		}

		if ( count( $expected['targets'] ) > 0 && $expected['constraintOnActorName'] ) {
			$matchCondition = $tempUserConfig->getMatchCondition(
				$this->getDb(),
				'actor_name',
				IExpression::NOT_LIKE
			)->toSql( $this->getDb() );

			if ( $excludeTempAccounts ) {
				$this->assertStringContainsString(
					$matchCondition,
					$queryInfo['tables']['a']
				);
			} else {
				$this->assertStringNotContainsString(
					$matchCondition,
					$queryInfo['tables']['a']
				);
			}
		}

		if ( $expected['timestampAndLimitConstraints'] ) {
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

			// This assertion will fail on SQLite, as it does not support ORDER BY and LIMIT in UNION queries
			// so only run the assertion if the DB supports this.
			if ( $this->getDb()->unionSupportsOrderAndLimit() ) {
				$actualLimit = $limit + 1;
				$this->assertStringContainsString( "LIMIT $actualLimit", $queryInfo['tables']['a'] );
			}
		}
	}

	public static function provideGetQueryInfo(): array {
		$range = IPUtils::parseRange( '127.0.0.1/24' );
		return [
			'Valid username' => [
				'targets' => [ 'User1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => false,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111' ],
					'constraintOnActorName' => true,
					'conds' => [ 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Valid username, with start' => [
				'targets' => [ 'User1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => false,
				'start' => '111',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111' ],
					'constraintOnActorName' => true,
					'conds' => [ 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Valid IP' => [
				'targets' => [ '1.2.3.4' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => false,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ IPUtils::toHex( '1.2.3.4' ) ],
					'constraintOnActorName' => true,
					'conds' => [ 'cuc_ip_hex' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Multiple valid targets' => [
				'targets' => [ '1.2.3.4', 'User1', '~2025-1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => false,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111', IPUtils::toHex( '1.2.3.4' ), '33333' ],
					'constraintOnActorName' => true,
					'conds' => [ 'cuc_ip_hex', 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Multiple valid targets with some excluded' => [
				'targets' => [ '1.2.3.4', 'User1' ],
				'excludeTargets' => [ 'User2' ],
				'excludeTempAccounts' => false,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111', IPUtils::toHex( '1.2.3.4' ) ],
					'constraintOnActorName' => true,
					'excludedTargets' => [ '22222' ],
					'conds' => [ 'cuc_ip_hex', 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Multiple valid targets, excluding temporary accounts' => [
				'targets' => [ '1.2.3.4', 'User1', '~2025-1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111', IPUtils::toHex( '1.2.3.4' ) ],
					'constraintOnActorName' => true,
					'excludedTargets' => [],
					'conds' => [ 'cuc_ip_hex', 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Valid IP range' => [
				'targets' => [ '127.0.0.1/24', 'User1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 500,
				'expected' => [
					'targets' => [ '11111' ] + $range,
					'constraintOnActorName' => false,
					'conds' => [ 'cuc_ip_hex >=', 'cuc_ip_hex <=', 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Some valid targets' => [
				'targets' => [ 'User1', 'InvalidUser', '1.1..23', '::1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 20,
				'expected' => [
					'targets' => [ '11111', IPUtils::toHex( '::1' ) ],
					'constraintOnActorName' => false,
					'conds' => [ 'actor_user', 'cuc_ip_hex' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Requesting only temp accounts and not excluding temp accounts' => [
				'targets' => [ '~2025-1', '~2025-2' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => false,
				'start' => '',
				'limit' => 20,
				'expected' => [
					'targets' => [ '33333', '44444' ],
					'constraintOnActorName' => true,
					'conds' => [ 'actor_user' ],
					'timestampAndLimitConstraints' => true,
				],
			],
			'Requesting only temp accounts while filtering temp accounts out' => [
				'targets' => [ '~2025-1', '~2025-2' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 20,
				'expected' => [
					'targets' => [],
					'constraintOnActorName' => true,
					// an impossible condition is added when it is determined no
					// other WHERE clauses would be present in the query (which
					// would make the query itself fail).
					'conds' => [ '1=0' ],
					'timestampAndLimitConstraints' => false,
				],
			],
			'Requesting a single temp account while filtering temp accounts out' => [
				'targets' => [ '~2025-1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 50,
				'expected' => [
					'targets' => [],
					'constraintOnActorName' => true,
					// an impossible condition is added when it is determined no
					// other WHERE clauses would be present in the query (which
					// would make the query itself fail).
					'conds' => [ '1=0' ],
					'timestampAndLimitConstraints' => false,
				],
			],
			'Requesting and excluding a single temp account while filtering temp accounts out' => [
				'targets' => [ '~2025-1' ],
				'excludeTargets' => [ '~2025-1' ],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 50,
				'expected' => [
					'targets' => [],
					'constraintOnActorName' => true,
					// an impossible condition is added when it is determined no
					// other WHERE clauses would be present in the query (which
					// would make the query itself fail).
					'conds' => [ '1=0' ],
					'timestampAndLimitConstraints' => false,
				],
			],
			'Requesting temp and non-temp accounts while filtering temp accounts out' => [
				'targets' => [ '~2025-1', 'User1' ],
				'excludeTargets' => [],
				'excludeTempAccounts' => true,
				'start' => '',
				'limit' => 20,
				'expected' => [
					'targets' => [ '11111' ],
					'conds' => [ 'actor_user' ],
					'excludedTargets' => [],
					'constraintOnActorName' => false,
					'timestampAndLimitConstraints' => false,
				],
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForInvalidTargets */
	public function testGetQueryInfoForInvalidTargets( $targets ) {
		$this->expectException( LogicException::class );
		$this->getServiceContainer()->get( 'CheckUserTimelineService' )->getQueryInfo( $targets, [], false, '', 500 );
	}

	public static function provideGetQueryInfoForInvalidTargets() {
		return [
			'Invalid targets' => [ [ 'InvalidUser' ] ],
			'Empty targets' => [ [] ],
		];
	}

	public function testCastValueToTypeForPostgres() {
		// Mock that the database that says it is the 'postgres' DB type.
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockDbr->method( 'getType' )
			->willReturn( 'postgres' );
		$mockConnectionProvider = $this->createMock( IConnectionProvider::class );
		$mockConnectionProvider->method( 'getReplicaDatabase' )->willReturn( $mockDbr );
		// Get the object under test while using the mock IConnectionProvider that returns a mock DB type.
		$timelineService = new TimelineService(
			$mockConnectionProvider,
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' ),
			$this->getServiceContainer()->getTempUserConfig()
		);
		// Call the method under test
		$timelineService = TestingAccessWrapper::newFromObject( $timelineService );
		$this->assertSame(
			'CAST(0 AS smallint)',
			$timelineService->castValueToType( '0', 'smallint' )
		);
	}
}
