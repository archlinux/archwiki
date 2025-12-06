<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait
 */
class KeySessionStorageTraitTest extends MediaWikiIntegrationTestCase {
	use KeySessionStorageTrait;

	private Session $session;
	private WebRequest $request;

	public function setUp(): void {
		// do not test with encryption
		$this->setMwGlobals( 'wgOATHSecretKey', false );
		$this->session = $this->createMock( Session::class, [ 'set' ] );
		$this->request = $this->createMock( WebRequest::class, [ 'getSession' ] );
		$this->request->method( 'getSession' )->willReturn( $this->session );
	}

	// mock function for trait
	public function getRequest(): WebRequest {
		return $this->request;
	}

	public function getSession(): Session {
		return $this->session;
	}

	public function provideSessionKeyNameAndDataData(): array {
		return [
			[ 'TOTPKey', [ '' ], false, IAuthKey::class ],
			[ 'RecoveryCodeKeys', [ '' ], true, null ],
			[ 'TOTPKey', [ 'secret' => 'ABCDEFGH==' ], true, IAuthKey::class ],
		];
	}

	/**
	 * @dataProvider provideSessionKeyNameAndDataData
	 */
	public function testSetGetKeyDataInSession( $keyType, $keyData, $assertEquals, $interfaceType ): void {
		// test creation and setting of new IAuthKeys in session
		// TODO: $authKey1 assignment should be done dynamically, if PHP will allow...
		$authKey1 = ( $keyType === 'TOTPKey' ) ?
			TOTPKey::newFromArray( $keyData )
			: RecoveryCodeKeys::newFromArray( $keyData );
		$authKey2 = $this->setKeyDataInSession( $keyType, $keyData );
		if ( count( $keyData ) > 0 && $interfaceType ) {
			$this->assertInstanceOf( $interfaceType, $authKey2 );
		}
		$this->getSession()->expects( $this->once() )
			->method( 'getSecret' )
			->with( $this->getSessionKeyName( $keyType ) )
			->willReturn( $authKey2 );
		if ( $assertEquals ) {
			$this->assertEquals( $authKey1, $this->getKeyDataInSession( $keyType ) );
		} else {
			$this->assertNotEquals( $authKey1, $this->getKeyDataInSession( $keyType ) );
		}

		// assert setting keys in session to null
		$this->getSession()->expects( $this->once() )
			->method( 'setSecret' )
			->with( $this->getSessionKeyName( $keyType ) )
			->willReturn( null );
		$this->setKeyDataInSessionToNull( $keyType );
	}

	public function provideSessionKeyNameData(): array {
		return [
			[ 'TOTPKey_oathauth_key', 'TOTPKey' ],
			[ 'RecoveryCodeKeys_oathauth_key', 'RecoveryCodeKeys' ]
		];
	}

	/**
	 * @dataProvider provideSessionKeyNameData
	 */
	public function testGetSessionKeyName( $str1, $str2 ): void {
		$this->assertSame( $str1, $this->getSessionKeyName( $str2 ) );
	}
}
