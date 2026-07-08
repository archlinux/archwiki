<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Unit\Key;

use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCode;
use MediaWikiUnitTestCase;
use RuntimeException;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Key\RecoveryCode
 */
class RecoveryCodeTest extends MediaWikiUnitTestCase {

	public function testAvoidsReencryption() {
		$encryptionHelper = $this->createStub( EncryptionHelper::class );
		$code = new RecoveryCode( $encryptionHelper, 'CODE', [ 'foo' => 'bar' ], 'ENCRYPTED', 'NONCE' );

		$this->assertSame( 'CODE', $code->getCode() );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
		$this->assertSame( 'NONCE', $code->getNonce() );
		$this->assertSame( [ 'foo' => 'bar' ], $code->getData() );
		$this->assertNull( $code->getExpiryTimestamp() );
		$this->assertTrue( $code->isPermanent() );

		$this->assertTrue( $code->test( 'CODE' ) );
	}

	public function testEncryption() {
		$encryptionHelper = $this->createMock( EncryptionHelper::class );
		$encryptionHelper->method( 'isEnabled' )
			->willReturn( true );
		$encryptionHelper->method( 'encrypt' )
			->willReturnCallback( static function ( $code, $nonce ) {
				if ( $code === 'CODE' && $nonce === 'NONCE' ) {
					return [ 'secret' => 'ENCRYPTED', 'nonce' => 'NONCE' ];
				}
				return [ 'secret' => 'WRONG', 'nonce' => $nonce ];
			} );
		$code = new RecoveryCode( $encryptionHelper, 'CODE' );

		$this->assertSame( 'CODE', $code->getCode() );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
		$this->assertSame( 'NONCE', $code->getNonce() );
	}

	public function testEncryptionThrowsIfDisabled() {
		$encryptionHelper = $this->createMock( EncryptionHelper::class );
		$encryptionHelper->method( 'isEnabled' )
			->willReturn( false );
		$encryptionHelper->method( 'encrypt' )
			->willReturnCallback( static function ( $code, $nonce ) {
				if ( $code === 'CODE' && $nonce === 'NONCE' ) {
					return [ 'secret' => 'ENCRYPTED', 'nonce' => 'NONCE' ];
				}
				return [ 'secret' => 'WRONG', 'nonce' => $nonce ];
			} );
		$code = new RecoveryCode( $encryptionHelper, 'CODE' );

		$this->expectException( RuntimeException::class );
		$this->assertSame( 'ENCRYPTED', $code->encryptCode( 'NONCE' ) );
	}

	/** @dataProvider provideExpiration */
	public function testExpiration(
		?string $codeExpiry,
		?string $expectedExpiry,
		bool $expectedIsExpired,
		bool $canMatch
	) {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );

		$encryptionHelper = $this->createStub( EncryptionHelper::class );
		$code = new RecoveryCode( $encryptionHelper, 'CODE', [ 'expiry' => $codeExpiry ] );

		$this->assertSame( $expectedExpiry, $code->getExpiryTimestamp() );
		$this->assertSame( $expectedExpiry === null, $code->isPermanent() );
		$this->assertSame( $expectedIsExpired, $code->isExpired() );
		$this->assertSame( $canMatch, $code->test( 'CODE' ) );
	}

	public static function provideExpiration(): iterable {
		yield 'No expiration configured' => [
			'codeExpiry' => null,
			'expectedExpiry' => null,
			'expectedIsExpired' => false,
			'canMatch' => true,
		];
		yield 'Expiration set in future' => [
			'codeExpiry' => '20270101000000',
			'expectedExpiry' => '20270101000000',
			'expectedIsExpired' => false,
			'canMatch' => true,
		];
		yield 'Expiration set in past' => [
			'codeExpiry' => '20250101000000',
			'expectedExpiry' => '20250101000000',
			'expectedIsExpired' => true,
			'canMatch' => false,
		];
		yield 'Expiration configured to invalid timestamp' => [
			'codeExpiry' => 'INVALID',
			'expectedExpiry' => null,
			'expectedIsExpired' => false,
			'canMatch' => true,
		];
	}
}
