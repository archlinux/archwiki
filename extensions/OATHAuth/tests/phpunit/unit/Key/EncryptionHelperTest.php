<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Key;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWikiUnitTestCase;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\EncryptionHelper
 */
class EncryptionHelperTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !extension_loaded( 'sodium' ) ) {
			$this->markTestSkipped( 'sodium extension not installed, skipping' );
		}
	}

	private function getHelper() {
		return new EncryptionHelper(
			new ServiceOptions(
				EncryptionHelper::CONSTRUCTOR_OPTIONS,
				// Generated once using `MWCryptRand::generateHex( 64 );`
				[ 'OATHSecretKey' => 'f901c7d7ecc25c90229c01cec0efec1b521a5e2eb6761d29007dde9566c4536a' ],
			),
		);
	}

	public function testEncryptionHelper() {
		$helper = $this->getHelper();
		$this->assertTrue( $helper->isEnabled() );
		$magicPhrase = 'super secret phrase';
		$encrypted = $helper->encrypt( $magicPhrase );
		$decrypted = $helper->decrypt( $encrypted['secret'], $encrypted['nonce'] );
		$this->assertSame( $magicPhrase, $decrypted );
	}

	public function testInvalidEncryptionAttempt() {
		$helper = $this->getHelper();
		$this->assertTrue( $helper->isEnabled() );
		$magicPhrase = 'super secret phrase';
		$invalidMagicPhrase = 'a different phrase that isn\'t encrypted';
		$encrypted = $helper->encrypt( $magicPhrase );
		$this->expectException( UnexpectedValueException::class );
		$decrypted = $helper->decrypt( $invalidMagicPhrase, $encrypted['nonce'] );
	}

	/**
	 * @dataProvider provideInvalidKeys
	 */
	public function testIsEnabledInvalidKey( string $key ) {
		$helper = new EncryptionHelper(
			new ServiceOptions(
				EncryptionHelper::CONSTRUCTOR_OPTIONS,
				[ 'OATHSecretKey' => $key ],
			),
		);

		$this->expectException( UnexpectedValueException::class );
		$this->assertNull( $helper->isEnabled() );
	}

	public static function provideInvalidKeys() {
		yield 'invalid length' => [ 'aaaaaaaaaaaaa' ];
		yield 'not hexadecimal' => [ 'tttttttttttttttttttttttttttttttttttttttttttttttttttttttttttttttt' ];
	}

	public function testIsEnabledDisabled() {
		$helper = new EncryptionHelper(
			new ServiceOptions(
				EncryptionHelper::CONSTRUCTOR_OPTIONS,
				[ 'OATHSecretKey' => null ],
			),
		);

		$this->assertFalse( $helper->isEnabled() );
	}
}
