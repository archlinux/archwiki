<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\SimpleCaptcha;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\CacheKeyHelper;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha
 * @group Database
 */
class SimpleCaptchaDatabaseTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		// Clear any handlers of the ConfirmEditTriggersCaptcha hook for this test, as in CI their additional
		// checks may cause the tests to fail (such as those from IPReputation).
		$this->clearHook( 'ConfirmEditTriggersCaptcha' );
	}

	/**
	 * @dataProvider provideCanSkipCaptchaForMessageWhitelist
	 * @covers \MediaWiki\Extension\ConfirmEdit\Hooks::onPageSaveComplete
	 */
	public function testCanSkipCaptchaForMessageWhitelist( $ipWhitelistText, $requestIP, $expected ) {
		// We are testing the i18n message definition method, so clear the globals to avoid matching against them.
		$this->overrideConfigValue( 'CaptchaBypassIPs', [] );

		// Define override for the bypass i18n message
		$this->overrideConfigValue( MainConfigNames::UseDatabaseMessages, true );
		$this->editPage(
			Title::newFromText( 'captcha-ip-whitelist', NS_MEDIAWIKI ), $ipWhitelistText
		);

		$testObject = new SimpleCaptcha();
		$request = new FauxRequest();
		$request->setIP( $requestIP );
		$this->setRequest( $request );

		$this->assertEquals(
			$expected,
			$testObject->canSkipCaptcha( RequestContext::getMain()->getUser() )
		);
	}

	public static function provideCanSkipCaptchaForMessageWhitelist() {
		return [
			'captcha-ip-whitelist is disabled' => [ '-', '1.2.3.4', false ],
			'captcha-ip-whitelist contains invalid IPs' => [ "abc\nabcdef\n300.300.300.300", '1.2.3.4', false ],
			'captcha-ip-whitelist contains IPs that don\'t match' => [ "1.2.3.5\n2.3.4.5", '1.2.3.4', false ],
			'captcha-ip-whitelist contains an IP that matches' => [ "1.2.3.4\n2.3.4.5", '1.2.3.4', true ],
		];
	}

	public function testEditShowCaptchaWhenUserExemptedFromCaptchas() {
		$this->setTemporaryHook( 'ConfirmEditCanUserSkipCaptcha', static function ( $user, &$result ) {
			$result = true;
		} );
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', function () {
			$this->fail( 'No captcha should have been attempted to be added, as user can skip captchas.' );
		} );

		$article = Article::newFromTitle( Title::newFromText( 'Testing' ), RequestContext::getMain() );

		$testObject = new SimpleCaptcha();
		$testObject->editShowCaptcha( new EditPage( $article ) );

		// Test fails if ConfirmEditTriggersCaptcha hook is called, which occurs if the captcha is going to be
		// added and the skipcaptcha right is ignored.
		$this->expectNotToPerformAssertions();
	}

	public function testEditShowCaptchaWhenAddUrlSetButNotPosted() {
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', function ( $action, $title, &$result ) {
			if ( $action === 'addurl' ) {
				$this->fail( '"addurl" action was not expected, as it should be skipped for a GET request' );
			} else {
				$result = false;
			}
		} );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setOutput( $this->createNoOpMock( OutputPage::class ) );
		$context->setRequest( new FauxRequest( [], false ) );

		$article = Article::newFromTitle( Title::newFromText( 'Testing' ), $context );

		$testObject = new SimpleCaptcha();
		$testObject->editShowCaptcha( new EditPage( $article ) );
	}

	public function testEditShowCaptchaWhenTriggeredOnCreateButPageExists() {
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', static function ( $action, $title, &$result ) {
			$result = $action === 'create';
		} );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setOutput( $this->createNoOpMock( OutputPage::class ) );

		$article = Article::newFromTitle( $this->getExistingTestPage()->getTitle(), $context );

		$testObject = new SimpleCaptcha();
		$testObject->editShowCaptcha( new EditPage( $article ) );
	}

	public function testConfirmEditMergedWhenShouldCheckReturnsFalse() {
		$this->setTemporaryHook( 'ConfirmEditCanUserSkipCaptcha', static function ( $user, &$result ) {
			$result = true;
		} );

		$user = $this->getTestUser()->getUser();
		$title = $this->getNonexistingTestPage()->getTitle();
		$status = Status::newGood();
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$context->setTitle( $title );

		$simpleCaptcha = new SimpleCaptcha();
		$this->assertTrue( $simpleCaptcha->confirmEditMerged(
			$context, ContentHandler::makeContent( '', $title ), $status, '', $user, false
		) );
		$this->assertStatusGood( $status );
	}

	private function verifyConfirmEditMergedStatus(
		SimpleCaptcha $simpleCaptcha, Status $status, string $expectedStatusErrorMessageKey
	): void {
		$this->assertStatusError( $expectedStatusErrorMessageKey, $status );

		// Verify the statusData
		$this->assertArrayHasKey( 'captcha', $status->statusData );
		$this->assertSame( 'simple', $status->statusData['captcha']['type'] );
		$this->assertSame( 'text/plain', $status->statusData['captcha']['mime'] );

		$actualId = $status->statusData['captcha']['id'];
		$this->assertSame(
			$simpleCaptcha->retrieveCaptcha( $actualId )['question'],
			$status->statusData['captcha']['question']
		);
	}

	/** @dataProvider provideConfirmEditMergedForEditWhenCaptchaFieldMissing */
	public function testConfirmEditMergedForEditWhenCaptchaFieldMissing(
		bool $forceShowCaptchaSet, string $expectedStatusErrorMessageKey
	) {
		$this->clearHook( 'ConfirmEditCanUserSkipCaptcha' );
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', static function ( $action, $title, &$result ) {
			$result = true;
		} );

		// Set up the request and context such that no captcha word is provided,
		// so that the captcha check will fail
		$user = $this->getTestUser()->getUser();
		$title = $this->getNonexistingTestPage()->getTitle();
		$status = Status::newGood();
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$context->setTitle( $title );

		$simpleCaptcha = new SimpleCaptcha();
		$simpleCaptcha->setForceShowCaptcha( $forceShowCaptchaSet );

		$this->assertFalse( $simpleCaptcha->confirmEditMerged(
			$context, ContentHandler::makeContent( '', $title ), $status, '', $user, false
		) );
		$this->verifyConfirmEditMergedStatus( $simpleCaptcha, $status, $expectedStatusErrorMessageKey );
		$this->assertArrayEquals(
			[ CacheKeyHelper::getKeyForPage( $title ) => true ],
			$simpleCaptcha->getActivatedCaptchas(),
			false, true
		);
	}

	public static function provideConfirmEditMergedForEditWhenCaptchaFieldMissing(): array {
		return [
			'::shouldForceShowCaptcha returns false' => [ false, 'captcha-edit-fail' ],
			'::shouldForceShowCaptcha returns true' => [ true, 'captcha-edit' ],
		];
	}

	/** @dataProvider provideConfirmEditMergedForEditWhenCaptchaFieldPresentButIncorrect */
	public function testConfirmEditMergedForEditWhenCaptchaFieldPresentButIncorrect( bool $forceShowCaptchaSet ) {
		$this->clearHook( 'ConfirmEditCanUserSkipCaptcha' );
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', static function ( $action, $title, &$result ) {
			$result = true;
		} );

		// Set up the request and context such that a captcha word is present
		$user = $this->getTestUser()->getUser();
		$title = $this->getNonexistingTestPage()->getTitle();
		$status = Status::newGood();
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->getRequest()->setVal( 'wpCaptchaWord', 'test' );

		$simpleCaptcha = new SimpleCaptcha();
		$simpleCaptcha->setForceShowCaptcha( $forceShowCaptchaSet );

		$this->assertFalse( $simpleCaptcha->confirmEditMerged(
			$context, ContentHandler::makeContent( '', $title ), $status, '', $user, false
		) );
		$this->verifyConfirmEditMergedStatus( $simpleCaptcha, $status, 'captcha-edit-fail' );
		$this->assertArrayEquals(
			[ CacheKeyHelper::getKeyForPage( $title ) => true ],
			$simpleCaptcha->getActivatedCaptchas(),
			false, true
		);
	}

	public static function provideConfirmEditMergedForEditWhenCaptchaFieldPresentButIncorrect(): array {
		return [
			'::shouldForceShowCaptcha returns false' => [ false ],
			'::shouldForceShowCaptcha returns true' => [ true ],
		];
	}

	/**
	 * Test trigger evaluation order and fallback behavior.
	 * Verifies that 'addurl' is evaluated before 'edit' and 'create', and that
	 * 'edit'/'create' work as fallback when 'addurl' doesn't trigger.
	 *
	 * @dataProvider provideShouldCheckTriggerEvaluationOrder
	 */
	public function testShouldCheckTriggerEvaluationOrder(
		array $captchaTriggers,
		string $contentText,
		bool $expectedResult,
		string $expectedAction,
		array $expectedTriggerMessages,
		string $description
	) {
		$this->overrideConfigValue( 'CaptchaTriggers', $captchaTriggers );

		$user = $this->getTestUser()->getUser();
		$title = $this->getExistingTestPage()->getTitle();
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		$content = ContentHandler::makeContent( $contentText, $title );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [], true ) );

		$simpleCaptcha = new SimpleCaptcha();
		$result = $simpleCaptcha->shouldCheck( $page, $content, '', $context );

		$expectedBool = $expectedResult ? 'true' : 'false';
		$this->assertSame(
			$expectedResult,
			$result,
			"shouldCheck should return $expectedBool: $description"
		);

		if ( $expectedResult ) {
			$wrapper = TestingAccessWrapper::newFromObject( $simpleCaptcha );
			$actionMsg = "Action should be $expectedAction: $description";
			$this->assertSame( $expectedAction, $wrapper->action, $actionMsg );

			if ( $expectedAction === 'addurl' ) {
				// Verify that the message logged contains a given set of parts.
				// This is done this way (instead of providing the expected full
				// message directly) because names for users and pages used in
				// tests are not known in advance, and because some messages are
				// too long to be included in the data provider verbatim.
				foreach ( $expectedTriggerMessages as $expectedTriggerMessage ) {
					$this->assertStringContainsString(
						$expectedTriggerMessage,
						$wrapper->trigger
					);
				}
			}

			// (T411168) Ensure the message size is below 4.25 kB, given that
			// the list of URLs should be trimmed below 4096 bytes and the fixed
			// pats of the message are around 130 bytes.
			//
			// This length restriction is required to prevent errors when
			// logging messages caused by adding hundreds of URLs to a page.
			$this->assertLessThan( 4250, strlen( $wrapper->trigger ) );
		}
	}

	public static function provideShouldCheckTriggerEvaluationOrder(): array {
		return [
			'addurl evaluated before edit/create when all enabled' => [
				'captchaTriggers' => [
					'edit' => true,
					'create' => true,
					'addurl' => true,
				],
				'contentText' => 'Some text with a new URL: https://example.com/new-link',
				'expectedResult' => true,
				'expectedAction' => 'addurl',
				'expectedTriggerMessages' => [
					'URL trigger by ',
					'(1 URLs)',
					'https://example.com/new-link'
				],
				'description' => 'All triggers enabled, addurl should take precedence',
			],
			'addurl works in 100% passive mode' => [
				'captchaTriggers' => [
					'edit' => false,
					'create' => false,
					'addurl' => true,
				],
				'contentText' => 'Some text with a new URL: https://example.com/new-link',
				'expectedResult' => true,
				'expectedAction' => 'addurl',
				'expectedTriggerMessages' => [
					'URL trigger by ',
					'(1 URLs)',
					'https://example.com/new-link'
				],
				'description' => '100% passive mode: edit/create disabled, addurl enabled',
			],
			'addurl trims the list of URLs included in the trigger message' => [
				'captchaTriggers' => [
					'edit' => true,
					'create' => true,
					'addurl' => true,
				],
				'contentText' =>
					'Some text with a number of different URLs: ' .
					implode( ' ', array_map(
						static fn ( int $i ) => "https://example.com/new-link-{$i}",
						range( 1, 130 )
					) ),
				'expectedResult' => true,
				'expectedAction' => 'addurl',
				'expectedTriggerMessages' => [
					// T411168 Includes the first links, separated by commas...
					'https://example.com/new-link-1, ',
					'https://example.com/new-link-2, ',
					'https://example.com/new-link-3, ',
					// ...and up to the 123, which is the last one before
					// exceeding the allowed size. After that, three dots are
					// added to indicate that there were more URLs, but have
					// been omitted from the message
					'https://example.com/new-link-123, ...',
				],
				'description' => 'All triggers enabled, addurl should take precedence',
			],
			'edit works as fallback when addurl does not trigger' => [
				'captchaTriggers' => [
					'edit' => true,
					'create' => false,
					'addurl' => true,
				],
				'contentText' => 'Some text without URLs',
				'expectedResult' => true,
				'expectedAction' => 'edit',
				'expectedTriggerMessages' => [
					// unused in this scenario since expectedAction is not addurl
				],
				'description' => 'When addurl does not trigger, edit should work as fallback',
			],
		];
	}

	/**
	 * Test that triggers respect performance guards (wasPosted() and !$isEmpty).
	 * This documents that addurl checks wasPosted() guard (inside its block),
	 * while edit/create are evaluated regardless of request method.
	 *
	 * @dataProvider provideShouldCheckRespectsPerformanceGuards
	 */
	public function testShouldCheckRespectsPerformanceGuards(
		array $captchaTriggers,
		bool $isPostRequest,
		string $contentText,
		bool $expectedResult,
		string $description
	) {
		$this->overrideConfigValue( 'CaptchaTriggers', $captchaTriggers );

		$user = $this->getTestUser()->getUser();
		$title = $this->getExistingTestPage()->getTitle();
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		$content = ContentHandler::makeContent( $contentText, $title );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [], $isPostRequest ) );

		$simpleCaptcha = new SimpleCaptcha();
		$result = $simpleCaptcha->shouldCheck( $page, $content, '', $context );

		$this->assertSame( $expectedResult, $result, $description );
	}

	public static function provideShouldCheckRespectsPerformanceGuards(): array {
		return [
			'edit/create trigger on GET request' => [
				'captchaTriggers' => [
					'edit' => true,
					'create' => true,
					'addurl' => false,
				],
				'isPostRequest' => false,
				'contentText' => 'Some text',
				'expectedResult' => true,
				'description' => 'shouldCheck should return true for GET request with edit/create enabled',
			],
			'addurl does not trigger on empty content' => [
				'captchaTriggers' => [
					'edit' => false,
					'create' => false,
					'addurl' => true,
				],
				'isPostRequest' => true,
				'contentText' => '',
				'expectedResult' => false,
				'description' => 'shouldCheck should return false POST request with empty content and addurl enabled',
			],
		];
	}

	/**
	 * Test that setConfig() is called when addurl triggers with a config in CaptchaTriggers.
	 *
	 * @dataProvider provideShouldCheckSetConfigForAddUrl
	 */
	public function testShouldCheckSetConfigForAddUrl(
		array $captchaTriggers,
		string $contentText,
		array $expectedConfig,
		string $description
	) {
		$this->overrideConfigValue( 'CaptchaTriggers', $captchaTriggers );

		$user = $this->getTestUser()->getUser();
		$title = $this->getExistingTestPage()->getTitle();
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		$content = ContentHandler::makeContent( $contentText, $title );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [], true ) );

		$simpleCaptcha = new SimpleCaptcha();
		$result = $simpleCaptcha->shouldCheck( $page, $content, '', $context );

		$this->assertTrue( $result, "shouldCheck should return true: $description" );

		$wrapper = TestingAccessWrapper::newFromObject( $simpleCaptcha );
		$this->assertSame( 'addurl', $wrapper->action, "Action should be addurl: $description" );
		$this->assertSame( $expectedConfig, $simpleCaptcha->getConfig(), "Config should match: $description" );
	}

	public static function provideShouldCheckSetConfigForAddUrl(): array {
		return [
			'addurl sets config when provided in CaptchaTriggers' => [
				'captchaTriggers' => [
					'edit' => false,
					'create' => false,
					'addurl' => [
						'trigger' => true,
						'config' => [
							'sitekey' => 'test-sitekey',
							'secret' => 'test-secret',
						],
					],
				],
				'contentText' => 'Some text with a new URL: https://example.com/new-link',
				'expectedConfig' => [
					'sitekey' => 'test-sitekey',
					'secret' => 'test-secret',
				],
				'description' => 'addurl should set config from CaptchaTriggers when triggering',
			],
			'addurl sets empty config when not provided in CaptchaTriggers' => [
				'captchaTriggers' => [
					'edit' => false,
					'create' => false,
					'addurl' => true,
				],
				'contentText' => 'Some text with a new URL: https://example.com/new-link',
				'expectedConfig' => [],
				'description' => 'addurl should set empty config when config not provided in CaptchaTriggers',
			],
		];
	}
}
