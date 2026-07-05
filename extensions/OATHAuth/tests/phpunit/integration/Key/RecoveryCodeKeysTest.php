<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCode;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\Tests\Integration\EncryptionTestTrait;
use MediaWiki\Request\WebRequest;
use MediaWikiIntegrationTestCase;
use OutOfRangeException;
use SodiumException;
use UnexpectedValueException;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\AuthKey
 * @covers \MediaWiki\Extension\OATHAuth\Key\EncryptionHelper
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCode
 * @covers \MediaWiki\Extension\OATHAuth\Module\TOTP
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @group Database
 */
class RecoveryCodeKeysTest extends MediaWikiIntegrationTestCase {
	use EncryptionTestTrait;

	private const NONCE = 'ZQLYMZGFRFXA62IPRSX6ZQGZERFIM6M6ZQ4PI2I=';
	// The two below correspond to each other with the above nonce
	private const VALID_ENCRYPTED_RECOVERY_KEY = 'YD576FTL362W5AJL6GYNI55SRZFBWV72NWAF3IZV2NSMXX2X5T2A====';
	private const VALID_RECOVERY_KEY = 'IETUSRVABHG54F33';
	private const INVALID_ENCRYPTED_RECOVERY_KEY = '88asdyf09sadf';

	public function setUp(): void {
		$this->setMwGlobals( 'wgOATHSecretKey', false );
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

	public function testNewFromArrayWithNonce_encryptionDisabled() {
		$this->setMwGlobals( 'wgOATHSecretKey', false );
		$this->expectException( UnexpectedValueException::class );
		RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ self::INVALID_ENCRYPTED_RECOVERY_KEY ],
			'nonce' => 'bad_value',
		] );
	}

	public function testNewFromArrayWithNonce_invalidKey() {
		$this->encryptionIntegrationTestSetup();
		$this->expectException( SodiumException::class );
		RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ self::INVALID_ENCRYPTED_RECOVERY_KEY ],
			'nonce' => 'bad_value',
		] );
	}

	public function testNewFromArrayWithNonce_validKey() {
		$this->encryptionIntegrationTestSetup();
		$key = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ self::VALID_ENCRYPTED_RECOVERY_KEY ],
			'nonce' => self::NONCE,
		] );
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );

		$recoveryCodes = $key->getRecoveryCodeKeys();
		$this->assertCount( 1, $recoveryCodes );
		$this->assertSame( self::VALID_RECOVERY_KEY, $recoveryCodes[0] );
	}

	public function testNewFromArrayWithEncryption() {
		$this->encryptionIntegrationTestSetup();

		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();

		$keysPostSerialization = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => $data['recoverycodekeys'],
			'nonce' => $data['nonce'],
		] );

		$encryptionHelper = OATHAuthServices::getInstance( $this->getServiceContainer() )->getEncryptionHelper();
		$decryptedKeys = [];
		foreach ( $data['recoverycodekeys'] as $recoveryCodeKeys ) {
			$decryptedKeys[] = $encryptionHelper->decrypt( $recoveryCodeKeys, $data['nonce'] );
		}

		$this->assertEquals( $decryptedKeys, $keysPostSerialization->getRecoveryCodeKeys() );
	}

	public function testJsonSerializerWithEncryption() {
		$this->encryptionIntegrationTestSetup();
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$keys->regenerateRecoveryCodeKeys();
		$data = $keys->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'recoverycodekeys', $data );
		$config = $this->getServiceContainer()->getMainConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $data['recoverycodekeys'] );
		$this->assertNotEquals( $data['recoverycodekeys'], $keys->getRecoveryCodeKeys() );
	}

	public function testEncryptsKeyWithData() {
		$this->encryptionIntegrationTestSetup();
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [
			[ 'TESTCODE', [ 'foo' => 'bar' ] ],
		] ] );
		$data = $keys->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'recoverycodekeys', $data );
		$this->assertSame( [ 'TESTCODE' ], $keys->getRecoveryCodeKeys() );
	}

	public function testDoNotReencryptEncryptedKeyData() {
		$this->encryptionIntegrationTestSetup();

		$keys = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ self::VALID_ENCRYPTED_RECOVERY_KEY ],
			'nonce' => self::NONCE,
		] );
		$serializedData = $keys->jsonSerialize();
		$this->assertEquals( [ self::VALID_ENCRYPTED_RECOVERY_KEY ], $serializedData['recoverycodekeys'] );
		$this->assertEquals( self::NONCE, $serializedData['nonce'] );
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
		$mockWebRequest = $this->createMock( WebRequest::class );
		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getCentralId' )
			->willReturn( 12345 );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'GetSecurityLogContext',
			static function ( array $info, array &$context ) {
				$context['foo'] = 'bar';
			}
		);
		$mockWebRequest->method( 'getSecurityLogContext' )
			->willReturn( [ 'clientIp' => '1.1.1.1' ] );

		$testData = [];
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$this->assertFalse( $keys->verify( $mockOATHUser, $testData ) );

		$keys->regenerateRecoveryCodeKeys();

		$testData = [ 'recoverycode' => 'bad_token' ];
		$this->assertFalse( $keys->verify( $mockOATHUser, $testData ) );

		$config = $this->getServiceContainer()->getMainConfig();
		$this->assertCount( $config->get( 'OATHRecoveryCodesCount' ), $keys->getRecoveryCodeKeys() );

		// Test that verify works with a generated key
		$testData = [ 'recoverycode' => $keys->getRecoveryCodeKeys()[0] ];
		$this->assertTrue( $keys->verify( $mockOATHUser, $testData ) );
	}

	/** @dataProvider provideRemoveCode */
	public function testRemoveCode( int $originalCodeCount, int $expectedCodeCount ): void {
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );
		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' )
		];
		while ( count( $codes ) < $originalCodeCount ) {
			$codes[] = RecoveryCode::newRandom();
		}

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$this->assertArrayContains( [ 'TESTCODE' ], $keys->getRecoveryCodeKeys() );

		$keys->removeRecoveryCode( $mockOATHUser, 'TESTCODE' );
		$this->assertNotContains( 'TESTCODE', $keys->getRecoveryCodeKeys() );
		$this->assertCount( $expectedCodeCount, $keys->getRecoveryCodeKeys() );
	}

	public static function provideRemoveCode(): iterable {
		yield 'There are also other keys' => [ 5, 4 ];
		yield 'The removed key is the last one' => [ 1, 10 ];
	}

	public function testIsValidRecoveryCode(): void {
		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [ '64SZLJTTPRI5XBUE' ] ] );
		$this->assertTrue( $key->isValidRecoveryCode( '64SZLJTTPRI5XBUE' ) );
		// Whitespace is stripped
		$this->assertTrue( $key->isValidRecoveryCode( ' 64SZLJTTPRI5XBUE ' ) );
		// Wrong token
		$this->assertFalse( $key->isValidRecoveryCode( 'WIQGC24UJUFXQDW4' ) );
	}

	public function testGenerateAdditionalCodes(): void {
		$keys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [ 'BL5KE9W38GYGEB9T' ] ] );
		$this->assertSame( [ 'BL5KE9W38GYGEB9T' ], $keys->getRecoveryCodeKeys() );

		$newCodes = $keys->generateAdditionalRecoveryCodeKeys( 1 );
		$this->assertCount( 1, $newCodes );
		$this->assertCount( 2, $keys->getRecoveryCodeKeys() );

		$existingKeys = $keys->getRecoveryCodeKeys();
		$this->assertSame( 'BL5KE9W38GYGEB9T', $existingKeys[0] );
	}

	public function testGenerateAdditionalCodesWithEncryption(): void {
		$this->encryptionIntegrationTestSetup();

		$keysObject = RecoveryCodeKeys::newFromArray( [
			'recoverycodekeys' => [ self::VALID_ENCRYPTED_RECOVERY_KEY ],
			'nonce' => self::NONCE,
		] );
		$existingKeysInitial = $keysObject->getRecoveryCodeKeys();
		$this->assertCount( 1, $existingKeysInitial );

		$keysObject->generateAdditionalRecoveryCodeKeys( 1 );
		$existingKeysPreSerialization = $keysObject->getRecoveryCodeKeys();
		$this->assertCount( 2, $existingKeysPreSerialization );
		$this->assertSame( $existingKeysInitial[0], $existingKeysPreSerialization[0] );

		$data = $keysObject->jsonSerialize();
		$keysObjectPostSerialization = RecoveryCodeKeys::newFromArray( $data );
		$existingKeysPostSerialization = $keysObjectPostSerialization->getRecoveryCodeKeys();
		$this->assertCount( 2, $existingKeysPostSerialization );
		$this->assertSame( $existingKeysInitial[0], $existingKeysPostSerialization[0] );
	}

	/** @dataProvider provideWithEncryption */
	public function testDataIsPreservedWhenSerializing( bool $useEncryption ): void {
		if ( $useEncryption ) {
			$this->encryptionIntegrationTestSetup();
			$originalData = [
				'recoverycodekeys' => [ [ self::VALID_ENCRYPTED_RECOVERY_KEY, [ 'foo' => 'bar' ] ] ],
				'nonce' => self::NONCE,
			];
		} else {
			$originalData = [
				'recoverycodekeys' => [ [ 'KEY', [ 'foo' => 'bar' ] ] ]
			];
		}

		$keysObject = RecoveryCodeKeys::newFromArray( $originalData );
		$serializedData = $keysObject->jsonSerialize();

		$this->assertSame( $originalData, $serializedData );
	}

	public static function provideWithEncryption(): iterable {
		yield 'No encryption' => [ false ];
		yield 'With encryption' => [ true ];
	}

	public function testSkipsExpiredKeysWhenInitializing() {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );

		$keyData = [
			'recoverycodekeys' => [
				[ 'VALID_KEY', [ 'expiry' => '20270101000000' ] ],
				[ 'EXPIRED_KEY', [ 'expiry' => '20250101000000' ] ],
			]
		];
		$keys = RecoveryCodeKeys::newFromArray( $keyData );
		$this->assertSame( [ 'VALID_KEY' ], $keys->getRecoveryCodeKeys() );
		$this->assertSame( '20270101000000', $keys->getRecoveryCodes()[0]->getExpiryTimestamp() );
	}

	public function testRegeneratesCodesWhenLastOneIsUsed() {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 10 );

		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
			RecoveryCode::newFromPlaintext( 'EXPIRINGCODE', [ 'expiry' => '20270101000000' ] ),
		];

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$this->assertArrayContains( [ 'TESTCODE', 'EXPIRINGCODE' ], $keys->getRecoveryCodeKeys() );

		$keys->removeRecoveryCode( $mockOATHUser, 'TESTCODE' );
		$this->assertNotContains( 'TESTCODE', $keys->getRecoveryCodeKeys() );
		$this->assertCount( 11, $keys->getRecoveryCodeKeys() );
	}

	public function testDropsTemporaryWhenRegenerating_notEnoughSlots() {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 2 );
		$this->setMwGlobals( 'wgOATHMaxRecoveryCodesCount', 3 );

		$mockOATHUser = $this->createMock( OATHUser::class );
		$mockOATHUser->method( 'getUser' )
			->willReturn( $this->getTestUser()->getUser() );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
			RecoveryCode::newFromPlaintext( 'EXPIRING1', [ 'expiry' => '20270101000000' ] ),
			RecoveryCode::newFromPlaintext( 'EXPIRING2', [ 'expiry' => '20270101000000' ] ),
		];

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$this->assertArrayContains( [ 'TESTCODE', 'EXPIRING1', 'EXPIRING2' ], $keys->getRecoveryCodeKeys() );

		$keys->removeRecoveryCode( $mockOATHUser, 'TESTCODE' );
		$this->assertNotContains( 'TESTCODE', $keys->getRecoveryCodeKeys() );
		$this->assertNotContains( 'EXPIRING1', $keys->getRecoveryCodeKeys() );
		$this->assertCount( 3, $keys->getRecoveryCodeKeys() );
	}

	public function testCapsNumberOfCodesAtMaximum() {
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 100 );
		$this->setMwGlobals( 'wgOATHMaxRecoveryCodesCount', 2 );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
		];

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$keys->regenerateRecoveryCodeKeys();

		$this->assertNotContains( 'TESTCODE', $keys->getRecoveryCodeKeys() );
		$this->assertCount( 2, $keys->getRecoveryCodeKeys() );
	}

	public function testGenerateAdditionalBeyondMaximum() {
		$this->setMwGlobals( 'wgOATHMaxRecoveryCodesCount', 2 );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
		];

		$this->expectException( OutOfRangeException::class );
		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$keys->generateAdditionalRecoveryCodeKeys( 2 );
	}

	public function testGenerateAdditionalBeyondMaximum_noThrow() {
		$this->setMwGlobals( 'wgOATHMaxRecoveryCodesCount', 2 );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
		];

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$newCodes = $keys->generateAdditionalRecoveryCodeKeys( 2, [], true );

		$this->assertCount( 1, $newCodes );
		$this->assertCount( 2, $keys->getRecoveryCodeKeys() );
		$this->assertContains( 'TESTCODE', $keys->getRecoveryCodeKeys() );
	}

	public function testRemoveTemporaryCodes() {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );

		$codes = [
			RecoveryCode::newFromPlaintext( 'TESTCODE' ),
			RecoveryCode::newFromPlaintext( 'EXPIRINGCODE', [ 'expiry' => '20270101000000' ] ),
		];

		$keys = new RecoveryCodeKeys( null, null, null, $codes );
		$this->assertArrayContains( [ 'TESTCODE', 'EXPIRINGCODE' ], $keys->getRecoveryCodeKeys() );

		$keys->removeTemporaryCodes();
		$this->assertNotContains( 'EXPIRINGCODE', $keys->getRecoveryCodeKeys() );
		$this->assertCount( 1, $keys->getRecoveryCodeKeys() );
	}
}
