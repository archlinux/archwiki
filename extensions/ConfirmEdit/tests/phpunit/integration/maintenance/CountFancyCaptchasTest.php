<?php

namespace MediaWiki\Extension\ConfirmEdit\Test\Integration\Maintenance;

use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\Maintenance\CountFancyCaptchas;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Maintenance\CountFancyCaptchas
 */
class CountFancyCaptchasTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return CountFancyCaptchas::class;
	}

	public function setUp(): void {
		parent::setUp();
		Hooks::unsetInstanceForTests();
	}

	public static function tearDownAfterClass(): void {
		parent::tearDownAfterClass();
		Hooks::unsetInstanceForTests();
	}

	public function testExecuteWhenCaptchaInstanceNotFancyCaptcha() {
		$this->overrideConfigValue( 'CaptchaClass', SimpleCaptcha::class );

		$this->expectOutputRegex( '/\$wgCaptchaClass is not FancyCaptcha/' );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public function testExecute() {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );

		// Create one captcha file in the $captchaDirectory/subfolder folder.
		mkdir( $captchaDirectory . '/subfolder' );
		file_put_contents( $captchaDirectory . '/subfolder/test.png', 'abc' );

		$this->expectOutputString( "Current number of stored captchas is 1.\n" );
		$this->maintenance->execute();
	}
}
