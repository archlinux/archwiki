<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWikiIntegrationTestCase;
use SodiumException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys
 * @covers \MediaWiki\Extension\OATHAuth\Key\TOTPKey
 */
class TOTPKeyTest extends MediaWikiIntegrationTestCase {
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

	public function testDeserialization(): void {
		$key = TOTPKey::newFromRandom();
		$deserialized = TOTPKey::newFromArray( json_decode( json_encode( $key ), true ) );
		$this->assertSame( $key->getSecret(), $deserialized->getSecret() );
	}

	public function testNewFromRandomNoBase32Padding(): void {
		$base32PaddingElement = '=';
		$key = TOTPKey::newFromRandom();
		$this->assertNotEquals( $base32PaddingElement, substr( $key->getSecret(), -1 ) );
	}

	public function testNewFromArrayWithNonceNoEncryption(): void {
		// bad nonce value will throw a sodium exception
		$this->encryptionTestSetup();

		$this->expectException( SodiumException::class );
		TOTPKey::newFromArray( [
			'secret' => '123456',
			'nonce' => '789101112',
		] );
	}

	public function testNewFromArrayWithEncryption(): void {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();

		$key = TOTPKey::newFromArray( [
			'secret' => $data['secret'],
			'nonce' => $data['nonce'],
		] );

		$this->assertEquals(
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getEncryptionHelper()
				->decrypt( $data['secret'], $data['nonce'] ),
			$key->getSecret(),
		);
	}

	public function testNewFromArrayWithoutSecret(): void {
		// We aren't setting secret, so this will return null
		$this->assertSame( null, TOTPKey::newFromArray( [] ) );
	}

	public function testJsonSerializerWithEncryption(): void {
		$this->encryptionTestSetup();
		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();
		$this->assertArrayHasKey( 'nonce', $data );
		$this->assertArrayHasKey( 'secret', $data );
		$this->assertNotEquals( $data['secret'], $key->getSecret() );
	}

	public function testDoNotReencryptEncryptedKeyData(): void {
		$this->encryptionTestSetup();

		$key = TOTPKey::newFromRandom();
		$data = $key->jsonSerialize();
		$encryptedData = $key->getEncryptedSecretAndNonce();
		$oldEncryptedSecret = $encryptedData[0];
		$oldNonce = $encryptedData[1];

		$newData = $key->jsonSerialize();
		$this->assertEquals( $oldEncryptedSecret, $newData['secret'] );
		$this->assertEquals( $oldNonce, $newData['nonce'] );
	}

	public function testGetSetFunctions(): void {
		$totpKeyLength = 42;
		$key = TOTPKey::newFromRandom();
		$this->assertNull( $key->getId() );
		$this->assertIsString( $key->getSecret() );
		$this->assertEquals( $totpKeyLength, strlen( $key->getSecret() ) );

		$this->assertSame( TOTP::MODULE_NAME, $key->getModule() );
	}

	public function testVerify(): void {
		$mockOATHUser = $this->createMock( OATHUser::class );

		$testData1 = [];
		$key = TOTPKey::newFromRandom();
		$this->assertSame( false, $key->verify( $testData1, $mockOATHUser ) );
	}
}
