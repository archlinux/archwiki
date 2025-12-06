<?php

namespace MediaWiki\Extension\ConfirmEdit\Test\Integration\Maintenance;

use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\Maintenance\GenerateFancyCaptchas;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Shellbox\Command\UnboxedResult;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Maintenance\GenerateFancyCaptchas
 */
class GenerateFancyCaptchasTest extends MaintenanceBaseTestCase {

	/** @var MockObject|GenerateFancyCaptchas|TestingAccessWrapper */
	protected $maintenance;

	private string $captchaDirectory;

	protected function getMaintenanceClass() {
		return GenerateFancyCaptchas::class;
	}

	/** @inheritDoc */
	protected function createMaintenance() {
		$maintenance = $this->getMockBuilder( $this->getMaintenanceClass() )
			->onlyMethods( [ 'generateFancyCaptchas' ] )
			->getMock();
		return TestingAccessWrapper::newFromObject( $maintenance );
	}

	public function setUp(): void {
		parent::setUp();

		$captchaDirectory = $this->getNewTempDirectory();
		$this->captchaDirectory = $captchaDirectory;

		$this->overrideConfigValues( [
			'CaptchaDirectoryLevels' => 1,
			'CaptchaSecret' => 'secret',
			'CaptchaDirectory' => $captchaDirectory,
			'CaptchaClass' => FancyCaptcha::class,
			'CaptchaStorageDirectory' => 'subfolder',
		] );

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

	public function testExecuteWhenCaptchaContainerAlreadyFilledToSpecifiedNumber() {
		// Create one captcha file in the $captchaDirectory/subfolder folder
		mkdir( $this->captchaDirectory . '/subfolder' );
		$captchaFilename = $this->captchaDirectory . '/subfolder/test.png';
		file_put_contents( $captchaFilename, 'abc' );

		$this->maintenance->expects( $this->never() )->method( 'generateFancyCaptchas' );

		$this->maintenance->setOption( 'fill', 1 );
		$this->maintenance->setOption( 'wordlist', $this->getNewTempFile() );
		$this->maintenance->setOption( 'font', $this->getNewTempFile() );
		$this->maintenance->execute();

		// Verify that the script did not attempt to generate more captchas as it already has a captcha
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Current number of captchas is 1.', $actualOutput );
		$this->assertStringContainsString( 'No need to generate any extra captchas.', $actualOutput );
		$this->assertDoesNotMatchRegularExpression( '/Generating.*new captchas\.\.\./', $actualOutput );
		$this->assertFileExists( $captchaFilename );
	}

	public function testExecuteWhenPythonScriptsExitsWithErrorCode() {
		// Make ::generateFancyCaptchas return a bad execution status
		$this->maintenance->expects( $this->once() )
			->method( 'generateFancyCaptchas' )
			->willReturnCallback( static function () {
				$unboxedResult = new UnboxedResult();
				$unboxedResult->exitCode( 123 );
				$unboxedResult->stderr( 'testing error' );
				return $unboxedResult;
			} );

		// Verify that the script exited early because the python script exited with an error
		$this->expectCallToFatalError();
		$this->expectOutputRegex(
			"/An error occurred when running captcha.py:\ntesting error/"
		);
		$this->maintenance->setOption( 'fill', 1 );
		$this->maintenance->setOption( 'wordlist', $this->getNewTempFile() );
		$this->maintenance->setOption( 'font', $this->getNewTempFile() );
		$this->maintenance->execute();
	}

	/**
	 * Validates that a call to {@link GenerateFancyCaptchas::generateFancyCaptchas} occurs with the correct
	 * arguments.
	 *
	 * @param int $fill The --fill argument to the maintenance script
	 * @param array $additionalExpectedArguments Additional arguments to the python script that should be expected
	 * @param callable $onGenerateFancyCaptchas A callback which is called when ::generateFancyCaptchas is called
	 */
	private function validateGenerateFancyCaptchasCalled(
		int $fill, array $additionalExpectedArguments, callable $onGenerateFancyCaptchas
	): void {
		$this->maintenance->expects( $this->once() )
			->method( 'generateFancyCaptchas' )
			->willReturnCallback(
				function ( array $cmd ) use (
					$fill, $additionalExpectedArguments, $onGenerateFancyCaptchas
				) {
					// The --output directory should be the 5th argument.
					$temporaryCaptchaGenerationDirectory = $cmd[5];

					$this->assertArrayEquals(
						array_merge( [
							'python3', MW_INSTALL_PATH . '/extensions/ConfirmEdit/captcha.py',
							'--key', 'secret',
							'--output', $temporaryCaptchaGenerationDirectory,
							'--count', (string)$fill,
							'--dirs', 1,
						], $additionalExpectedArguments ),
						$cmd, true
					);

					// Sanity check that the $temporaryCaptchaGenerationDirectory we found was actually exists
					$this->assertDirectoryExists( $temporaryCaptchaGenerationDirectory );
					$onGenerateFancyCaptchas( $temporaryCaptchaGenerationDirectory );

					$unboxedResult = new UnboxedResult();
					$unboxedResult->exitCode( 0 );
					return $unboxedResult;
				}
			);
	}

	/**
	 * Gets the hash for an answer to a FancyCaptcha.
	 * Uses the method described at {@link FancyCaptcha::getCaptcha}.
	 */
	private function getHash( string $secret, string $salt, string $answer ): string {
		return substr( md5( $secret . $salt . $answer . $secret . $salt ), 0, 16 );
	}

	public function testExecuteWhenPythonScriptDoesNotGenerateAnyImages() {
		$this->maintenance->setOption( 'fill', 1 );

		$wordList = $this->getNewTempFile();
		$font = $this->getNewTempFile();
		$this->maintenance->setOption( 'wordlist', $wordList );
		$this->maintenance->setOption( 'font', $font );

		// Verify that ::generateFancyCaptchas is called with the correct arguments but do not generate any
		// captcha files as a result of the call.
		$this->validateGenerateFancyCaptchasCalled(
			1, [ '--wordlist', $wordList, '--font', $font ], static fn () => null
		);

		// Verify that the script found no captchas generated and exited early.
		$this->expectCallToFatalError();
		$this->expectOutputRegex(
			'/No generated captchas found in temporary directory; did captcha.py actually succeed\?/'
		);
		$this->maintenance->execute();
	}

	public function testExecuteForGenerationOfOneImage() {
		$this->maintenance->setOption( 'fill', 1 );

		$wordList = $this->getNewTempFile();
		$font = $this->getNewTempFile();
		$this->maintenance->setOption( 'wordlist', $wordList );
		$this->maintenance->setOption( 'font', $font );

		// Make a filename for a captcha that we will "generate" when ::generateFancyCaptchas is called.
		$imageSalt = '0';
		$imageHash = $this->getHash( 'secret', $imageSalt, 'def' );
		$captchaImageFilename = "image_{$imageSalt}_{$imageHash}.png";

		// Verify that ::generateFancyCaptchas is called with the correct arguments and that when it is called create
		// a test fake captcha file in the temporary captcha generation directory.
		$temporaryCaptchaGenerationDirectory = '';
		$this->validateGenerateFancyCaptchasCalled(
			1, [ '--wordlist', $wordList, '--font', $font ],
			static function ( $tmpDir ) use ( $captchaImageFilename, &$temporaryCaptchaGenerationDirectory ) {
				file_put_contents( $tmpDir . '/' . $captchaImageFilename, 'abc' );
				$temporaryCaptchaGenerationDirectory = $tmpDir;
			}
		);

		$this->maintenance->execute();

		// Verify that the script generated one captcha and copied it to the captcha storage directory
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Current number of captchas is 0.', $actualOutput );
		$this->assertStringContainsString( 'Generation script for 1 captchas ran', $actualOutput );
		$this->assertStringContainsString( 'Enumerated 1 temporary captchas', $actualOutput );
		$this->assertStringContainsString( 'Copied 1 captchas to storage', $actualOutput );
		$this->assertStringContainsString( 'Removing temporary files... Done', $actualOutput );
		$this->assertFileExists( $this->captchaDirectory . "/$imageHash[0]/" . $captchaImageFilename );
		$this->assertDirectoryDoesNotExist( $temporaryCaptchaGenerationDirectory );
	}

	public function testExecuteWhereFillIsTwoButOnlyOneGeneratesAndDeletingOldSpecified() {
		$this->maintenance->setOption( 'fill', 2 );

		$wordList = $this->getNewTempFile();
		$font = $this->getNewTempFile();
		$this->maintenance->setOption( 'wordlist', $wordList );
		$this->maintenance->setOption( 'font', $font );
		$this->maintenance->setOption( 'delete', 1 );

		// Make a captcha that we will delete using the script
		$firstImageSalt = '0';
		$firstImageHash = $this->getHash( 'secret', $firstImageSalt, 'def' );
		$firstCaptchaImageFilename = "image_{$firstImageSalt}_{$firstImageHash}.png";
		file_put_contents( $this->captchaDirectory . '/' . $firstCaptchaImageFilename, 'abc' );

		// Make a filename for a captcha that we will "generate" when ::generateFancyCaptchas is called.
		$secondImageSalt = '0';
		$secondImageHash = $this->getHash( 'secret', $secondImageSalt, 'abcdef' );
		$secondCaptchaImageFilename = "image_{$secondImageSalt}_{$secondImageHash}.png";

		// Verify that ::generateFancyCaptchas is called with the correct arguments and that when it is called create
		// a test fake captcha file in the temporary captcha generation directory.
		$temporaryCaptchaGenerationDirectory = '';
		$this->validateGenerateFancyCaptchasCalled(
			2, [ '--wordlist', $wordList, '--font', $font ],
			static function ( $tmpDir ) use ( $secondCaptchaImageFilename, &$temporaryCaptchaGenerationDirectory ) {
				file_put_contents( $tmpDir . '/' . $secondCaptchaImageFilename, 'abc' );
				$temporaryCaptchaGenerationDirectory = $tmpDir;
			}
		);

		$this->maintenance->execute();

		// Verify that the script deleted the old captcha, generated one captcha, and warned that it couldn't fill up
		// to two captchas.
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringNotContainsString( 'Current number of captchas.', $actualOutput );
		$this->assertStringContainsString( 'Generation script for 2 captchas ran', $actualOutput );
		$this->assertStringContainsString(
			'Expecting 2 new captchas, only 1 found on disk; continuing', $actualOutput
		);
		$this->assertStringContainsString( 'Getting a list of old captchas to delete', $actualOutput );
		$this->assertStringContainsString( 'Enumerated 1 temporary captchas', $actualOutput );
		$this->assertStringContainsString( 'Copied 1 captchas to storage', $actualOutput );
		$this->assertStringContainsString( 'Deleted 1 old captchas', $actualOutput );
		$this->assertStringContainsString( 'Removing temporary files... Done', $actualOutput );

		$this->assertFileExists( $this->captchaDirectory . "/$secondImageHash[0]/" . $secondCaptchaImageFilename );
		$this->assertFileDoesNotExist( $this->captchaDirectory . $firstCaptchaImageFilename );
		$this->assertDirectoryDoesNotExist( $temporaryCaptchaGenerationDirectory );
	}
}
