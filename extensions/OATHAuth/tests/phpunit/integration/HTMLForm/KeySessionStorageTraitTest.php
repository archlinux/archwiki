<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\HTMLForm;

use MediaWiki\Extension\OATHAuth\HTMLForm\KeySessionStorageTrait;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
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
		$this->session = $this->createMock( Session::class );
		$this->request = $this->createMock( WebRequest::class );
		$this->request->method( 'getSession' )->willReturn( $this->session );
	}

	public function getRequest(): WebRequest {
		return $this->request;
	}

	public function getSession(): Session {
		return $this->session;
	}

	public static function provideSessionKeyNameAndData(): array {
		$emptyVal = [ '' ];
		$filledVal = [ 'secret' => 'ABCDEFGH==' ];
		return [
			[
				'TOTPKey',
				TOTPKey::newFromArray( $emptyVal ),
				$emptyVal,
				false,
				AuthKey::class,
			],
			[
				'RecoveryCodeKeys',
				RecoveryCodeKeys::newFromArray( $emptyVal ),
				$emptyVal,
				true,
				null,
			],
			[
				'TOTPKey',
				TOTPKey::newFromArray( $filledVal ),
				$filledVal,
				true,
				AuthKey::class,
			],
		];
	}

	/**
	 * @dataProvider provideSessionKeyNameAndData
	 */
	public function testSetGetKeyDataInSession( $keyType, $authKey1, $keyData, $assertEquals, $interfaceType ): void {
		// test creation and setting of new AuthKeys in session
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

	public static function provideSessionKeyNameData(): array {
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
