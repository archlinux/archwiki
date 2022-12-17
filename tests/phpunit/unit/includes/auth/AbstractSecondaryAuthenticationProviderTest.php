<?php

namespace MediaWiki\Tests\Unit\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;

/**
 * @group AuthManager
 * @covers \MediaWiki\Auth\AbstractSecondaryAuthenticationProvider
 */
class AbstractSecondaryAuthenticationProviderTest extends \MediaWikiUnitTestCase {
	public function testAbstractSecondaryAuthenticationProvider() {
		$user = $this->createMock( \User::class );

		$provider = $this->getMockForAbstractClass( AbstractSecondaryAuthenticationProvider::class );

		try {
			$provider->continueSecondaryAuthentication( $user, [] );
			$this->fail( 'Expected exception not thrown' );
		} catch ( \BadMethodCallException $ex ) {
		}

		try {
			$provider->continueSecondaryAccountCreation( $user, $user, [] );
			$this->fail( 'Expected exception not thrown' );
		} catch ( \BadMethodCallException $ex ) {
		}

		$req = $this->getMockForAbstractClass( AuthenticationRequest::class );

		$this->assertTrue( $provider->providerAllowsPropertyChange( 'foo' ) );
		$this->assertEquals(
			\StatusValue::newGood( 'ignored' ),
			$provider->providerAllowsAuthenticationDataChange( $req )
		);
		$this->assertEquals(
			\StatusValue::newGood(),
			$provider->testForAccountCreation( $user, $user, [] )
		);
		$this->assertEquals(
			\StatusValue::newGood(),
			$provider->testUserForCreation( $user, AuthManager::AUTOCREATE_SOURCE_SESSION )
		);
		$this->assertEquals(
			\StatusValue::newGood(),
			$provider->testUserForCreation( $user, false )
		);

		$provider->providerChangeAuthenticationData( $req );
		$provider->autoCreatedAccount( $user, AuthManager::AUTOCREATE_SOURCE_SESSION );

		$res = AuthenticationResponse::newPass();
		$provider->postAuthentication( $user, $res );
		$provider->postAccountCreation( $user, $user, $res );
	}

	public function testProviderRevokeAccessForUser() {
		$reqs = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$reqs[$i] = $this->createMock( AuthenticationRequest::class );
			$reqs[$i]->done = false;
		}

		$provider = $this->getMockBuilder( AbstractSecondaryAuthenticationProvider::class )
			->onlyMethods( [ 'providerChangeAuthenticationData' ] )
			->getMockForAbstractClass();
		$provider->expects( $this->once() )->method( 'getAuthenticationRequests' )
			->with(
				$this->identicalTo( AuthManager::ACTION_REMOVE ),
				$this->identicalTo( [ 'username' => 'UTSysop' ] )
			)
			->willReturn( $reqs );
		$provider->expects( $this->exactly( 3 ) )->method( 'providerChangeAuthenticationData' )
			->willReturnCallback( function ( $req ) {
				$this->assertSame( 'UTSysop', $req->username );
				$this->assertFalse( $req->done );
				$req->done = true;
			} );

		$provider->providerRevokeAccessForUser( 'UTSysop' );

		foreach ( $reqs as $i => $req ) {
			$this->assertTrue( $req->done, "#$i" );
		}
	}
}
