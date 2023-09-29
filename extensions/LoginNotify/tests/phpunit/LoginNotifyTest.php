<?php

use LoginNotify\LoginNotify;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \LoginNotify\LoginNotify
 * @group LoginNotify
 */
class LoginNotifyTest extends MediaWikiIntegrationTestCase {

	private $inst;

	public function setUpLoginNotify() {
		$config = new HashConfig( [
			"LoginNotifyAttemptsKnownIP" => 15,
			"LoginNotifyExpiryKnownIP" => 604800,
			"LoginNotifyAttemptsNewIP" => 5,
			"LoginNotifyExpiryNewIP" => 1209600,
			"LoginNotifyCheckKnownIPs" => true,
			"LoginNotifyEnableOnSuccess" => true,
			"LoginNotifyEnableForPriv" => [ "editinterface", "userrights" ],
			"LoginNotifySecretKey" => "Secret Stuff!",
			"LoginNotifyCookieExpire" => 15552000,
			"LoginNotifyCookieDomain" => null,
			"LoginNotifyMaxCookieRecords" => 6,
			"LoginNotifyCacheLoginIPExpiry" => 60 * 60 * 24 * 60
		] );
		$this->inst = TestingAccessWrapper::newFromObject(
			new LoginNotify(
				$config,
				new HashBagOStuff
			)
		);
		$this->inst->setLogger( new NullLogger );
	}

	public function setUp(): void {
		parent::setUp();
		$this->setUpLoginNotify();
	}

	/**
	 * @dataProvider provideGetIPNetwork
	 */
	public function testGetIPNetwork( $ip, $expected ) {
		$actual = $this->inst->getIPNetwork( $ip );
		$this->assertSame( $expected, $actual );
	}

	public function provideGetIPNetwork() {
		return [
			[ '127.0.0.1', '127.0.0.' ],
			[ '118.221.191.18', '118.221.191.' ],
			[ '::1', '0:0:0:0:' ],
			[ '1:2:3:4:5:6:7:8', '1:2:3:4:' ],
			[ '1:ffe1::7:8', '1:FFE1:0:0:' ],
			[ 'd3::1:2:3:4:5:6', 'D3:0:1:2:' ]
		];
	}

	public function testGetIPNetworkInvalid() {
		$this->expectException( UnexpectedValueException::class );
		$this->inst->getIPNetwork( 'localhost' );
	}

	/**
	 * @dataProvider provideGenerateUserCookieRecord
	 */
	public function testGenerateUserCookieRecord( $username, $year, $salt, $expected ) {
		$actual = $this->inst->generateUserCookieRecord( $username, $year, $salt );
		$this->assertEquals( $expected, $actual );
	}

	public function provideGenerateUserCookieRecord() {
		return [
			[ 'Foo', 2011, 'a4321f', '2011-a4321f-8oerxg4l59zpiu0by7m2to1b4cjeer4' ],
			[ 'Foo', 2011, 'A4321f', '2011-A4321f-in65gc2i9czojfopkeieijc0ek8j5vu' ],
			[ 'Foo', 2015, 'a4321f', '2015-a4321f-2hf2zh9h3afv79b1u4l474ozc0by2xe' ],
			[ 'FOo', 2011, 'a4321f', '2011-a4321f-d0dhdzxg3te3yd3np6xdfrwdrckop7m' ],
		];
	}

	/**
	 * @dataProvider provideIsUserRecordGivenCookie
	 */
	public function testIsUserRecordGivenCookie( User $user, $cookieRecord, $expected, $desc ) {
		$actual = $this->inst->isUserRecordGivenCookie( $user, $cookieRecord );
		$this->assertEquals( $expected, $actual, "For {$user->getName()} on test $desc." );
	}

	public function provideIsUserRecordGivenCookie() {
		$this->setUpLoginNotify();
		$u = User::newFromName( 'Foo', false );
		$cookie1 = $this->inst->generateUserCookieRecord( 'Foo2' );
		$cookie2 = $this->inst->generateUserCookieRecord( 'Foo' );
		$cookie3 = $this->inst->generateUserCookieRecord( 'Foo', gmdate( 'Y' ) - 2 );
		$cookie4 = $this->inst->generateUserCookieRecord( 'Foo', gmdate( 'Y' ), 'RAND' );
		$cookie5 = $this->inst->generateUserCookieRecord( 'Foo', gmdate( 'Y' ) - 4 );
		return [
			[ $u, '2015-in65gc2i9czojfopkeieijc0ek8j5vu', false, "no salt" ],
			[ $u, '2011-A4321f-in65gc2i9czojfopkeieijc0ek8j5vu', false, "too old" ],
			[ $u, $cookie1, false, "name mismatch" ],
			[ $u, $cookie2, true, "normal" ],
			[ $u, $cookie3, true, "2 year old" ],
			[ $u, $cookie4, true, "Specific salt" ],
			[ $u, $cookie5, false, "4 year old" ],
		];
	}

	public function testGetPrevLoginCookie() {
		$req = new FauxRequest();
		$res1 = $this->inst->getPrevLoginCookie( $req );
		$this->assertSame( '', $res1, "no cookie set" );

		$req->setCookie( 'loginnotify_prevlogins', 'foo', '' );
		$res2 = $this->inst->getPrevLoginCookie( $req );
		$this->assertEquals( 'foo', $res2, "get dummy cookie" );
	}

	public function testGetKey() {
		$user1 = User::newFromName( 'Foo_bar' );
		// Make sure proper normalization happens.
		$user2 = User::newFromName( 'Foo__bar' );
		$user3 = User::newFromName( 'Somebody' );

		$this->assertEquals(
			'global:loginnotify:new:ok2qitd5efi25tzjy2l3el4n57g6l3l',
			$this->inst->getKey( $user1, 'new' )
		);
		$this->assertEquals(
			'global:loginnotify:known:ok2qitd5efi25tzjy2l3el4n57g6l3l',
			$this->inst->getKey( $user1, 'known' )
		);
		$this->assertEquals(
			'global:loginnotify:new:ok2qitd5efi25tzjy2l3el4n57g6l3l',
			$this->inst->getKey( $user2, 'new' )
		);
		$this->assertEquals(
			'global:loginnotify:new:tuwpi7e2h9pidovmaxxswk6aq327ewg',
			$this->inst->getKey( $user3, 'new' )
		);
	}

	public function testCheckAndIncKey() {
		$key = 'global:loginnotify:new:tuwpi7e2h9pidovmaxxswk6aq327ewg';
		for ( $i = 1; $i < 5; $i++ ) {
			$res = $this->inst->checkAndIncKey( $key, 5, 3600 );
			$this->assertFalse( $res, "key check numb $i" );
		}
		$this->assertEquals( 5, $this->inst->checkAndIncKey( $key, 5, 3600 ) );
		for ( $i = 1; $i < 5; $i++ ) {
			$res = $this->inst->checkAndIncKey( $key, 5, 3600 );
			$this->assertFalse( $res, "key check numb $i+5" );
		}
		$this->assertEquals( 10, $this->inst->checkAndIncKey( $key, 5, 3600 ) );

		$key2 = 'global:loginnotify:known:tuwpi7e2h9pidovmaxxswk6aq327ewg';
		for ( $i = 1; $i < 5; $i++ ) {
			$res = $this->inst->checkAndIncKey( $key2, 1, 3600 );
			$this->assertEquals( $i, $res, "key check interval 1 numb $i" );
		}
	}

	/**
	 * @dataProvider provideClearCounters
	 */
	public function testClearCounters( $key ) {
		$user = User::newFromName( "Fred" );
		$key = $this->inst->getKey( $user, $key );

		$this->inst->checkAndIncKey( $key, 1, 3600 );
		$res = $this->inst->checkAndIncKey( $key, 1, 3600 );
		$this->assertEquals( 2, $res, "prior to clear" );
		$this->inst->clearCounters( $user );
		$res = $this->inst->checkAndIncKey( $key, 1, 3600 );
		$this->assertSame( 1, $res, "after clear" );
	}

	public function provideClearCounters() {
		return [
			[ 'new' ],
			[ 'known' ],
		];
	}

	/**
	 * @note Expected new cookie does not include first record, as
	 * first record depends on random numbers.
	 * @dataProvider provideCheckAndGenerateCookie
	 */
	public function testCheckAndGenerateCookie(
		User $user,
		$cookie,
		$expectedSeenBefore,
		$expectedNewCookie,
		$desc
	) {
		list( $actualSeenBefore, $actualNewCookie ) =
			$this->inst->checkAndGenerateCookie( $user, $cookie );

		$this->assertEquals( $expectedSeenBefore, $actualSeenBefore,
			"[Seen before] $desc"
		);
		$newCookieParts = explode( '.', $actualNewCookie, 2 );
		if ( !isset( $newCookieParts[1] ) ) {
			$newCookieParts[1] = '';
		}
		$this->assertTrue(
			$this->inst->isUserRecordGivenCookie( $user, $newCookieParts[0] ),
			"[Cookie new entry] $desc"
		);
		$this->assertEquals( $expectedNewCookie, $newCookieParts[1], "[Cookie] $desc" );
	}

	public function provideCheckAndGenerateCookie() {
		$this->setUpLoginNotify();
		$y = gmdate( 'Y' );
		$oldYear = $y - 4;
		$u1 = User::newFromName( 'Foo' );

		$cookie1 = $this->inst->generateUserCookieRecord( 'Foo' );
		$cookie2 = $this->inst->generateUserCookieRecord( 'Foo' );
		$cookieOld = $this->inst->generateUserCookieRecord( 'Foo', 2001 );
		$cookieOtherUser = "$y-1veusdo-tr049njztrrvkkz4tk3kre8rm1zb134";
		return [
			[ $u1, '', false, '', "no cookie" ],
			[
				$u1,
				"$cookieOtherUser",
				false,
				"$cookieOtherUser",
				"not in cookie"
			],
			[
				$u1,
				"$cookieOtherUser.$y-.$y-abcdefg-8oerxg4l59zpiu0by7m2to1b4cjeer4.$oldYear-" .
					"1234567-tpnsk00419wba6vjh1upif21qtst1cv",
				false,
				"$cookieOtherUser.$y-abcdefg-8oerxg4l59zpiu0by7m2to1b4cjeer4",
				"old values in cookie"
			],
			[
				$u1,
				$cookieOld,
				false,
				"",
				"Only old value"
			],
			[
				$u1,
				$cookie1,
				true,
				"",
				"Normal success"
			],
			[
				$u1,
				"$cookieOtherUser.$cookie1.$cookie2."
					. "$y-1234567-tpnsk00419wba6vjh1upif21qtst1cv.$cookie1",
				true,
				"$cookieOtherUser.$y-1234567-tpnsk00419wba6vjh1upif21qtst1cv",
				"Remove all of current user."
			],
			[
				$u1,
				"$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser",
				false,
				"$cookieOtherUser.$cookieOtherUser.$cookieOtherUser."
					. "$cookieOtherUser.$cookieOtherUser.$cookieOtherUser",
				"Limit max number of records."
			]
		];
	}

	/**
	 * @dataProvider provideValidateCookieRecord
	 */
	public function testValidateCookieRecord( $cookie, $expected ) {
		$this->assertEquals( $expected, $this->inst->validateCookieRecord( $cookie ) );
	}

	public function provideValidateCookieRecord() {
		$y = gmdate( 'Y' );
		return [
			[ 'fdakslnknfaknasf', false ],
			[ '--', false ],
			[ '91-ffff-fdaskfjlasflasd', false ],
			[ '2011-ffff-fdaskfjlasflasd', false ],
			[ "$y-1veusdo-tr049njztrrvkkz4tk3kre8rm1zb134", true ],
			[ "1991-1veusdo-tr049njztrrvkkz4tk3kre8rm1zb134", false ],
		];
	}

	public function testUserIsInCache() {
		$u = User::newFromName( 'Xyzzy' );
		$this->assertSame(
			LoginNotify::USER_NO_INFO,
			$this->inst->userIsInCache( $u, new FauxRequest() )
		);

		$this->inst->cacheLoginIP( $u );
		$this->assertSame(
			LoginNotify::USER_KNOWN,
			$this->inst->userIsInCache( $u, new FauxRequest() )
		);

		$request = new FauxRequest();
		$request->setIP( '10.1.2.3' );

		$this->assertSame(
			LoginNotify::USER_NOT_KNOWN,
			$this->inst->userIsInCache( $u, $request )
		);
	}
}
