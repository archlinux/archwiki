<?php

namespace MediaWiki\Tests\Integration\Permissions;

use CentralIdLookup;
use HashBagOStuff;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\RateLimiter;
use MediaWiki\Permissions\RateLimitSubject;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\WRStats\BagOStuffStatsStore;
use Wikimedia\WRStats\WRStatsFactory;

/**
 * @coversDefaultClass \MediaWiki\Permissions\RateLimiter
 */
class RateLimiterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return MockObject|CentralIdLookup
	 */
	private function getMockContralIdProvider() {
		$mockCentralIdLookup = $this->createNoOpMock(
			CentralIdLookup::class,
			[ 'centralIdFromLocalUser', 'getProviderId' ]
		);

		$mockCentralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturnCallback( static function ( UserIdentity $user ) {
				return $user->getId() % 100;
			} );
		$mockCentralIdLookup->method( 'getProviderId' )
			->willReturn( 'test' );

		return $mockCentralIdLookup;
	}

	/**
	 * @covers ::limit
	 * @covers ::__construct
	 * @covers ::getConditions
	 * @covers \Wikimedia\WRStats\WRStatsFactory
	 * @covers \Wikimedia\WRStats\BagOStuffStatsStore
	 */
	public function testPingLimiterGlobal() {
		$limits = [
			'edit' => [
				'anon' => [ 1, 60 ],
			],
			'purge' => [
				'ip' => [ 1, 60 ],
				'subnet' => [ 1, 60 ],
			],
			'rollback' => [
				'user' => [ 1, 60 ],
			],
			'move' => [
				'user-global' => [ 1, 60 ],
			],
			'delete' => [
				'ip-all' => [ 1, 60 ],
				'subnet-all' => [ 1, 60 ],
			],
		];

		// Set up a fake cache for storing limits
		$cache = new HashBagOStuff( [ 'keyspace' => 'xwiki' ] );
		$cacheAccess = TestingAccessWrapper::newFromObject( $cache );
		$cacheAccess->keyspace = 'xwiki';

		$statsFactory = new WRStatsFactory( new BagOStuffStatsStore( $cache ) );

		$limiter = $this->newRateLimiter( $limits, [], $statsFactory );

		// Set up some fake users
		$anon1 = $this->newFakeAnon( '1.2.3.4' );
		$anon2 = $this->newFakeAnon( '1.2.3.8' );
		$anon3 = $this->newFakeAnon( '6.7.8.9' );
		$anon4 = $this->newFakeAnon( '6.7.8.1' );

		// The mock ContralIdProvider uses the local id MOD 10 as the global ID.
		// So Frank has global ID 11, and Jane has global ID 56.
		// Kara's global ID is 0, which means no global ID.
		$frankX1 = $this->newFakeUser( 'Frank', '1.2.3.4', 111 );
		$frankX2 = $this->newFakeUser( 'Frank', '1.2.3.8', 111 );
		$frankY1 = $this->newFakeUser( 'Frank', '1.2.3.4', 211 );
		$janeX1 = $this->newFakeUser( 'Jane', '1.2.3.4', 456 );
		$janeX3 = $this->newFakeUser( 'Jane', '6.7.8.9', 456 );
		$janeY1 = $this->newFakeUser( 'Jane', '1.2.3.4', 756 );
		$karaX1 = $this->newFakeUser( 'Kara', '5.5.5.5', 100 );
		$karaY1 = $this->newFakeUser( 'Kara', '5.5.5.5', 200 );

		// Test limits on wiki X
		$this->assertFalse( $limiter->limit( $anon1, 'edit' ), 'First anon edit' );
		$this->assertTrue( $limiter->limit( $anon2, 'edit' ), 'Second anon edit' );

		$this->assertFalse( $limiter->limit( $anon1, 'purge' ), 'Anon purge' );
		$this->assertTrue( $limiter->limit( $anon1, 'purge' ), 'Anon purge via same IP' );

		$this->assertFalse( $limiter->limit( $anon3, 'purge' ), 'Anon purge via different subnet' );
		$this->assertTrue( $limiter->limit( $anon2, 'purge' ), 'Anon purge via same subnet' );

		$this->assertFalse( $limiter->limit( $frankX1, 'rollback' ), 'First rollback' );
		$this->assertTrue( $limiter->limit( $frankX2, 'rollback' ), 'Second rollback via different IP' );
		$this->assertFalse( $limiter->limit( $janeX1, 'rollback' ), 'Rlbk by different user, same IP' );

		$this->assertFalse( $limiter->limit( $frankX1, 'move' ), 'First move' );
		$this->assertTrue( $limiter->limit( $frankX2, 'move' ), 'Second move via different IP' );
		$this->assertFalse( $limiter->limit( $janeX1, 'move' ), 'Move by different user, same IP' );
		$this->assertFalse( $limiter->limit( $karaX1, 'move' ), 'Move by another user' );
		$this->assertTrue( $limiter->limit( $karaX1, 'move' ), 'Second move by another user' );

		$this->assertFalse( $limiter->limit( $frankX1, 'delete' ), 'First delete' );
		$this->assertTrue( $limiter->limit( $janeX1, 'delete' ), 'Delete via same IP' );

		$this->assertTrue( $limiter->limit( $frankX2, 'delete' ), 'Delete via same subnet' );
		$this->assertFalse( $limiter->limit( $janeX3, 'delete' ), 'Delete via different subnet' );

		// Now test how limits carry over to wiki Y
		$cacheAccess->keyspace = 'ywiki';

		$this->assertFalse( $limiter->limit( $anon3, 'edit' ), 'Anon edit on wiki Y' );
		$this->assertTrue( $limiter->limit( $anon4, 'purge' ), 'Anon purge on wiki Y, same subnet' );
		$this->assertFalse( $limiter->limit( $frankY1, 'rollback' ), 'Rollback on wiki Y, same name' );
		$this->assertTrue( $limiter->limit( $frankY1, 'move' ), 'Move on wiki Y, same name' );
		$this->assertTrue( $limiter->limit( $janeY1, 'move' ), 'Move on wiki Y, different user' );
		$this->assertTrue( $limiter->limit( $frankY1, 'delete' ), 'Delete on wiki Y, same IP' );

		// For a user without a global ID, user-global acts as a local restriction
		$this->assertFalse( $limiter->limit( $karaY1, 'move' ), 'Move by another user' );
		$this->assertTrue( $limiter->limit( $karaY1, 'move' ), 'Second move by another user' );
	}

	/**
	 * @covers ::limit
	 * @covers ::getConditions
	 */
	public function testPingLimiterWithStaleCache() {
		$limits = [
			'edit' => [
				'user' => [ 1, 60 ],
			],
		];

		$bagTime = 1600000000.0;
		$appTime = 1600000000;
		$bag = new HashBagOStuff();

		$statsFactory = new WRStatsFactory( new BagOStuffStatsStore( $bag ) );
		$statsFactory->setCurrentTime( $appTime );

		$bag->setMockTime( $bagTime ); // this is a reference!

		$user = $this->newFakeUser( 'Frank', '1.2.3.4', 111 );
		$limiter = $this->newRateLimiter( $limits, [], $statsFactory );

		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'limit not reached' );
		$this->assertTrue( $limiter->limit( $user, 'edit' ), 'limit reached' );

		// Make it so that rate limits are expired according to MWTimestamp::time(),
		// but not according to $cache->getCurrentTime(), emulating the conditions
		// that trigger T246991.
		$bagTime += 10;
		$statsFactory->setCurrentTime( $appTime += 100 );

		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'limit expired' );
		$this->assertTrue( $limiter->limit( $user, 'edit' ), 'limit functional after expiry' );
	}

	/**
	 * @covers ::limit
	 * @covers ::getConditions
	 */
	public function testPingLimiterRate() {
		$limits = [
			'edit' => [
				'user' => [ 3, 60 ],
			],
		];

		$fakeTime = 1600000000;
		$cache = new HashBagOStuff();

		$cache->setMockTime( $fakeTime ); // this is a reference!
		$statsFactory = new WRStatsFactory( new BagOStuffStatsStore( $cache ) );
		$statsFactory->setCurrentTime( $fakeTime );

		$user = $this->newFakeUser( 'Frank', '1.2.3.4', 111 );
		$limiter = $this->newRateLimiter( $limits, [], $statsFactory );

		// The limit is 3 per 60 second. Do 5 edits at an emulated 50 second interval.
		// They should all pass. This tests that the counter doesn't just keeps increasing
		// but gets reset in an appropriate way.
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'first ping should pass' );

		$statsFactory->setCurrentTime( $fakeTime += 50 );
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'second ping should pass' );

		$statsFactory->setCurrentTime( $fakeTime += 50 );
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'third ping should pass' );

		$statsFactory->setCurrentTime( $fakeTime += 50 );
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'fourth ping should pass' );

		$statsFactory->setCurrentTime( $fakeTime += 50 );
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'fifth ping should pass' );
	}

	/**
	 * @covers ::limit
	 */
	public function testPingLimiterHook() {
		$limits = [
			'edit' => [
				'user' => [ 3, 60 ],
			],
		];

		$user = $this->newFakeUser( 'Frank', '1.2.3.4', 111 );
		$limiter = $this->newRateLimiter( $limits, [] );

		// Hook leaves $result false
		$this->setTemporaryHook(
			'PingLimiter',
			static function ( &$user, $action, &$result, $incrBy ) {
				return false;
			}
		);
		$this->assertFalse(
			$limiter->limit( $user, 'edit' ),
			'Hooks that just return false leave $result false'
		);
		$this->removeTemporaryHook( 'PingLimiter' );

		// Hook sets $result to true
		$this->setTemporaryHook(
			'PingLimiter',
			static function ( &$user, $action, &$result, $incrBy ) {
				$result = true;
				return false;
			}
		);
		$this->assertTrue(
			$limiter->limit( $user, 'edit' ),
			'Hooks can set $result to true'
		);
		$this->removeTemporaryHook( 'PingLimiter' );

		// Unknown action
		$this->assertFalse(
			$limiter->limit( $user, 'FakeActionWithNoRateLimit' ),
			'Actions with no rate limit set do not trip the rate limiter'
		);
	}

	public function provideIsExempt() {
		$user = new UserIdentityValue( 123, 'Foo' );

		yield 'IP not excluded'
			=> [ [], new RateLimitSubject( $user, '1.2.3.4', [] ), false ];

		yield 'IP excluded'
			=> [ [ '1.2.3.4' ], new RateLimitSubject( $user, '1.2.3.4', [] ), true ];

		yield 'IP subnet excluded'
			=> [ [ '1.2.3.0/8' ], new RateLimitSubject( $user, '1.2.3.4', [] ), true ];

		$flags = [ RateLimitSubject::EXEMPT => true ];
		yield 'noratelimit right'
			=> [ [], new RateLimitSubject( $user, '1.2.3.4', $flags ), true ];
	}

	/**
	 * @dataProvider provideIsExempt
	 * @covers ::isExempt
	 *
	 * @param array $rateLimitExcludeIps
	 * @param RateLimitSubject $subject
	 * @param bool $expected
	 */
	public function testIsExempt(
		array $rateLimitExcludeIps,
		RateLimitSubject $subject,
		bool $expected
	) {
		$limiter = $this->newRateLimiter( [], $rateLimitExcludeIps );

		$this->assertSame( $expected, $limiter->isExempt( $subject ) );
	}

	private function newFakeAnon( string $ip ) {
		return new RateLimitSubject(
			new UserIdentityValue( 0, $ip ),
			$ip,
			[ RateLimitSubject::NEWBIE => true ]
		);
	}

	private function newFakeUser( string $name, string $ip, int $id, $newbie = false ) {
		return new RateLimitSubject(
			new UserIdentityValue( $id, $name ),
			$ip,
			[ RateLimitSubject::NEWBIE => $newbie ]
		);
	}

	/**
	 * @param array $limits
	 * @param array $excludedIPs
	 * @param WRStatsFactory|null $statsFactory
	 *
	 * @return RateLimiter
	 * @throws \Exception
	 */
	protected function newRateLimiter(
		array $limits,
		array $excludedIPs,
		WRStatsFactory $statsFactory = null
	): RateLimiter {
		if ( $statsFactory === null ) {
			$statsFactory = new WRStatsFactory( new BagOStuffStatsStore( new HashBagOStuff() ) );
		}

		$services = $this->getServiceContainer();

		$limiter = new RateLimiter(
			new ServiceOptions( RateLimiter::CONSTRUCTOR_OPTIONS, [
				MainConfigNames::RateLimits => $limits,
				MainConfigNames::RateLimitsExcludedIPs => $excludedIPs,
			] ),
			$statsFactory,
			$this->getMockContralIdProvider(),
			$services->getUserFactory(),
			$services->getUserGroupManager(),
			$services->getHookContainer()
		);

		return $limiter;
	}

	/**
	 * Test limit with different limit types
	 * @covers ::limit
	 * @covers ::getConditions
	 */
	public function testLimitTypes() {
		$limits = [
			'edit' => [
				'user' => [ 1, 60 ],
				'ip' => [ 2, 60 ],
			],
		];

		$user1 = $this->newFakeUser( 'User1', '127.0.0.1', 1, true );
		$user2 = $this->newFakeUser( 'User2', '127.0.0.1', 2, true );
		$user3 = $this->newFakeUser( 'User3', '127.0.0.1', 3, true );

		$limiter = $this->newRateLimiter( $limits, [] );
		$this->assertFalse( $limiter->limit( $user1, 'edit' ) );
		$this->assertFalse( $limiter->limit( $user2, 'edit' ) );
		$this->assertTrue( $limiter->limit( $user3, 'edit' ) );
	}

	/**
	 * Test that '&can-bypass' can be used to impose limits on users
	 * who are otherwise exempt from limits.
	 *
	 * @covers ::limit
	 */
	public function testCanBypass() {
		$limits = [
			'edit' => [
				'user' => [ 1, 60 ],
			],
			'delete' => [
				'&can-bypass' => false,
				'user' => [ 1, 60 ],
			],
		];

		$user = new RateLimitSubject(
			new UserIdentityValue( 7, 'Garth' ),
			'127.0.0.1',
			[ RateLimitSubject::EXEMPT => true ]
		);

		$limiter = $this->newRateLimiter( $limits, [] );
		$this->assertFalse( $limiter->limit( $user, 'edit' ) );
		$this->assertFalse( $limiter->limit( $user, 'delete' ) );

		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'bypass should be granted' );
		$this->assertTrue( $limiter->limit( $user, 'delete' ), 'bypass should be denied' );
	}

	/**
	 * Test that the most permissive limit is used when a limit is defined for
	 * multiple groups a user belongs to.
	 *
	 * @covers ::limit
	 */
	public function testGroupLimits() {
		$limits = [
			'edit' => [
				'user' => [ 1, 60 ],
				'autoconfirmed' => [ 2, 60 ],
			],
		];

		$user = $this->getTestUser( [ 'autoconfirmed' ] )->getUser();
		$user = new RateLimitSubject( $user, '127.0.0.1', [] );

		$limiter = $this->newRateLimiter( $limits, [] );
		$this->assertFalse( $limiter->limit( $user, 'edit' ) );
		$this->assertFalse( $limiter->limit( $user, 'edit' ), 'limit for autoconfirmed used' );
		$this->assertTrue( $limiter->limit( $user, 'edit' ), 'limit for autoconfirmed exceeded' );
	}

}
