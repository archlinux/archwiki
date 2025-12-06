<?php

namespace MediaWiki\Extension\ConfirmEdit\Test\Integration\Maintenance;

use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\Maintenance\DeleteOldFancyCaptchas;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Maintenance\DeleteOldFancyCaptchas
 */
class DeleteOldFancyCaptchasTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return DeleteOldFancyCaptchas::class;
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

	public function testExecuteWhenNothingToDelete() {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );

		// Create one captcha file in the $captchaDirectory/subfolder folder that is not expired.
		mkdir( $captchaDirectory . '/subfolder' );
		$captchaFilename = $captchaDirectory . '/subfolder/test.png';
		file_put_contents( $captchaFilename, 'abc' );

		$this->maintenance->setOption( 'date', '20200405060708' );
		$this->maintenance->execute();

		// Verify that the captcha file was not deleted after the maintenance script run
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Current number of captchas is 1.', $actualOutput );
		$this->assertStringContainsString( 'No old fancy captchas to delete!', $actualOutput );
		$this->assertFileExists( $captchaFilename );
	}

	public function testExecuteWhenOneCaptchaToDelete() {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );

		// Create one captcha file in the $captchaDirectory/subfolder folder.
		mkdir( $captchaDirectory . '/subfolder' );
		$expiredCaptchaFilename = $captchaDirectory . '/subfolder/test.png';
		file_put_contents( $expiredCaptchaFilename, 'abc' );
		touch( $expiredCaptchaFilename, ConvertibleTimestamp::convert( TS_UNIX, '20240405060708' ) );
		$notExpiredCaptchaFilename = $captchaDirectory . '/subfolder/testing.png';
		file_put_contents( $notExpiredCaptchaFilename, 'abc' );

		$this->maintenance->setOption( 'date', '20250405060708' );
		$this->maintenance->execute();

		// Verify that the old captcha file was deleted but not the new one
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Current number of captchas is 2.', $actualOutput );
		$this->assertStringContainsString( '1 old fancy captchas to be deleted', $actualOutput );
		$this->assertStringContainsString( '1 old fancy captchas deleted', $actualOutput );
		$this->assertFileExists( $notExpiredCaptchaFilename );
		$this->assertFileDoesNotExist( $expiredCaptchaFilename );
	}
}
