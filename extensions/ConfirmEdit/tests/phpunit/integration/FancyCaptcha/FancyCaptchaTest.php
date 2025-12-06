<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\FancyCaptcha;

use InvalidArgumentException;
use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\HTMLFancyCaptchaField;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWiki\Message\Message;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use UnderflowException;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha
 * @group Database
 */
class FancyCaptchaTest extends MediaWikiIntegrationTestCase {

	public function testGetName() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ConfirmEdit/FancyCaptcha' );
		$this->assertEquals( 'Fancy CAPTCHA', ( new FancyCaptcha )->getName() );
	}

	/** @dataProvider provideGetCaptchaCount */
	public function testGetCaptchaCount( $filenames, $expectedCount ) {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );

		// Create captcha files in the $captchaDirectory/subfolder folder.
		mkdir( $captchaDirectory . '/subfolder' );
		foreach ( $filenames as $filename ) {
			file_put_contents( $captchaDirectory . '/subfolder/' . $filename, 'abc' );
		}

		$fancyCaptcha = new FancyCaptcha();
		$this->assertSame( $expectedCount, $fancyCaptcha->getCaptchaCount() );
	}

	public static function provideGetCaptchaCount(): array {
		return [
			'No captcha files present' => [ [], 0 ],
			'One captcha file present' => [ [ 'test.png' ], 1 ],
			'Three captcha files present' => [ [ 'test.png', 'testing.png', 'abc.png' ], 3 ],
		];
	}

	public function testGetCaptchaWhenOutOfImages() {
		// Get a captcha directory with no images
		$captchaDirectory = $this->getNewTempDirectory();
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );

		// Attempt to pass the captcha and expect it throws because no images could be found
		$fancyCaptcha = new FancyCaptcha();
		$this->expectException( UnderflowException::class );
		$this->expectExceptionMessage( 'Ran out of captcha images' );
		$fancyCaptcha->getCaptcha();
	}

	/**
	 * Gets the hash for an answer to a FancyCaptcha.
	 * Uses the method described at {@link FancyCaptcha::getCaptcha}.
	 */
	private function getHash( string $secret, string $salt, string $answer ): string {
		return substr( md5( $secret . $salt . $answer . $secret . $salt ), 0, 16 );
	}

	/** @dataProvider providePassCaptcha */
	public function testPassCaptcha( bool $captchaPassedSuccessfully, bool $deleteOnSolve ) {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );
		$this->overrideConfigValue( 'CaptchaSecret', 'secret' );
		$this->overrideConfigValue( 'CaptchaDeleteOnSolve', $deleteOnSolve );

		// Create one captcha file in the $captchaDirectory folder with a defined hash and salt in the filename.
		$correctAnswer = 'abcdef';
		$imageSalt = '0';
		$imageHash = $this->getHash( 'secret', $imageSalt, $correctAnswer );
		$captchaImageFilename = $captchaDirectory . "/image_{$imageSalt}_{$imageHash}.png";
		file_put_contents( $captchaImageFilename, 'abc' );

		// Either use the correct answer or another answer depending on whether the user should pass the captcha.
		$userProvidedAnswer = $captchaPassedSuccessfully ? $correctAnswer : 'abc';

		// Expect that a debug log is created to indicate that the captcha either was solved or was not solved.
		$mockLogger = $this->createMock( LoggerInterface::class );
		if ( $captchaPassedSuccessfully ) {
			$mockLogger->expects( $this->once() )
				->method( 'debug' )
				->with(
					'FancyCaptcha: answer hash matches expected {expected_hash}',
					[ 'expected_hash' => $imageHash ]
				);
		} else {
			$mockLogger->expects( $this->once() )
				->method( 'debug' )
				->with(
					'FancyCaptcha: answer hashes to {answer_hash}, expected {expected_hash}',
					[
						'answer_hash' => $this->getHash( 'secret', $imageSalt, $userProvidedAnswer ),
						'expected_hash' => $imageHash,
					]
				);
		}
		$this->setLogger( 'captcha', $mockLogger );

		// Attempt to pass the captcha and expect that it either passes or does not pass
		$fancyCaptcha = new FancyCaptcha();
		$info = $fancyCaptcha->getCaptcha();
		$index = $fancyCaptcha->storeCaptcha( $info );
		$this->assertSame(
			$captchaPassedSuccessfully,
			$fancyCaptcha->passCaptchaFromRequest(
				new FauxRequest( [ 'wpCaptchaWord' => $userProvidedAnswer, 'wpCaptchaId' => $index ] ),
				$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
			)
		);

		// The captcha image we used should still exist unless $wgCaptchaDeleteOnSolve is true and the user passed
		// the captcha.
		if ( $deleteOnSolve && $captchaPassedSuccessfully ) {
			$this->assertFileDoesNotExist( $captchaImageFilename );
		} else {
			$this->assertFileExists( $captchaImageFilename );
		}
	}

	public static function providePassCaptcha(): array {
		return [
			'Passes FancyCaptcha check, no delete on solve' => [ true, false ],
			'Passes FancyCaptcha check, delete on solve' => [ true, true ],
			'Fails FancyCaptcha check' => [ false, true ],
		];
	}

	public function testHashFromImageNameForValidName() {
		$fancyCaptcha = new FancyCaptcha();
		$this->assertSame(
			[ '01234', 'abcdef' ],
			$fancyCaptcha->hashFromImageName( "image_01234_abcdef.png" )
		);
	}

	public function testHashFromImageNameForInvalidName() {
		$fancyCaptcha = new FancyCaptcha();
		$this->expectException( InvalidArgumentException::class );
		$fancyCaptcha->hashFromImageName( "image_ghjk_4567.png" );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}

	/**
	 * Sets up the FancyCaptcha image storage with a valid image that can be picked to use as a captcha by
	 * {@link FancyCaptcha::pickImage}.
	 *
	 * @return string[] The hash and salt for the test fancy captcha image
	 */
	private function setUpTestFancyCaptchaImage(): array {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );
		$this->overrideConfigValue( 'CaptchaSecret', 'secret' );

		// Create one captcha file in the $captchaDirectory folder with a defined hash and salt in the filename.
		$correctAnswer = 'abcdef';
		$imageSalt = '0';
		$imageHash = $this->getHash( 'secret', $imageSalt, $correctAnswer );
		$captchaImageFilename = $captchaDirectory . "/image_{$imageSalt}_{$imageHash}.png";
		file_put_contents( $captchaImageFilename, 'abc' );

		return [ 'hash' => $imageHash, 'salt' => $imageSalt ];
	}

	public function testGetFormInformation() {
		$this->setUserLang( 'qqx' );
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$captchaImageDetails = $this->setUpTestFancyCaptchaImage();

		// Get the form information, validate that it picks the test image above, and has the expected form fields
		$fancyCaptcha = new FancyCaptcha();
		$fancyCaptcha->setAction( 'edit' );
		$formInformation = $fancyCaptcha->getFormInformation();
		$this->assertArrayEquals( [ 'ext.confirmEdit.fancyCaptcha' ], $formInformation['modules'] );
		$this->assertArrayEquals(
			[ 'codex-styles', 'ext.confirmEdit.fancyCaptcha.styles' ],
			$formInformation['modulestyles']
		);

		// Verify the expected form fields are there and the HTML structure is as expected
		$html = $formInformation['html'];
		$this->assertStringContainsString( '(fancycaptcha-captcha)', $html );
		$this->assertStringNotContainsString( 'createacct-imgcaptcha-help', $html );
		$captchaIdElement = DOMCompat::querySelector( DOMUtils::parseHTML( $html ), '#wpCaptchaId' );
		$this->assertNotNull( $captchaIdElement );
		$actualIndex = $captchaIdElement->getAttribute( 'value' );

		$reloadField = $this->assertAndGetByElementClass( $html, 'fancycaptcha-reload' );
		$this->assertStringContainsString( '(fancycaptcha-reload-text)', $reloadField );

		$captchaContainer = $this->assertAndGetByElementClass( $html, 'fancycaptcha-captcha-container' );
		$imageContainer = $this->assertAndGetByElementClass( $captchaContainer, 'fancycaptcha-image-container' );
		$this->assertStringContainsString( 'fancycaptcha-image', $imageContainer );
		$this->assertStringContainsString( 'wpCaptchaId=' . urlencode( $actualIndex ), $imageContainer );
		$this->assertStringContainsString( 'wpCaptchaWord', $captchaContainer );
		$this->assertStringContainsString( '(fancycaptcha-imgcaptcha-ph)', $captchaContainer );

		// Verify the actual index is for the current captcha being used
		$this->assertArrayEquals(
			[
				'viewed' => false, 'hash' => $captchaImageDetails['hash'], 'salt' => $captchaImageDetails['salt'],
				'index' => $actualIndex,
			],
			CaptchaStore::get()->retrieve( $actualIndex ), false, true
		);
	}

	public function testGetFormInformationForAccountCreation() {
		$this->setUserLang( 'qqx' );
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->setUpTestFancyCaptchaImage();

		// Get the form information and validate the account creation help message is shown
		$fancyCaptcha = new FancyCaptcha();
		$fancyCaptcha->setAction( 'createaccount' );
		$formInformation = $fancyCaptcha->getFormInformation();
		$this->assertStringContainsString( 'createacct-imgcaptcha-help', $formInformation['html'] );
	}

	public function testGetCaptchaInfo() {
		$fancyCaptcha = new FancyCaptcha();
		$this->assertSame(
			'/index.php?title=Special:Captcha/image&wpCaptchaId=1234abcdef',
			$fancyCaptcha->getCaptchaInfo( [], '1234abcdef' )
		);
	}

	public function testAddCaptchaAPIWhenImageExists() {
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$captchaImageDetails = $this->setUpTestFancyCaptchaImage();

		$fancyCaptcha = TestingAccessWrapper::newFromObject( new FancyCaptcha() );

		$actualCaptchaInformation = [];

		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $fancyCaptcha->object );
		$methodReflection = $classReflection->getMethod( 'addCaptchaAPI' );
		$methodReflection->invokeArgs( $fancyCaptcha->object, [ &$actualCaptchaInformation ] );

		$this->assertArrayContains(
			[ 'captcha' => [ 'type' => 'image', 'mime' => 'image/png' ] ], $actualCaptchaInformation
		);

		// Verify the actual index is for the current captcha being used
		$this->assertArrayHasKey( 'id', $actualCaptchaInformation['captcha'] );
		$actualIndex = $actualCaptchaInformation['captcha']['id'];
		$this->assertArrayEquals(
			[
				'viewed' => false, 'hash' => $captchaImageDetails['hash'], 'salt' => $captchaImageDetails['salt'],
				'index' => $actualIndex,
			],
			CaptchaStore::get()->retrieve( $actualIndex ), false, true
		);

		// Verify the URL provided uses the actual index and has the expected structure
		$this->assertSame(
			"/index.php?title=Special:Captcha/image&wpCaptchaId=$actualIndex",
			$actualCaptchaInformation['captcha']['url']
		);
	}

	public function testAddCaptchaAPIWhenOutOfImages() {
		$captchaDirectory = $this->getNewTempDirectory();

		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$this->overrideConfigValue( 'CaptchaDirectory', $captchaDirectory );
		$this->overrideConfigValue( 'CaptchaStorageDirectory', 'subfolder' );
		$this->overrideConfigValue( 'CaptchaSecret', 'secret' );

		$fancyCaptcha = TestingAccessWrapper::newFromObject( new FancyCaptcha() );
		$actualCaptchaInformation = [];

		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $fancyCaptcha->object );
		$methodReflection = $classReflection->getMethod( 'addCaptchaAPI' );
		$methodReflection->invokeArgs( $fancyCaptcha->object, [ &$actualCaptchaInformation ] );

		$this->assertArrayEquals(
			[ 'captcha' => [ 'error' => 'Out of images' ] ],
			$actualCaptchaInformation,
			false, true
		);
	}

	public function testOnAuthChangeFormFieldsWhenCaptchaNotRequested() {
		$hCaptcha = new FancyCaptcha();

		// Verify that nothing happens if the CaptchaAuthenticationRequest is not included in the list of $requests.
		$formDescriptor = [];
		$hCaptcha->onAuthChangeFormFields( [], [], $formDescriptor, '' );
		$this->assertSame( [], $formDescriptor );
	}

	public function testOnAuthChangeFormFieldsWhenCaptchaRequested() {
		$this->overrideConfigValue( 'CaptchaClass', FancyCaptcha::class );
		$captchaImageDetails = $this->setUpTestFancyCaptchaImage();

		$hCaptcha = new FancyCaptcha();

		// Verify that the onAuthChangeFormFields handler updates the form fields as expected
		$formDescriptor = [ 'captchaWord' => [ 'id' => 'test' ], 'captchaInfo' => [ 'id' => 'test2' ] ];
		$hCaptcha->onAuthChangeFormFields(
			[ $hCaptcha->createAuthenticationRequest() ], [], $formDescriptor, 'create'
		);
		$this->assertArrayContains(
			[ 'captchaWord' => [ 'id' => 'test', 'class' => HTMLFancyCaptchaField::class, 'showCreateHelp' => true ] ],
			$formDescriptor
		);
		$this->assertArrayNotHasKey( 'captchaInfo', $formDescriptor );
		$this->assertInstanceOf( Message::class, $formDescriptor['captchaWord']['label-message'] );
		$expectedUrlPathExcludingCaptchaId = '/index.php?title=Special:Captcha/image&wpCaptchaId=';
		$this->assertStringContainsString(
			$expectedUrlPathExcludingCaptchaId, $formDescriptor['captchaWord']['imageUrl']
		);

		// Verify that the index in the URL is for the captcha image that we generated at the start of the test
		$actualIndex = substr(
			$formDescriptor['captchaWord']['imageUrl'], strlen( $expectedUrlPathExcludingCaptchaId )
		);
		$this->assertArrayEquals(
			[
				'viewed' => false, 'hash' => $captchaImageDetails['hash'], 'salt' => $captchaImageDetails['salt'],
				'index' => $actualIndex,
			],
			CaptchaStore::get()->retrieve( $actualIndex ), false, true
		);
	}
}
