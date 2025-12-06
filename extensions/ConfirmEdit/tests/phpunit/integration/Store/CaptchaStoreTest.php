<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\Store;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaCacheStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaHashStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaSessionStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore
 * @covers \MediaWiki\Extension\ConfirmEdit\Store\CaptchaSessionStore
 * @covers \MediaWiki\Extension\ConfirmEdit\Store\CaptchaHashStore
 * @covers \MediaWiki\Extension\ConfirmEdit\Store\CaptchaCacheStore
 */
class CaptchaStoreTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();
		CaptchaStore::unsetInstanceForTests();
	}

	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		CaptchaStore::unsetInstanceForTests();
	}

	/** @dataProvider provideCaptchaStoreClasses */
	public function testStoreRetrieveAndClearLoop( $captchaStorageClass, $storageClassNeedsCookies ) {
		$this->overrideConfigValue( 'CaptchaStorageClass', $captchaStorageClass );

		$captchaStore = CaptchaStore::get();
		$this->assertInstanceOf( $captchaStorageClass, $captchaStore );

		// Test that ::store works and that ::retrieve gets us the value we stored.
		$this->assertFalse( $captchaStore->retrieve( '123' ) );
		$captchaStore->store( '123', [ 'fake' => 'result' ] );
		$this->assertSame( [ 'fake' => 'result' ], $captchaStore->retrieve( '123' ) );

		// Test that ::retrieve does not fetch the stored index if we provide a different index
		$this->assertFalse( $captchaStore->retrieve( '234' ) );

		// Test that ::clear does not clear anything other than the index provided
		$captchaStore->clear( '234' );
		$this->assertSame( [ 'fake' => 'result' ], $captchaStore->retrieve( '123' ) );

		// Test that ::clear clears the index we stored
		$captchaStore->clear( '123' );
		$this->assertFalse( $captchaStore->retrieve( '123' ) );

		$this->assertSame( $storageClassNeedsCookies, $captchaStore->cookiesNeeded() );
	}

	public static function provideCaptchaStoreClasses(): array {
		return [
			'CaptchaCacheStore' => [ CaptchaCacheStore::class, false ],
			'CaptchaHashStore' => [ CaptchaHashStore::class, false ],
			'CaptchaSessionStore' => [ CaptchaSessionStore::class, true ],
		];
	}

	public function testInvalidCaptchaStorageClassCausesConfigException() {
		$invalidCaptchaStorageClass = SimpleCaptcha::class;
		$this->overrideConfigValue( 'CaptchaStorageClass', $invalidCaptchaStorageClass );

		$this->expectException( ConfigException::class );
		$this->expectExceptionMessage( "Invalid CaptchaStore class $invalidCaptchaStorageClass" );
		CaptchaStore::get();
	}

	public function testGetCachesTheInstance() {
		// Get the instance, which should be a cached instance of CaptchaCacheStore
		$this->overrideConfigValue( 'CaptchaStorageClass', CaptchaCacheStore::class );
		$actualCaptchaStoreInstance = CaptchaStore::get();

		// Change $wgCaptchaStorageClass so that a different instance being returned (and therefore no instance cache)
		// can be detected.
		$this->overrideConfigValue( 'CaptchaStorageClass', CaptchaSessionStore::class );

		$this->assertSame( $actualCaptchaStoreInstance, CaptchaStore::get() );
	}
}
