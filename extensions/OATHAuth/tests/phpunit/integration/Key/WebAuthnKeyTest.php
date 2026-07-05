<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Key;

use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\WebAuthnKey
 * @covers \MediaWiki\Extension\OATHAuth\Key\AuthKey
 */
class WebAuthnKeyTest extends MediaWikiIntegrationTestCase {

	private const KEY_DATA = [
		'userHandle' => 'fakeHandle',
		'friendlyName' => 'testKey',
		'counter' => 3,
		'transports' => [],
		'aaguid' => 'f7dcf1ec-76ad-49cb-ae2a-6d8ed6736f88',
		'publicKeyCredentialId' => 'none',
		'credentialPublicKey' => 'none',
	];

	public function testPasswordlessLoginPasskeysSupportsPasswordless(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);
		$key->setPasswordlessSupport( true );
		$this->assertTrue( $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysDoesntSupportPasswordless(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA + [ 'supportsPasswordless' => false ]
		);
		$this->assertFalse( $key->supportsPasswordlessLogin() );
	}

	public function testPasswordlessLoginPasskeysSupportPasswordlessMissing(): void {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);
		$this->assertFalse( $key->supportsPasswordlessLogin() );
	}

	public function testJsonSerialize() {
		$key = WebAuthnKey::newFromData(
			self::KEY_DATA
		);

		$this->assertEquals(
			$key,
			WebAuthnKey::newFromData(
				$key->jsonSerialize()
			)
		);
	}
}
