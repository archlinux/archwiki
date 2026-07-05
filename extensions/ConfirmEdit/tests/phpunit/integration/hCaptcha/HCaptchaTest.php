<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\hCaptcha;

use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiEditPage;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HTMLHCaptchaField;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Http\MWHttpRequest;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha
 */
class HCaptchaTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testGetName() {
		$this->assertEquals( 'hCaptcha', ( new hCaptcha )->getName() );
	}

	public function testShowEditFormFieldsDoesNothing() {
		$outputPage = $this->createNoOpMock( OutputPage::class );

		$objectUnderTest = new HCaptcha();
		$objectUnderTest->showEditFormFields( $this->createMock( EditPage::class ), $outputPage );
	}

	public function testGetFormInformationWhenNoError() {
		// Mock the HCaptchaOutput service to expect a call and return mock HTML. We test that service through
		// the tests in HTMLHCaptchaField, so don't need to repeat the tests here.
		$mockHCaptchaOutput = $this->createMock( HCaptchaOutput::class );
		$mockHCaptchaOutput->expects( $this->once() )
			->method( 'addHCaptchaToForm' )
			->with( RequestContext::getMain()->getOutput(), false )
			->willReturn( 'mock html' );
		$this->setService( 'HCaptchaOutput', $mockHCaptchaOutput );

		$hCaptcha = new HCaptcha();
		$this->assertSame( [ 'html' => 'mock html' ], $hCaptcha->getFormInformation() );
	}

	public function testGetFormInformationWhenCaptchaHasError() {
		$mockOutputPage = $this->createMock( OutputPage::class );

		// Mock the HCaptchaOutput service to expect a call and return mock HTML. We test that service through
		// the tests in HTMLHCaptchaField, so don't need to repeat the tests here.
		$mockHCaptchaOutput = $this->createMock( HCaptchaOutput::class );
		$mockHCaptchaOutput->expects( $this->once() )
			->method( 'addHCaptchaToForm' )
			->with( $mockOutputPage, true )
			->willReturn( 'mock html' );
		$this->setService( 'HCaptchaOutput', $mockHCaptchaOutput );

		// Mock that the site-verify URL call will fail with a HTTP 500 error so that we get an error for
		// the form information.
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::wrap( StatusValue::newFatal( 'http-error-500' ) ) );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 500 );
		$this->installMockHttp( $mwHttpRequest );

		$hCaptcha = new HCaptcha();
		$hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		);
		$this->assertSame( 'http', $hCaptcha->getError() );
		$this->assertSame( [ 'html' => 'mock html' ], $hCaptcha->getFormInformation( 1, $mockOutputPage ) );
	}

	/** @dataProvider provideGetFormInformationScenarios */
	public function testGetFormInformationWhenActionIsSet(
		string $action, bool $hasError, bool $expectedErrorFlag, string $expectedMessage
	) {
		$this->setUserLang( 'qqx' );
		$mockOutputPage = $this->createMock( OutputPage::class );

		// Mock the HCaptchaOutput service to expect a call and return mock HTML
		$mockHCaptchaOutput = $this->createMock( HCaptchaOutput::class );
		$mockHCaptchaOutput->expects( $this->once() )
			->method( 'addHCaptchaToForm' )
			->with( $mockOutputPage, $expectedErrorFlag )
			->willReturn( 'mock html' );
		$this->setService( 'HCaptchaOutput', $mockHCaptchaOutput );

		if ( $hasError ) {
			// Mock that the site-verify URL call will fail with a HTTP 500 error.
			// Must be set before constructing HCaptcha so the constructor captures the mock.
			$mwHttpRequest = $this->createMock( MWHttpRequest::class );
			$mwHttpRequest->method( 'execute' )
				->willReturn( Status::wrap( StatusValue::newFatal( new ApiRawMessage( 'Some error' ) ) ) );
			$mwHttpRequest->method( 'getStatus' )
				->willReturn( 500 );
			$this->installMockHttp( $mwHttpRequest );

			$hCaptcha = new HCaptcha();
			$hCaptcha->passCaptchaFromRequest(
				new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
				$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
			);
			$this->assertSame( 'http', $hCaptcha->getError() );
		} else {
			$hCaptcha = new HCaptcha();
			$this->assertNull( $hCaptcha->getError() );
		}

		// Test the message content
		$message = $hCaptcha->getMessage( $action );
		$this->assertSame( $expectedMessage, $message->text() );

		// Test that getFormInformation calls addHCaptchaToForm with the expected error flag
		$this->assertSame(
			[ 'html' => 'mock html' ],
			$hCaptcha->getFormInformation( 1, $mockOutputPage )
		);
	}

	public static function provideGetFormInformationScenarios(): array {
		return [
			'Edit action with no error - should return empty message' => [
				'edit',
				false,
				false,
				''
			],
			'Edit action with error - should return error message' => [
				'edit',
				true,
				true,
				'<div class="error">(hcaptcha-edit)</div>'
			],
			'Createaccount action with no error - should return normal message' => [
				'createaccount',
				false,
				false,
				'(hcaptcha-createaccount)'
			],
			'Createaccount action with error - should return error message' => [
				'createaccount',
				true,
				true,
				'<div class="error">(hcaptcha-createaccount)</div>'
			]
		];
	}

	public function testPassCaptchaForHttpError() {
		$this->setUserLang( 'qqx' );
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );
		$this->overrideConfigValue( 'HCaptchaProxy', 'proxy.test.com' );

		// Mock that the site-verify URL call will fail with a HTTP 500 error
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::wrap( StatusValue::newFatal( 'http-error-500' ) ) );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 500 );

		// Mock HttpRequestFactory directly so that we can check the URL and options are as expected.
		// Initial attempt plus two retries should all use the same URL and options.
		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequestFactory->expects( $this->exactly( 3 ) )
			->method( 'create' )
			->willReturnCallback( function ( $url, $options ) use ( $mwHttpRequest ) {
				$this->assertSame( 'https://api.hcaptcha.com/siteverify', $url );
				$this->assertArrayEquals(
					[
						'method' => 'POST',
						'postData' => [
							'response' => 'abcdef',
							'secret' => 'secretkey',
							'remoteip' => '127.0.0.1',
						],
						'proxy' => 'proxy.test.com',
						'timeout' => 1,
					],
					$options,
					false,
					true
				);
				return $mwHttpRequest;
			} );
		$this->setService( 'HttpRequestFactory', $mockHttpRequestFactory );

		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );

		// Verify that a warning log is created for each retry attempt,
		// and error logs for the exhausted retries and the final failure.
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->exactly( 2 ) )
			->method( 'warning' )
			->with(
				'SiteVerify API request failed on attempt {attempt} of {maxAttempts}, retrying. Error: {error}',
				$this->anything()
			);
		$mockLogger->expects( $this->exactly( 2 ) )
			->method( 'error' )
			->willReturnCallback( function ( $message, $context ) {
				static $callIndex = 0;
				if ( $callIndex === 0 ) {
					$this->assertSame(
						'All SiteVerify API attempts failed. Error: {error}',
						$message
					);
				} else {
					$this->assertSame(
						'Unable to validate response. Error: {error}',
						$message
					);
					$expectedSubset = [
						'error' => 'http-error-500',
						'user' => '1.2.3.4',
						'captcha_type' => 'hcaptcha',
						'captcha_action' => 'edit',
						'captcha_trigger' => "edit trigger by '~2025-198' at [[Test]]",
						'hcaptcha_token' => 'abcdef',
						'clientIp' => '127.0.0.1',
						'ua' => false,
						'user_exists_locally' => false,
					];
					$this->assertArrayContains( $expectedSubset, $context );
				}
				$callIndex++;
			} );
		$this->setLogger( 'captcha', $mockLogger );

		// Attempt to pass the captcha using a fake response that we expect to pass to the API.
		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( "edit trigger by '~2025-198' at [[Test]]" );
		$this->assertFalse( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );

		// Verify that the captcha verification failed with an error of 'http'
		$this->assertSame( 'http', $hCaptcha->getError() );

		// Verify that ::getMessage will output the message as usual but with an error background
		$actualMessage = $hCaptcha->getMessage( 'edit' );
		$this->assertSame( '<div class="error">(hcaptcha-edit)</div>', $actualMessage->text() );

		// Verify that the exhausted counter and all three timing metrics were emitted
		$formatted = $statsHelper->consumeAllFormatted();
		$this->assertCount( 4, $formatted );
		$this->assertMatchesRegularExpression(
			'/^mediawiki\.ConfirmEdit\.hcaptcha_siteverify_call:[\d.]+\|ms\|#status:failed,is_retry:false$/',
			$formatted[0]
		);
		$this->assertMatchesRegularExpression(
			'/^mediawiki\.ConfirmEdit\.hcaptcha_siteverify_call:[\d.]+\|ms\|#status:failed,is_retry:true$/',
			$formatted[1]
		);
		$this->assertMatchesRegularExpression(
			'/^mediawiki\.ConfirmEdit\.hcaptcha_siteverify_call:[\d.]+\|ms\|#status:failed,is_retry:true$/',
			$formatted[2]
		);
		$this->assertSame(
			'mediawiki.ConfirmEdit.hcaptcha_siteverify_exhausted_total:1|c',
			$formatted[3]
		);
	}

	public function testPassCaptchaHttpRetrySucceeds() {
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );
		$this->overrideConfigValue( 'HCaptchaSiteKey', 'test-sitekey' );

		// First request fails, second succeeds
		$failingRequest = $this->createMock( MWHttpRequest::class );
		$failingRequest->method( 'execute' )
			->willReturn( Status::wrap( StatusValue::newFatal( 'http-error-500' ) ) );

		$successfulRequest = $this->createMock( MWHttpRequest::class );
		$successfulRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$successfulRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [
				'success' => true,
				'sitekey' => 'test-sitekey',
			] ) );

		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequestFactory->expects( $this->exactly( 2 ) )
			->method( 'create' )
			->willReturnOnConsecutiveCalls( $failingRequest, $successfulRequest );
		$this->setService( 'HttpRequestFactory', $mockHttpRequestFactory );

		// Verify warning for initial failure and info for successful retry.
		// There are two info() calls: one for the retry success, one for the
		// captcha solution attempt.
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'SiteVerify API request failed on attempt {attempt} of {maxAttempts}, retrying. Error: {error}',
				$this->anything()
			);
		$infoMessages = [];
		$mockLogger->method( 'info' )
			->willReturnCallback( static function ( $message ) use ( &$infoMessages ) {
				$infoMessages[] = $message;
			} );
		$this->setLogger( 'captcha', $mockLogger );

		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( 'test' );
		$this->assertTrue( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );

		// Error count should NOT have been incremented since retry succeeded
		$this->assertNull( $hCaptcha->getError() );
		$this->assertContains(
			'SiteVerify API call succeeded on attempt {attempt} of {maxAttempts} after initial failure',
			$infoMessages
		);
	}

	public function testPassCaptchaHttpSecondRetrySucceeds() {
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );
		$this->overrideConfigValue( 'HCaptchaSiteKey', 'test-sitekey' );

		// First two requests fail, third (second retry) succeeds.
		$failingRequest = $this->createMock( MWHttpRequest::class );
		$failingRequest->method( 'execute' )
			->willReturn( Status::wrap( StatusValue::newFatal( 'http-error-500' ) ) );

		$successfulRequest = $this->createMock( MWHttpRequest::class );
		$successfulRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$successfulRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [
				'success' => true,
				'sitekey' => 'test-sitekey',
			] ) );

		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequestFactory->expects( $this->exactly( 3 ) )
			->method( 'create' )
			->willReturnOnConsecutiveCalls( $failingRequest, $failingRequest, $successfulRequest );
		$this->setService( 'HttpRequestFactory', $mockHttpRequestFactory );

		// Capture warning context so we can verify the attempt numbers.
		$warningContexts = [];
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->method( 'warning' )
			->willReturnCallback( static function ( $message, $context ) use ( &$warningContexts ) {
				$warningContexts[] = [ 'message' => $message, 'context' => $context ];
			} );
		$infoMessages = [];
		$mockLogger->method( 'info' )
			->willReturnCallback( static function ( $message, $context = [] ) use ( &$infoMessages ) {
				$infoMessages[] = [ 'message' => $message, 'context' => $context ];
			} );
		$this->setLogger( 'captcha', $mockLogger );

		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( 'test' );
		$this->assertTrue( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );

		// Error count should NOT have been incremented since retry succeeded
		$this->assertNull( $hCaptcha->getError() );

		// Verify both retry warnings fired, with correct attempt numbers.
		$this->assertCount( 2, $warningContexts );
		$this->assertSame( 1, $warningContexts[0]['context']['attempt'] );
		$this->assertSame( 3, $warningContexts[0]['context']['maxAttempts'] );
		$this->assertSame( 2, $warningContexts[1]['context']['attempt'] );
		$this->assertSame( 3, $warningContexts[1]['context']['maxAttempts'] );

		// Verify the success info log was emitted with attempt=3.
		$successLogs = array_values( array_filter(
			$infoMessages,
			static fn ( $entry ) => $entry['message']
				=== 'SiteVerify API call succeeded on attempt {attempt} of {maxAttempts} after initial failure'
		) );
		$this->assertCount( 1, $successLogs );
		$this->assertSame( 3, $successLogs[0]['context']['attempt'] );
		$this->assertSame( 3, $successLogs[0]['context']['maxAttempts'] );
	}

	public function testPassCaptchaSkipsSiteVerifyWhenTokenMissing() {
		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequestFactory->expects( $this->never() )
			->method( 'create' );
		$this->setService( 'HttpRequestFactory', $mockHttpRequestFactory );

		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'No hCaptcha token present in the request; skipping siteverify call.',
				$this->callback( function ( $actualData ) {
					$expectedSubset = [
						'user' => '1.2.3.4',
						'error' => 'missing-token',
						'captcha_type' => 'hcaptcha',
						'captcha_action' => 'edit',
						'captcha_trigger' => "edit trigger by '~2025-198' at [[Test]]",
						'clientIp' => '127.0.0.1',
						'ua' => false,
						'user_exists_locally' => false,
					];
					$this->assertArrayContains( $expectedSubset, $actualData );
					return true;
				} )
			);
		$this->setLogger( 'captcha', $mockLogger );

		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( "edit trigger by '~2025-198' at [[Test]]" );

		$this->assertFalse( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest(),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );
		$this->assertSame( 'missing-token', $hCaptcha->getError() );
	}

	public function testPassCaptchaForInvalidJsonResponse() {
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );

		// Mock that the site-verify URL call will cause invalid JSON to be returned.
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 200 );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( 'invalidjson:{' );
		$this->installMockHttp( $mwHttpRequest );

		// Verify that a log is created to indicate the error
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'error' )
			->with( 'Unable to validate response. Error: {error}',
				$this->callback( function ( $actualData ) {
					$expectedSubset = [
						'error' => 'json',
						'user' => '1.2.3.4',
						'captcha_type' => 'hcaptcha',
						'captcha_action' => 'edit',
						'captcha_trigger' => "edit trigger by '~2025-198' at [[Test]]",
						'hcaptcha_token' => 'abcdef',
						'clientIp' => '127.0.0.1',
						'ua' => false,
						'user_exists_locally' => false,
					];
					$this->assertArrayContains( $expectedSubset, $actualData );
					return true;
				} ) );
		$this->setLogger( 'captcha', $mockLogger );

		// Attempt to pass the captcha, but expect that this fails
		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( "edit trigger by '~2025-198' at [[Test]]" );
		$this->assertFalse( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );
		$this->assertSame( 'json', $hCaptcha->getError() );
	}

	public function testPassCaptchaForErrorsInJsonResponse() {
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );

		// Mock that the site-verify URL call will cause JSON with error codes from the hCaptcha API
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 200 );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [ 'error-codes' => [ 'testingabc', 'test' ] ] ) );
		$this->installMockHttp( $mwHttpRequest );

		// Verify that a log is created to indicate the error
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'error' )
			->with( 'Unable to validate response. Error: {error}',
				$this->callback( function ( $actualData ) {
					$expectedSubset = [
						'error' => 'testingabc,test',
						'user' => '1.2.3.4',
						'captcha_type' => 'hcaptcha',
						'captcha_action' => 'edit',
						'captcha_trigger' => "edit trigger by '~2025-198' at [[Test]]",
						'hcaptcha_token' => 'abcdef',
						'clientIp' => '127.0.0.1',
						'ua' => false,
						'user_exists_locally' => false,
					];
					$this->assertArrayContains( $expectedSubset, $actualData );
					return true;
				} ) );
		$this->setLogger( 'captcha', $mockLogger );

		// Attempt to pass the captcha, but expect that this fails
		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( "edit trigger by '~2025-198' at [[Test]]" );
		$this->assertFalse( $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		) );
		$this->assertSame( 'hcaptcha-api', $hCaptcha->getError() );
	}

	/** @dataProvider providePassCaptcha */
	public function testPassCaptcha(
		bool $captchaPassedSuccessfully,
		bool $developerMode,
		bool $useRiskScore,
		bool $sendRemoteIP,
		array $mockApiResponse
	) {
		$this->overrideConfigValues( [
			'HCaptchaSecretKey' => 'secretkey',
			'HCaptchaSiteKey' => 'test-sitekey',
			'HCaptchaDeveloperMode' => $developerMode,
			'HCaptchaUseRiskScore' => $useRiskScore,
			'HCaptchaSendRemoteIP' => $sendRemoteIP,
		] );
		// Set a default IP for the web request, in order to be able to test
		// $sendRemoteIP later on
		$request = RequestContext::getMain()->getRequest();
		$testIP = '1.2.3.4';
		$request->setIP( $testIP );
		RequestContext::getMain()->setRequest( $request );
		ConvertibleTimestamp::setFakeTime( '2011-01-01T09:00:00Z' );

		// Mock the site-verify URL call to respond with a successful response
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 200 );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( $mockApiResponse ) );

		// Mock HttpRequestFactory directly so that we can check the URL and options are as expected.
		$expectedPostData = [ 'response' => 'abcdef', 'secret' => 'secretkey' ];
		$expectedPostData['remoteip'] = $sendRemoteIP ? $testIP : '127.0.0.1';

		$mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$mockHttpRequestFactory->method( 'create' )
			->willReturnCallback( function ( $url, $options ) use ( $mwHttpRequest, $expectedPostData ) {
				$this->assertSame( 'https://api.hcaptcha.com/siteverify', $url );
				$this->assertArrayEquals(
					[ 'method' => 'POST', 'postData' => $expectedPostData, 'timeout' => 1 ],
					$options,
					false,
					true
				);
				return $mwHttpRequest;
			} );
		$this->setService( 'HttpRequestFactory', $mockHttpRequestFactory );

		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );

		// Expect that an info log is created to indicate that the captcha either was solved or was not solved.
		$expectedLogContext = [
			'event' => 'captcha.solve',
			'user' => '1.2.3.4',
			'hcaptcha_success' => $mockApiResponse['success'],
			'captcha_type' => 'hcaptcha',
			'success_message' => $mockApiResponse['success'] ? 'Successful' : 'Failed',
			'captcha_action' => 'edit',
			'captcha_trigger' => "edit trigger by '~2025-198' at [[Test]]",
			'hcaptcha_token' => 'abcdef',
			'hcaptcha_response_sitekey' => 'test-sitekey',
			'clientIp' => '1.2.3.4',
			'ua' => false,
			'user_exists_locally' => false,
		];

		if ( $developerMode ) {
			$expectedLogContext += [
				'hcaptcha_score' => $mockApiResponse['score'] ?? null,
				'hcaptcha_score_reason' => $mockApiResponse['score_reason'] ?? null,
				'hcaptcha_blob' => $mockApiResponse,
			];
		}

		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with( '{success_message} captcha solution attempt for {user}',
				$this->callback( function ( $actualData ) use ( $expectedLogContext ) {
					$this->assertArrayContains( $expectedLogContext, $actualData );
					return true;
				} ) );
		$this->setLogger( 'captcha', $mockLogger );

		// Attempt to pass the captcha and expect that it passes
		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( 'edit' );
		$hCaptcha->setTrigger( "edit trigger by '~2025-198' at [[Test]]" );

		$this->assertSame(
			$captchaPassedSuccessfully,
			$hCaptcha->passCaptchaFromRequest(
				new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
				$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
			),
			'passCaptchaFromRequest() should be ' . ( $captchaPassedSuccessfully ? 'true' : 'false' )
		);

		if ( $useRiskScore || $developerMode ) {
			$this->assertSame(
				$mockApiResponse['score'] ?? null,
				$hCaptcha->retrieveSessionScore( 'hCaptcha-score' ),
				'hCaptcha-score should be ' . ( $mockApiResponse['score'] ?? '(null)' )
			);
		} else {
			$this->assertNull( $hCaptcha->retrieveSessionScore( 'hCaptcha-score' ) );
		}
		$this->assertNull( $hCaptcha->getError() );

		$this->assertSame(
			[ 'mediawiki.ConfirmEdit.hcaptcha_siteverify_call:1|ms|#status:ok,is_retry:false' ],
			$statsHelper->consumeAllFormatted()
		);
	}

	public static function providePassCaptcha(): array {
		return [
			'Passes hCaptcha check, in developer mode' => [
				true, true, false, false,
				[ 'success' => true, 'score' => 123, 'score_reason' => 'test', 'sitekey' => 'test-sitekey' ],
			],
			'Passes hCaptcha check, not in developer mode, sending remote IP' => [
				true, false, false, true,
				[ 'success' => true, 'score' => 123, 'score_reason' => 'test', 'sitekey' => 'test-sitekey' ],
			],
			'Passes hCaptcha check, not in developer mode, using risk score' => [
				true, false, true, false,
				[ 'success' => true, 'score' => 123, 'score_reason' => 'test', 'sitekey' => 'test-sitekey' ],
			],
			'Fails hCaptcha check, in developer mode' => [
				false, true, false, false,
				[ 'success' => false, 'score' => 123, 'score_reason' => 'test', 'sitekey' => 'test-sitekey' ],
			],
			'Fails hCaptcha check, not in developer mode' => [
				false, false, false, false,
				[ 'success' => false, 'score' => 123, 'score_reason' => 'test', 'sitekey' => 'test-sitekey' ],
			],
			'Fails hCaptcha check, in developer mode, no score included in response' => [
				false, true, false, false, [ 'success' => false, 'sitekey' => 'test-sitekey' ],
			],
			'Fails hCaptcha check, not in developer mode, no score included in response' => [
				false, false, false, false, [ 'success' => false, 'sitekey' => 'test-sitekey' ],
			],
		];
	}

	public function testAddCSPSources() {
		$this->overrideConfigValues( [
			'HCaptchaCSPRules' => [ '*.abc.com' ],
			MainConfigNames::CSPHeader => true,
		] );

		$mockContentSecurityPolicy = $this->createMock( ContentSecurityPolicy::class );
		$expectedMethodsToCallForEachUrl = [ 'addDefaultSrc', 'addScriptSrc', 'addStyleSrc' ];
		foreach ( $expectedMethodsToCallForEachUrl as $method ) {
			$mockContentSecurityPolicy->expects( $this->once() )
				->method( $method )
				->with( '*.abc.com' );
		}

		HCaptcha::addCSPSources( $mockContentSecurityPolicy );
	}

	public function testCreateAuthenticationRequest() {
		$hCaptcha = new HCaptcha();
		$this->assertInstanceOf( HCaptchaAuthenticationRequest::class, $hCaptcha->createAuthenticationRequest() );
	}

	public function testAddCaptchaAPIWhenImageExists() {
		$this->overrideConfigValue( 'HCaptchaSiteKey', 'abcdef' );

		$hCaptcha = TestingAccessWrapper::newFromObject( new HCaptcha() );
		$actualCaptchaInformation = [];

		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $hCaptcha->object );
		$methodReflection = $classReflection->getMethod( 'addCaptchaAPI' );
		$methodReflection->invokeArgs( $hCaptcha->object, [ &$actualCaptchaInformation ] );

		$this->assertArrayEquals(
			[
				'captcha' => [
					'type' => 'hcaptcha', 'mime' => 'application/javascript', 'key' => 'abcdef', 'error' => null,
				],
			],
			$actualCaptchaInformation,
			false, true
		);
	}

	public function testOnAuthChangeFormFieldsWhenCaptchaNotRequested() {
		$hCaptcha = new HCaptcha();

		// Verify that nothing happens if the CaptchaAuthenticationRequest is not included in the list of $requests.
		$formDescriptor = [];
		$hCaptcha->onAuthChangeFormFields( [], [], $formDescriptor, '' );
		$this->assertSame( [], $formDescriptor );
	}

	public function testOnAuthChangeFormFieldsWhenCaptchaRequested() {
		$hCaptcha = new HCaptcha();

		$formDescriptor = [ 'captchaWord' => [ 'id' => 'test' ] ];
		$hCaptcha->onAuthChangeFormFields(
			[ $hCaptcha->createAuthenticationRequest() ], [], $formDescriptor, ''
		);
		$this->assertArrayEquals(
			[ 'captchaWord' => [ 'id' => 'test', 'class' => HTMLHCaptchaField::class, 'error' => null ] ],
			$formDescriptor,
			false, true
		);
	}

	public function testScoreIsCached() {
		$userName = 'TestUser';
		$key = 'score';
		$expectedScore = 123;
		$hCaptcha = new HCaptcha();

		$hCaptcha->storeSessionScore( $key, $expectedScore, $userName );
		SessionManager::getGlobalSession()->set( $key, null );

		$this->assertSame(
			$expectedScore,
			$hCaptcha->retrieveSessionScore( $key, $userName )
		);
	}

	/** @dataProvider provideShouldCheck */
	public function testShouldCheck(
		?bool $captchaResult,
		bool $forceShowCaptchaFlagInRequest,
		bool $resultUsedForceCaptchaSiteKey,
		bool $expectedReturnValue
	): void {
		// Mock that all users should see a CAPTCHA, so that when the custom ::shouldCheck code returns
		// early with `false` we can test for that
		$this->clearHook( 'ConfirmEditCanUserSkipCaptcha' );
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', static function ( $action, $title, &$result ) {
			$result = true;
		} );

		$hCaptcha = TestingAccessWrapper::newFromObject( new HCaptcha() );
		$hCaptcha->result = $captchaResult;
		$hCaptcha->resultUsesForceShowCaptchaSiteKey = $resultUsedForceCaptchaSiteKey;

		$context = $this->createMock( RequestContext::class );
		if ( $forceShowCaptchaFlagInRequest ) {
			$request = new FauxRequest( [ 'wgConfirmEditForceShowCaptcha' => true ] );
		} else {
			$request = new FauxRequest( [] );
		}
		$context->method( 'getRequest' )->willReturn( $request );
		$context->method( 'getUser' )->willReturn( $this->createMock( User::class ) );

		$this->assertSame(
			$expectedReturnValue,
			$hCaptcha->shouldCheck( $this->createMock( WikiPage::class ), '', '', $context )
		);
	}

	public static function provideShouldCheck(): array {
		return [
			'Result is null' => [
				'captchaResult' => null,
				'forceShowCaptchaFlagInRequest' => false,
				'resultUsedForceCaptchaSiteKey' => false,
				'expectedReturnValue' => true,
			],
			'Result is false' => [
				'captchaResult' => false,
				'forceShowCaptchaFlagInRequest' => false,
				'resultUsedForceCaptchaSiteKey' => false,
				'expectedReturnValue' => true,
			],
			'Result is false and forceShowCaptcha flag set in request' => [
				'captchaResult' => false,
				'forceShowCaptchaFlagInRequest' => true,
				'resultUsedForceCaptchaSiteKey' => false,
				'expectedReturnValue' => false,
			],
			'Result is true' => [
				'captchaResult' => true,
				'forceShowCaptchaFlagInRequest' => false,
				'resultUsedForceCaptchaSiteKey' => false,
				'expectedReturnValue' => true,
			],
			'Result is true and forceShowCaptcha flag set in request' => [
				'captchaResult' => true,
				'forceShowCaptchaFlagInRequest' => true,
				'resultUsedForceCaptchaSiteKey' => true,
				'expectedReturnValue' => false,
			],
			'Result is true and forceShowCaptcha flag set in request, but result did not use force show sitekey' => [
				'captchaResult' => true,
				'forceShowCaptchaFlagInRequest' => true,
				'resultUsedForceCaptchaSiteKey' => false,
				'expectedReturnValue' => true,
			],
		];
	}

	public function testPassCaptchaInForceShowCaptchaMode(): void {
		$hCaptcha = TestingAccessWrapper::newFromObject( new HCaptcha() );
		$hCaptcha->result = true;
		$hCaptcha->setForceShowCaptcha( true );
		RequestContext::getMain()->getRequest()->unsetVal( 'wgConfirmEditForceShowCaptcha' );
		$this->assertFalse(
			$hCaptcha->passCaptcha( '', '', '' )
		);
	}

	/** @dataProvider provideGetSiteKeyForAction */
	public function testGetSiteKeyForAction(
		string $action,
		array $actionConfig,
		?string $globalSiteKey,
		bool $forceShow,
		string $expectedSiteKey
	): void {
		if ( $globalSiteKey !== null ) {
			$this->overrideConfigValue( 'HCaptchaSiteKey', $globalSiteKey );
		}

		$hCaptcha = new HCaptcha();
		$hCaptcha->setConfig( $actionConfig );
		if ( $forceShow ) {
			$hCaptcha->setForceShowCaptcha( true );
		}

		$actual = $hCaptcha->getSiteKeyForAction();
		$this->assertSame( $expectedSiteKey, $actual );
	}

	public static function provideGetSiteKeyForAction(): array {
		return [
			'Action config has site key' => [
				'edit',
				[ 'HCaptchaSiteKey' => 'action-key' ],
				'global-key',
				false,
				'action-key'
			],
			'Falls back to global when action config missing' => [
				'edit',
				[],
				'global-key',
				false,
				'global-key'
			],
			'Force show with always challenge key' => [
				'edit',
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAlwaysChallengeSiteKey' => 'challenge-key'
				],
				null,
				true,
				'challenge-key'
			],
			'Force show without always challenge key (fallback to normal)' => [
				'edit',
				[ 'HCaptchaSiteKey' => 'normal-key' ],
				null,
				true,
				'normal-key'
			],
			'Force show with fallback to global' => [
				'edit',
				[],
				'global-key',
				true,
				'global-key'
			],
			'Create action with action config' => [
				'create',
				[ 'HCaptchaSiteKey' => 'create-key' ],
				'global-key',
				false,
				'create-key'
			],
		];
	}

	/** @dataProvider providePassCaptchaSiteKeyValidation */
	public function testPassCaptchaSiteKeyValidation(
		string $action,
		array $actionConfig,
		?string $globalSiteKey,
		bool $forceShow,
		string $responseSiteKey,
		bool $shouldPass,
		?string $expectedError
	): void {
		$this->overrideConfigValue( 'HCaptchaSecretKey', 'secretkey' );
		if ( $globalSiteKey !== null ) {
			$this->overrideConfigValue( 'HCaptchaSiteKey', $globalSiteKey );
		}

		// Mock logger to capture error logs if validation fails - must be set before creating HCaptcha instance
		$mockLogger = $this->createMock( LoggerInterface::class );
		if ( !$shouldPass && $expectedError === 'sitekey-mismatch' ) {
			$mockLogger->expects( $this->once() )
				->method( 'error' )
				->with( 'Unable to validate response. Error: {error}', $this->anything() );
		}
		$this->setLogger( 'captcha', $mockLogger );

		// Mock the API response with the sitekey
		$mockApiResponse = [
			'success' => true,
			'sitekey' => $responseSiteKey
		];

		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 200 );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( $mockApiResponse ) );
		$this->installMockHttp( $mwHttpRequest );

		$hCaptcha = new HCaptcha();
		$hCaptcha->setConfig( $actionConfig );
		$hCaptcha->setAction( $action );
		$hCaptcha->setTrigger( "test trigger for $action" );
		if ( $forceShow ) {
			$hCaptcha->setForceShowCaptcha( true );
		}

		$result = $hCaptcha->passCaptchaFromRequest(
			new FauxRequest( [ 'h-captcha-response' => 'abcdef' ] ),
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		);

		$this->assertSame( $shouldPass, $result );
		if ( $expectedError ) {
			$this->assertSame( $expectedError, $hCaptcha->getError() );
		} else {
			$this->assertNull( $hCaptcha->getError() );
		}
	}

	public static function providePassCaptchaSiteKeyValidation(): array {
		return [
			'Site key matches - from action config' => [
				'edit',
				[ 'HCaptchaSiteKey' => 'test-key' ],
				null,
				false,
				'test-key',
				true,
				null
			],
			'Site key matches - from global fallback' => [
				'edit',
				[],
				'global-key',
				false,
				'global-key',
				true,
				null
			],
			'Site key mismatch - should fail' => [
				'edit',
				[ 'HCaptchaSiteKey' => 'correct-key' ],
				null,
				false,
				'wrong-key',
				false,
				'sitekey-mismatch'
			],
			'Force show - validates against challenge key' => [
				'edit',
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAlwaysChallengeSiteKey' => 'challenge-key'
				],
				null,
				true,
				'challenge-key',
				true,
				null
			],
			'Force show - wrong key (normal instead of challenge)' => [
				'edit',
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAlwaysChallengeSiteKey' => 'challenge-key'
				],
				null,
				true,
				'normal-key',
				false,
				'sitekey-mismatch'
			],
			'Force show - validates against global when challenge key not set' => [
				'edit',
				[],
				'global-key',
				true,
				'global-key',
				true,
				null
			],
			'Force show - allows additional keys when challenge key not set' => [
				'edit',
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key' ]
				],
				null,
				true,
				'additional-key',
				true,
				null
			],
		];
	}

	/** @dataProvider provideGetPrimarySiteKey */
	public function testGetPrimarySiteKey(
		array $actionConfig,
		?string $globalSiteKey,
		string $expectedSiteKey,
		bool $shouldThrowException = false
	): void {
		// Always override the config value - set to null if we want to test exception
		$this->overrideConfigValue( 'HCaptchaSiteKey', $globalSiteKey );

		$hCaptcha = TestingAccessWrapper::newFromObject( new HCaptcha() );
		$hCaptcha->setConfig( $actionConfig );

		if ( $shouldThrowException ) {
			$this->expectException( LogicException::class );
			$this->expectExceptionMessage( 'wgHCaptchaSiteKey is not set' );
		}

		$actual = $hCaptcha->getPrimarySiteKey();
		$this->assertSame( $expectedSiteKey, $actual );
	}

	public static function provideGetPrimarySiteKey(): array {
		return [
			'Action config has site key' => [
				[ 'HCaptchaSiteKey' => 'action-key' ],
				'global-key',
				'action-key'
			],
			'Falls back to global when action config missing' => [
				[],
				'global-key',
				'global-key'
			],
			'Action config has null, falls back to global' => [
				[ 'HCaptchaSiteKey' => null ],
				'global-key',
				'global-key'
			],
			'No config at all - throws exception' => [
				[],
				null,
				'',
				true
			],
		];
	}

	/** @dataProvider provideGetAllowedSiteKeysForCurrentAction */
	public function testGetAllowedSiteKeysForCurrentAction(
		array $actionConfig,
		?string $globalSiteKey,
		bool $forceShow,
		array $expectedSiteKeys
	): void {
		if ( $globalSiteKey !== null ) {
			$this->overrideConfigValue( 'HCaptchaSiteKey', $globalSiteKey );
		}

		$hCaptcha = TestingAccessWrapper::newFromObject( new HCaptcha() );
		$hCaptcha->setConfig( $actionConfig );
		if ( $forceShow ) {
			$hCaptcha->setForceShowCaptcha( true );
		}

		$actual = $hCaptcha->getAllowedSiteKeysForCurrentAction();
		// Sort arrays for comparison since order doesn't matter
		sort( $actual );
		sort( $expectedSiteKeys );
		$this->assertSame( $expectedSiteKeys, $actual );
	}

	public static function provideGetAllowedSiteKeysForCurrentAction(): array {
		return [
			'Normal mode - action config has site key only' => [
				[ 'HCaptchaSiteKey' => 'action-key' ],
				'global-key',
				false,
				[ 'action-key' ]
			],
			'Normal mode - action config has site key and additional keys' => [
				[
					'HCaptchaSiteKey' => 'primary-key',
					'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key-1', 'additional-key-2' ]
				],
				'global-key',
				false,
				[ 'primary-key', 'additional-key-1', 'additional-key-2' ]
			],
			'Normal mode - additional keys with duplicates and empty values' => [
				[
					'HCaptchaSiteKey' => 'primary-key',
					'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key', '', null, 'primary-key' ]
				],
				'global-key',
				false,
				[ 'primary-key', 'additional-key' ]
			],
			'Normal mode - no action config, falls back to global' => [
				[],
				'global-key',
				false,
				[ 'global-key' ]
			],
			'Normal mode - only additional keys (no primary), includes global as primary' => [
				[ 'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key' ] ],
				'global-key',
				false,
				[ 'global-key', 'additional-key' ]
			],
			'Normal mode - only empty additional keys, falls back to global' => [
				[ 'HCaptchaAdditionalValidSiteKeys' => [ '', null ] ],
				'global-key',
				false,
				[ 'global-key' ]
			],
			'Normal mode - has always challenge key' => [
				[ 'HCaptchaAdditionalValidSiteKeys' => [ 'challenge-key' ] ],
				'global-key',
				false,
				[ 'challenge-key', 'global-key' ]
			],
			'Force show mode - has always challenge key' => [
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAlwaysChallengeSiteKey' => 'challenge-key'
				],
				'global-key',
				true,
				[ 'challenge-key' ]
			],
			'Force show mode - no always challenge key, falls back to normal mode (primary only)' => [
				[ 'HCaptchaSiteKey' => 'normal-key' ],
				'global-key',
				true,
				[ 'normal-key' ]
			],
			'Force show mode - no always challenge key, falls back to normal mode with additional keys' => [
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key-1', 'additional-key-2' ]
				],
				'global-key',
				true,
				[ 'normal-key', 'additional-key-1', 'additional-key-2' ]
			],
			'Force show mode - no always challenge key, no action config, uses global' => [
				[],
				'global-key',
				true,
				[ 'global-key' ]
			],
			'Force show mode - with always challenge key, ignores additional keys' => [
				[
					'HCaptchaSiteKey' => 'normal-key',
					'HCaptchaAlwaysChallengeSiteKey' => 'challenge-key',
					'HCaptchaAdditionalValidSiteKeys' => [ 'additional-key-1', 'additional-key-2' ]
				],
				'global-key',
				true,
				[ 'challenge-key' ]
			],
		];
	}

	/**
	 * @dataProvider forceShowCaptchaAddsJsVarsDataProvider
	 */
	public function testForceShowCaptchaAddsJsVars(
		bool $expected,
		string $action
	): void {
		$hCaptcha = new HCaptcha();
		$hCaptcha->setAction( $action );
		$hCaptcha->setForceShowCaptcha( true );

		$key = 'wgHCaptchaTriggerFormSubmission';
		$output = RequestContext::getMain()->getOutput();
		$vars = $output->getJsConfigVars();

		if ( $expected ) {
			$this->assertArrayHasKey( $key, $vars );
			$this->assertTrue( $vars[$key] );
		} else {
			$this->assertArrayNotHasKey( $key, $vars );
		}
	}

	public static function forceShowCaptchaAddsJsVarsDataProvider(): array {
		return [
			'create action' => [
				'expected' => true,
				'action' => 'create',
			],
			'edit action' => [
				'expected' => true,
				'action' => 'edit',
			],
			'sendemail action' => [
				'expected' => false,
				'action' => 'sendemail',
			],
		];
	}

	public function testApiGetAllowedParamsDeclaresForceShowCaptcha(): void {
		$module = $this->createMock( ApiEditPage::class );
		$params = [];

		$hCaptcha = new HCaptcha();
		$hCaptcha->apiGetAllowedParams( $module, $params, 0 );

		$this->assertArrayHasKey( 'wgConfirmEditForceShowCaptcha', $params );
		$this->assertArrayHasKey( 'captchaword', $params );
		$this->assertArrayHasKey( 'captchaid', $params );
		$this->assertSame(
			'boolean',
			$params['wgConfirmEditForceShowCaptcha'][ParamValidator::PARAM_TYPE]
		);
	}

	public function testApiGetAllowedParamsDeclaresForceShowCaptchaForVisualEditor(): void {
		$module = $this->createMock( ApiBase::class );
		$module->expects( $this->atLeastOnce() )
			->method( 'getModuleName' )
			->willReturn( 'visualeditoredit' );
		$params = [];

		$hCaptcha = new HCaptcha();
		$hCaptcha->apiGetAllowedParams( $module, $params, 0 );

		$this->assertArrayHasKey( 'wgConfirmEditForceShowCaptcha', $params );
		$this->assertSame(
			'boolean',
			$params['wgConfirmEditForceShowCaptcha'][ParamValidator::PARAM_TYPE]
		);
	}

	public function testApiGetAllowedParamsDoesNothingForNonEditModule(): void {
		$module = $this->createMock( ApiBase::class );
		$params = [];

		$hCaptcha = new HCaptcha();
		$hCaptcha->apiGetAllowedParams( $module, $params, 0 );

		$this->assertSame( [], $params );
	}
}
