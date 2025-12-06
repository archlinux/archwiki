<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use SodiumException;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys
 * @covers \MediaWiki\Extension\OATHAuth\Key\EncryptionHelper
 * @covers \MediaWiki\Extension\OATHAuth\Module\TOTP
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 * @group Database
 */
class RecoveryCodeKeysTest extends MediaWikiIntegrationTestCase {
	public function encryptionTestSetup() {
		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
		$this->setMwGlobals( 'wgOATHSecretKey', 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a' );
		$this->getServiceContainer()->resetServiceForTesting( 'OATHAuth.EncryptionHelper' );
		$this->assertTrue(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->isEnabled(),
		);
	}

	public function testDeserializationUnencrypted() {
		$this->assertNull( RecoveryCodeKeys::newFromArray( [] ) );

		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 1 );
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$key->regenerateRecoveryCodeKeys();
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$key->regenerateRecoveryCodeKeys();
		$deserialized = RecoveryCodeKeys::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getRecoveryCodeKeys(), $deserialized->getRecoveryCodeKeys() );
	}

	public function testNewFromArrayWithNonce() {
		$this->setMwGlobals( 'wgOATHSecretKey', false );
		$this->expectException( UnexpectedValueException::class );
		$keyArray = [
			'recoverycodekeys' => [ '88asdyf09sadf' ],
			'nonce' => 'bad_value',
		];
		$key = RecoveryCodeKeys::newFromArray( $keyArray );

		$this->encryptionTestSetup();

		$this->expectException( SodiumException::class );
		$key = RecoveryCodeKeys::newFromArray( $keyArray );

		$key = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ '88as3hh433jj2o22' ],
			'nonce' => '7LRMXBX2AKPYWDBUBDHCN2WCFJXFX4XR2GZRV7Q=',
		] );
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );
	}

	public function testNewFromArrayWithEncryption() {
		$this->encryptionTestSetup();

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();

		$keysPostSerialization = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => $data['recoverycodekeys'],
			'nonce' => $data['nonce'],
		] );

		$this->assertEquals(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->decryptStringArrayValues( $data['recoverycodekeys'], $data['nonce'] ),
			$keysPostSerialization->getRecoveryCodeKeys(),
		);
	}

	public function testJsonSerializerWithEncryption() {
		$this->encryptionTestSetup();
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'recoverycodekeys', $data );
		$config = OATHAuthServices::getInstance( $this->getServiceContainer() )->getConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $data['recoverycodekeys'] );
		$this->assertNotEquals( $data['recoverycodekeys'], $keys->getRecoveryCodeKeys() );
	}

	public function testDoNotReencryptEncryptedKeyData() {
		$this->encryptionTestSetup();

		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$data = $keys->jsonSerialize();
		$encryptedData = $keys->getRecoveryCodeKeysEncryptedAndNonce();
		$oldEncryptedRecoveryCodes = $encryptedData[0];
		$oldNonce = $encryptedData[1];

		$newData = $keys->jsonSerialize();
		$this->assertEquals( $oldEncryptedRecoveryCodes, $newData['recoverycodekeys'] );
		$this->assertEquals( $oldNonce, $newData['nonce'] );
	}

	public function testGetSetFunctions(): void {
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$this->assertNull( $keys->getId() );
		$this->assertSame( [], $keys->getRecoveryCodeKeys() );

		$currentRecCodeKeys = $keys->getRecoveryCodeKeys();
		$keys->regenerateRecoveryCodeKeys();
		$this->assertNotSame( $currentRecCodeKeys, $keys->getRecoveryCodeKeys() );

		$this->assertSame( RecoveryCodes::MODULE_NAME, $keys->getModule() );
	}

	public function testVerify(): void {
		$mockUserIdentity = $this->createMock( UserIdentity::class );
		$mockWebRequest = $this->createMock( WebRequest::class, [ 'getSecurityLogContext' ] );
		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'GetSecurityLogContext',
			static function ( array $info, array &$context ) use ( $mockWebRequest, $mockUserIdentity ) {
				$context['foo'] = 'bar';
			}
		);
		$mockWebRequest->method( 'getSecurityLogContext' )
			->willReturn( [ 'clientIp' => '1.1.1.1' ] );

		$testData = [];
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$this->assertSame( false, $keys->verify( $testData, $mockOATHUser ) );

		$keys->regenerateRecoveryCodeKeys();

		$testData = [ 'recoverycode' => 'bad_token' ];
		$this->assertSame( false, $keys->verify( $testData, $mockOATHUser ) );

		$config = OATHAuthServices::getInstance( $this->getServiceContainer() )->getConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $keys->getRecoveryCodeKeys() );

		// Test that verify works with a generated key
		$testData = [ 'recoverycode' => $keys->getRecoveryCodeKeys()[0] ];
		$this->assertSame( true, $keys->verify( $testData, $mockOATHUser ) );

		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ) - 1, $keys->getRecoveryCodeKeys() );

		// Test that you can't verify twice (in a row) with the same recovery code
		$this->assertSame( false, $keys->verify( $testData, $mockOATHUser ) );
	}

	public function testIsValidRecoveryCode(): void {
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [ '64SZLJTTPRI5XBUE' ] ] );
		$this->assertTrue( $key->isValidRecoveryCode( '64SZLJTTPRI5XBUE' ) );
		// Whitespace is stripped
		$this->assertTrue( $key->isValidRecoveryCode( ' 64SZLJTTPRI5XBUE ' ) );
		// Wrong token
		$this->assertFalse( $key->isValidRecoveryCode( 'WIQGC24UJUFXQDW4' ) );
	}
}
