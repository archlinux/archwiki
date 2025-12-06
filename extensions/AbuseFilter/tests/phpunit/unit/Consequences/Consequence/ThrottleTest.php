<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Consequences\Consequence;

use Generator;
use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Throttle;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequenceNotPrecheckedException;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Throttle
 */
class ThrottleTest extends MediaWikiUnitTestCase {

	private function getThrottle(
		array $throttleParams = [],
		?BagOStuff $cache = null,
		bool $globalFilter = false,
		?UserIdentity $user = null,
		?Title $title = null,
		?UserEditTracker $editTracker = null,
		?string $ip = null
	) {
		$specifier = new ActionSpecifier(
			'some-action',
			$title ?? $this->createMock( LinkTarget::class ),
			$user ?? $this->createMock( UserIdentity::class ),
			$ip ?? '1.2.3.4',
			null
		);
		$params = new Parameters(
			$this->createMock( ExistingFilter::class ),
			$globalFilter,
			$specifier
		);
		return new Throttle(
			$params,
			$throttleParams + [ 'groups' => [ 'user' ], 'count' => 3, 'period' => 60, 'id' => 1 ],
			$cache ?? new HashBagOStuff(),
			$editTracker ?? $this->createMock( UserEditTracker::class ),
			$this->createMock( UserFactory::class ),
			new NullLogger(),
			false,
			$globalFilter ? 'foo-db' : null
		);
	}

	public function testExecute_notPrechecked() {
		$throttle = $this->getThrottle();
		$this->expectException( ConsequenceNotPrecheckedException::class );
		$throttle->execute();
	}

	public static function provideThrottle() {
		foreach ( [ false, true ] as $global ) {
			$globalStr = $global ? 'global' : 'local';
			yield "no groups, $globalStr" => [ [ 'groups' => [] ], $global, true ];

			yield "no cache value set, $globalStr" => [ [], $global, true, true ];

			$groups = [ 'ip', 'user', 'range', 'creationdate', 'editcount', 'site', 'page' ];
			foreach ( $groups as $group ) {
				yield "$group set, $globalStr" => [ [ 'groups' => [ $group ], 'count' => 0 ], $global, false ];
			}
		}
	}

	/**
	 * @dataProvider provideThrottle
	 */
	public function testShouldDisableOtherConsequences(
		array $throttleParams, bool $globalFilter, bool $shouldDisable
	) {
		$throttle = $this->getThrottle( $throttleParams, null, $globalFilter );
		if ( ( $throttleParams['groups'] ?? [] ) !== [] ) {
			$wrapper = TestingAccessWrapper::newFromObject( $throttle );
			$wrapper->setThrottled( $throttleParams['groups'][0] );
		}

		$this->assertSame( $shouldDisable, $throttle->shouldDisableOtherConsequences() );
	}

	/**
	 * @dataProvider provideThrottle
	 */
	public function testExecute(
		array $throttleParams, bool $globalFilter, bool $shouldDisable, bool $withCache = false
	) {
		$cache = $withCache
			? $this->getMockBuilder( HashBagOStuff::class )->onlyMethods( [ 'incrWithInit' ] )->getMock()
			: null;
		$throttle = $this->getThrottle( $throttleParams, $cache, $globalFilter );
		if ( ( $throttleParams['groups'] ?? [] ) !== [] ) {
			$wrapper = TestingAccessWrapper::newFromObject( $throttle );
			$wrapper->setThrottled( $throttleParams['groups'][0] );
		}
		if ( $cache ) {
			/** @var Throttle $wrapper */
			$wrapper = TestingAccessWrapper::newFromObject( $throttle );
			$groupCount = count( $wrapper->throttleParams['groups'] );
			$cache->expects( $this->exactly( $groupCount ) )->method( 'incrWithInit' );
		}
		$throttle->shouldDisableOtherConsequences();
		$this->assertSame( $shouldDisable, $throttle->execute() );
	}

	/**
	 * @dataProvider provideThrottleDataForIdentifiers
	 */
	public function testThrottleIdentifier(
		string $type,
		?string $expected,
		string $ip,
		array $userSpec,
		?int $editCount = null
	) {
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'AbuseFilter test throttle identifiers' );
		$user = new UserIdentityValue( ...$userSpec );
		if ( $editCount !== null ) {
			$editTracker = $this->createMock( UserEditTracker::class );
			$editTracker->method( 'getUserEditCount' )->with( $user )->willReturn( $editCount );
		} else {
			$editTracker = null;
		}

		$throttle = $this->getThrottle( [], null, false, $user, $title, $editTracker, $ip );
		/** @var Throttle $throttleWrapper */
		$throttleWrapper = TestingAccessWrapper::newFromObject( $throttle );

		if ( $expected === null ) {
			$this->expectException( InvalidArgumentException::class );
		}

		$this->assertSame( $expected, $throttleWrapper->throttleIdentifier( $type ) );
	}

	public static function provideThrottleDataForIdentifiers(): Generator {
		$ip = '42.42.42.42';
		$anon = [ 0, $ip ];

		yield 'IP, simple' => [ 'ip', "ip-$ip", $ip, $anon ];
		yield 'user, anonymous' => [ 'user', 'user-0', $ip, $anon ];

		$userID = 123;
		$user = [ $userID, 'Username' ];
		yield 'user, registered' => [ 'user', "user-$userID", $ip, $user ];

		$editcount = 5;
		yield 'editcount, simple' => [ 'editcount', "editcount-$editcount", $ip, $user, $editcount ];

		yield 'page, simple' => [ 'page', "page-AbuseFilter test throttle identifiers", $ip, $user ];

		yield 'site, simple' => [ 'site', 'site-1', $ip, $user ];

		yield 'non-existing throttle type' => [ 'foo', null, $ip, $user ];

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
			yield "range, $testIP" => [ 'range', "range-$expected", $testIP, $user ];
		}
	}
}
