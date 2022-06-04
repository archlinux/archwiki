<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Consequences\Consequence;

use BagOStuff;
use Generator;
use HashBagOStuff;
use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Throttle;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequenceNotPrecheckedException;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Title;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Throttle
 * @covers ::__construct
 */
class ThrottleTest extends MediaWikiUnitTestCase {

	private function getThrottle(
		array $throttleParams = [],
		BagOStuff $cache = null,
		bool $globalFilter = false,
		User $user = null,
		Title $title = null,
		UserEditTracker $editTracker = null,
		string $ip = null
	) {
		$params = $this->createMock( Parameters::class );
		$params->method( 'getIsGlobalFilter' )->willReturn( $globalFilter );
		if ( $user ) {
			$params->method( 'getUser' )->willReturn( $user );
		}
		if ( $title ) {
			$params->method( 'getTarget' )->willReturn( $title );
		}
		return new Throttle(
			$params,
			$throttleParams + [ 'groups' => [ 'user' ], 'count' => 3, 'period' => 60, 'id' => 1 ],
			$cache ?? new HashBagOStuff(),
			$editTracker ?? $this->createMock( UserEditTracker::class ),
			$this->createMock( UserFactory::class ),
			new NullLogger(),
			$ip ?? '1.2.3.4',
			false,
			$globalFilter ? 'foo-db' : null
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_notPrechecked() {
		$throttle = $this->getThrottle();
		$this->expectException( ConsequenceNotPrecheckedException::class );
		$throttle->execute();
	}

	public function provideThrottle() {
		foreach ( [ false, true ] as $global ) {
			$globalStr = $global ? 'global' : 'local';
			yield "no groups, $globalStr" => [ $this->getThrottle( [ 'groups' => [] ], null, $global ), true ];

			$cache = $this->getMockBuilder( HashBagOStuff::class )->onlyMethods( [ 'incrWithInit' ] )->getMock();
			yield "no cache value set, $globalStr" => [ $this->getThrottle( [], $cache, $global ), true, $cache ];

			$groups = [ 'ip', 'user', 'range', 'creationdate', 'editcount', 'site', 'page' ];
			foreach ( $groups as $group ) {
				$throttle = $this->getThrottle( [ 'groups' => [ $group ], 'count' => 0 ], null, $global );
				$throttleWr = TestingAccessWrapper::newFromObject( $throttle );
				$throttleWr->setThrottled( $group );
				yield "$group set, $globalStr" => [ $throttle, false ];
			}
		}
	}

	/**
	 * @covers ::shouldDisableOtherConsequences
	 * @covers ::isThrottled
	 * @covers ::throttleKey
	 * @covers ::throttleIdentifier
	 * @dataProvider provideThrottle
	 */
	public function testShouldDisableOtherConsequences( Throttle $throttle, bool $shouldDisable ) {
		$this->assertSame( $shouldDisable, $throttle->shouldDisableOtherConsequences() );
	}

	/**
	 * @covers ::execute
	 * @covers ::setThrottled
	 * @covers ::throttleKey
	 * @covers ::throttleIdentifier
	 * @dataProvider provideThrottle
	 */
	public function testExecute( Throttle $throttle, bool $shouldDisable, MockObject $cache = null ) {
		if ( $cache ) {
			$groupCount = count( TestingAccessWrapper::newFromObject( $throttle )->throttleParams['groups'] );
			$cache->expects( $this->exactly( $groupCount ) )->method( 'incrWithInit' );
		}
		$throttle->shouldDisableOtherConsequences();
		$this->assertSame( $shouldDisable, $throttle->execute() );
	}

	/**
	 * @covers ::throttleIdentifier
	 * @dataProvider provideThrottleDataForIdentifiers
	 */
	public function testThrottleIdentifier(
		string $type,
		?string $expected,
		string $ip,
		Title $title,
		User $user,
		UserEditTracker $editTracker = null
	) {
		$throttle = $this->getThrottle( [], null, false, $user, $title, $editTracker, $ip );
		/** @var Throttle $throttleWrapper */
		$throttleWrapper = TestingAccessWrapper::newFromObject( $throttle );

		if ( $expected === null ) {
			$this->expectException( InvalidArgumentException::class );
		}

		$this->assertSame( $expected, $throttleWrapper->throttleIdentifier( $type ) );
	}

	public function provideThrottleDataForIdentifiers(): Generator {
		$pageName = 'AbuseFilter test throttle identifiers';
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( $pageName );
		$user = $this->createMock( User::class );
		$ip = '42.42.42.42';

		yield 'IP, simple' => [ 'ip', "ip-$ip", $ip, $title, $user ];

		$userID = 123;
		$user->method( 'isAnon' )->willReturn( false );
		$user->method( 'getId' )->willReturn( $userID );
		yield 'user, registered' => [ 'user', "user-$userID", $ip, $title, $user ];

		$anonID = 0;
		$anon = $this->createMock( User::class );
		$anon->method( 'getId' )->willReturn( $anonID );
		yield 'user, anonymous' => [ 'user', "user-$anonID", $ip, $title, $anon ];

		$editcount = 5;
		$uet = $this->createMock( UserEditTracker::class );
		$uet->method( 'getUserEditCount' )->with( $user )->willReturn( $editcount );
		yield 'editcount, simple' => [ 'editcount', "editcount-$editcount", $ip, $title, $user, $uet ];

		yield 'page, simple' => [ 'page', "page-$pageName", $ip, $title, $user ];

		yield 'site, simple' => [ 'site', 'site-1', $ip, $title, $user ];

		yield 'non-existing throttle type' => [ 'foo', null, $ip, $title, $user ];

		$testingIPs = [
			'123.123.123.123' => '123.123.0.0/16',
			'100.0.0.0' => '100.0.0.0/16',
			'255.255.0.0' => '255.255.0.0/16',
			'1.2.3.4' => '1.2.0.0/16',
			'2001:0db8:0000:0000:0000:0000:1428:57ab' => '2001:DB8:0:0:0:0:0:0/64',
			'2001:0db8::1428:57ab' => '2001:DB8:0:0:0:0:0:0/64',
			'2001:0dff:ffff:ffff:ffff:ffff:ffff:ffff' => '2001:DFF:FFFF:FFFF:0:0:0:0/64',
			'2001:db8::' => '2001:DB8:0:0:0:0:0:0/64',
			'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff' => 'FFFF:FFFF:FFFF:FFFF:0:0:0:0/64'
		];
		foreach ( $testingIPs as $testIP => $expected ) {
			yield "range, $testIP" => [ 'range', "range-$expected", $testIP, $title, $user ];
		}
	}
}
