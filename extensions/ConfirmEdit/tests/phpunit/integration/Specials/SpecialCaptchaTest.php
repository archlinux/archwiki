<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\Special;

use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\QuestyCaptcha\QuestyCaptcha;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaHashStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaSessionStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWiki\FileBackend\FileBackendGroup;
use MediaWiki\Request\FauxRequest;
use SpecialPageTestBase;
use StatusValue;
use Wikimedia\FileBackend\FileBackend;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Specials\SpecialCaptcha
 * @covers \MediaWiki\Extension\ConfirmEdit\QuestyCaptcha\QuestyCaptcha
 * @covers \MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha
 * @covers \MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha
 * @group Database
 */
class SpecialCaptchaTest extends SpecialPageTestBase {

	public function setUp(): void {
		parent::setUp();
		Hooks::unsetInstanceForTests();
		CaptchaStore::unsetInstanceForTests();
	}

	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		Hooks::unsetInstanceForTests();
		CaptchaStore::unsetInstanceForTests();
	}

	public static function provideCaptchaStorageClasses(): array {
		return [
			'CaptchaSessionStore (uses cookies)' => [ CaptchaSessionStore::class, true ],
			'CaptchaHashStore (does not use cookies)' => [ CaptchaHashStore::class, false ],
		];
	}

	/** @dataProvider provideCaptchaStorageClasses */
	public function testExecuteForSimpleCaptcha( $captchaStorageClass, $usesCookies ) {
		$this->overrideConfigValue( 'CaptchaStorageClass', $captchaStorageClass );
		$this->overrideConfigValue( 'CaptchaClass', SimpleCaptcha::class );

		[ $html ] = $this->executeSpecialPage( '', null, null, null, true );

		$this->assertStringContainsString( '(captchahelp-text)', $html );
		$this->assertStringContainsString( '(captchahelp-title)', $html );
		if ( $usesCookies ) {
			$this->assertStringContainsString( '(captchahelp-cookies-needed', $html );
		} else {
			$this->assertStringNotContainsString( '(captchahelp-cookies-needed', $html );
		}
	}

	/** @dataProvider provideCaptchaStorageClasses */
	public function testExecuteForQuestyCaptcha( $captchaStorageClass, $usesCookies ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'ConfirmEdit/QuestyCaptcha' );
		$this->overrideConfigValue( 'CaptchaStorageClass', $captchaStorageClass );
		$this->overrideConfigValue( 'CaptchaClass', QuestyCaptcha::class );

		[ $html ] = $this->executeSpecialPage( '', null, null, null, true );

		$this->assertStringContainsString( '(questycaptchahelp-text)', $html );
		$this->assertStringContainsString( '(captchahelp-title)', $html );
		if ( $usesCookies ) {
			$this->assertStringContainsString( '(captchahelp-cookies-needed', $html );
		} else {
			$this->assertStringNotContainsString( '(captchahelp-cookies-needed', $html );
		}
	}

	public function testExecuteForShowImageSubpageWhenMissingCaptchaIndex() {
		$this->overrideConfigValue( 'CaptchaFileBackend', 'some-backend' );
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );

		$mockFileBackendGroup = $this->createMock( FileBackendGroup::class );
		$mockFileBackendGroup->method( 'get' )
			->with( 'some-backend' )
			->willReturn( $this->createNoOpMock( FileBackend::class ) );
		$this->setService( 'FileBackendGroup', $mockFileBackendGroup );

		[ $html ] = $this->executeSpecialPage( 'image' );
		$this->assertStringContainsString( 'Requested bogus captcha image', $html );
	}

	public function testExecuteForShowImageSubpageWithInvalidCaptchaIndex() {
		$this->overrideConfigValue( 'CaptchaFileBackend', 'some-backend' );
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );

		$mockFileBackendGroup = $this->createMock( FileBackendGroup::class );
		$mockFileBackendGroup->method( 'get' )
			->with( 'some-backend' )
			->willReturn( $this->createNoOpMock( FileBackend::class ) );
		$this->setService( 'FileBackendGroup', $mockFileBackendGroup );

		$fancyCaptcha = new FancyCaptcha();
		$fancyCaptcha->storeCaptcha( [ 'index' => '123' ] );

		[ $html ] = $this->executeSpecialPage( 'image', new FauxRequest( [ 'wpCaptchaId' => 'abc' ] ) );
		$this->assertStringContainsString( 'Requested bogus captcha image', $html );
	}

	public function testExecuteForShowImageSubpageWithValidCaptchaIndex() {
		$this->overrideConfigValue( 'CaptchaFileBackend', 'some-backend' );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'captcha-render' );
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );

		$mockFileBackend = $this->createMock( FileBackend::class );
		$mockFileBackend->expects( $this->once() )
			->method( 'streamFile' )
			->with( [
				'src' => 'mwstore:///captcha-render/image_def_abc.png',
				'headers' => [ 'Cache-Control: private, s-maxage=0, max-age=3600' ],
			] )
			->willReturn( StatusValue::newGood() );

		$mockFileBackendGroup = $this->createMock( FileBackendGroup::class );
		$mockFileBackendGroup->method( 'get' )
			->with( 'some-backend' )
			->willReturn( $mockFileBackend );
		$this->setService( 'FileBackendGroup', $mockFileBackendGroup );

		$fancyCaptcha = new FancyCaptcha();
		$fancyCaptcha->storeCaptcha( [ 'index' => '123', 'hash' => 'abc', 'salt' => 'def' ] );

		[ $html ] = $this->executeSpecialPage( 'image', new FauxRequest( [ 'wpCaptchaId' => '123' ] ) );
		$this->assertSame( '', $html, 'FileBackend::streamFile should handle output' );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Captcha' );
	}
}
