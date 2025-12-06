<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\SimpleCaptcha;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaCacheStore;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha
 */
class SimpleCaptchaTest extends MediaWikiIntegrationTestCase {
	private const TEST_CAPTCHA_INDEX = 127;

	/** @var ScopedCallback[] */
	private $hold = [];

	protected function setUp(): void {
		parent::setUp();

		// Clear any handlers of the ConfirmEditTriggersCaptcha hook for this test, as in CI their additional
		// checks may cause the tests to fail (such as those from IPReputation).
		$this->clearHook( 'ConfirmEditTriggersCaptcha' );
	}

	public function tearDown(): void {
		// Destroy any ScopedCallbacks being held
		$this->hold = [];
		parent::tearDown();
	}

	public function testGetName() {
		$this->assertEquals( 'SimpleCaptcha', ( new SimpleCaptcha )->getName() );
	}

	/**
	 * @dataProvider providePassCaptchaLimitedFromRequest
	 *
	 * @param string|null $captchaId
	 * @param string|null $captchaWord
	 * @param bool $passed
	 * @return void
	 */
	public function testPassCaptchaLimitedFromRequest(
		?string $captchaId,
		?string $captchaWord,
		bool $passed
	): void {
		$this->overrideConfigValue( 'CaptchaStorageClass', CaptchaCacheStore::class );
		CaptchaStore::unsetInstanceForTests();

		$params = [ 'index' => self::TEST_CAPTCHA_INDEX, 'question' => '5+3', 'answer' => 8 ];

		$captcha = new SimpleCaptcha();
		$captcha->storeCaptcha( $params );

		$request = new FauxRequest( array_filter( [
			'wpCaptchaId' => $captchaId,
			'wpCaptchaWord' => $captchaWord,
		] ) );

		$user = $this->createMock( User::class );
		$user->expects( $passed ? $this->once() : $this->exactly( 2 ) )
			->method( 'pingLimiter' )
			->willReturnMap( [
				[ 'badcaptcha', 0, false ],
				[ 'badcaptcha', 1, false ]
			] );

		$this->assertSame( $passed, $captcha->passCaptchaLimitedFromRequest( $request, $user ) );
	}

	public static function providePassCaptchaLimitedFromRequest(): iterable {
		yield 'new captcha session' => [ null, null, false ];
		yield 'missing captcha ID' => [ null, '8', false ];
		yield 'mismatched captcha ID' => [ '129', '8', false ];
		yield 'missing answer' => [ (string)self::TEST_CAPTCHA_INDEX, null, false ];
		yield 'wrong answer' => [ (string)self::TEST_CAPTCHA_INDEX, '7', false ];
		yield 'correct answer' => [ (string)self::TEST_CAPTCHA_INDEX, '8', true ];
	}

	/**
	 * @dataProvider provideSimpleTriggersCaptcha
	 */
	public function testTriggersCaptcha( $action, $expectedResult, $triggerValue ) {
		$captcha = new SimpleCaptcha();
		$this->overrideConfigValue( 'CaptchaTriggers', [
			$action => $triggerValue,
		] );
		$this->assertEquals( $expectedResult, $captcha->triggersCaptcha( $action ) );
	}

	public static function provideSimpleTriggersCaptcha() {
		$data = [];
		foreach ( CaptchaTriggers::CAPTCHA_TRIGGERS as $trigger ) {
			$data[] = [ $trigger, true, true ];
			$data[] = [ $trigger, false, false ];
			$data[] = [ $trigger, true, [
				'class' => 'SimpleCaptcha',
				'trigger' => true,
			] ];
			$data[] = [ $trigger, false, [
				'class' => 'SimpleCaptcha',
				'trigger' => false,
			] ];
			// When the trigger isn't defined, but the value is an array, default to false
			$data[] = [ $trigger, false, [
				'class' => 'SimpleCaptcha',
			] ];
		}
		return $data;
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testNamespaceTriggersOverwrite( bool $expected ) {
		$trigger = 'edit';
		$captcha = new SimpleCaptcha();
		$this->overrideConfigValues( [
			'CaptchaTriggers' => [
				$trigger => !$expected,
			],
			'CaptchaTriggersOnNamespace' => [
				0 => [
					$trigger => $expected,
				],
			],
		] );
		$title = Title::newFromText( 'Main' );
		$this->assertEquals( $expected, $captcha->triggersCaptcha( $trigger, $title ) );
	}

	private function setCaptchaTriggersAttribute( $trigger, $value ) {
		// Avoid clobbering captcha triggers registered by other extensions
		$this->overrideConfigValue( 'CaptchaTriggers', $GLOBALS['wgCaptchaTriggers'] );

		$this->hold[] = ExtensionRegistry::getInstance()->setAttributeForTest(
			'CaptchaTriggers', [ $trigger => $value ]
		);
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testCaptchaTriggersAttributeSetTrue( bool $value ) {
		$trigger = 'test';
		$this->setCaptchaTriggersAttribute( $trigger, $value );
		$captcha = new SimpleCaptcha();
		$this->assertEquals( $value, $captcha->triggersCaptcha( $trigger ) );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testCaptchaTriggersAttributeGetsOverwritten( bool $expected ) {
		$trigger = 'edit';
		$this->overrideConfigValue( 'CaptchaTriggers', [ $trigger => $expected ] );
		$this->setCaptchaTriggersAttribute( $trigger, !$expected );
		$captcha = new SimpleCaptcha();
		$this->assertEquals( $expected, $captcha->triggersCaptcha( $trigger ) );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testCanSkipCaptchaUserright( bool $userIsAllowed ) {
		$testObject = new SimpleCaptcha();
		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )->willReturn( $userIsAllowed );

		$actual = $testObject->canSkipCaptcha( $user );

		$this->assertEquals( $userIsAllowed, $actual );
	}

	public static function provideBooleans() {
		yield [ true ];
		yield [ false ];
	}

	/**
	 * @dataProvider provideCanSkipCaptchaBypassIPList
	 */
	public function testCanSkipCaptchaBypassIP( $requestIP, $list, $expected ) {
		$testObject = new SimpleCaptcha();
		$request = new FauxRequest();
		$request->setIP( $requestIP );

		$this->setRequest( $request );
		$this->overrideConfigValue( 'CaptchaBypassIPs', $list );

		$actual = $testObject->canSkipCaptcha( RequestContext::getMain()->getUser() );

		$this->assertEquals( $expected, $actual );
	}

	public static function provideCanSkipCaptchaBypassIPList() {
		return ( [
			[ '127.0.0.1', [ '127.0.0.1', '127.0.0.2' ], true ],
			[ '127.0.0.1', [], false ]
		]
		);
	}

	public function testCanSkipCaptchaSystemUser(): void {
		$testObject = new SimpleCaptcha();
		$user = $this->createConfiguredMock( User::class, [ 'isSystemUser' => true ] );

		$actual = $testObject->canSkipCaptcha( $user );

		$this->assertTrue( $actual );
	}

	public function testTriggersCaptchaReturnsEarlyIfCaptchaSolved() {
		$this->overrideConfigValue( 'CaptchaTriggers', [
			'edit' => true,
		] );
		$testObject = new SimpleCaptcha();
		/** @var SimpleCaptcha $wrapper */
		$wrapper = TestingAccessWrapper::newFromObject( $testObject );
		$wrapper->captchaSolved = true;
		$this->assertFalse( $testObject->triggersCaptcha( 'edit' ), 'CAPTCHA is not triggered if already solved' );
	}

	public function testForceShowCaptcha() {
		$this->overrideConfigValue( 'CaptchaTriggers', [
			'edit' => false,
		] );
		$testObject = new SimpleCaptcha();
		$this->assertFalse(
			$testObject->triggersCaptcha( 'edit' ), 'CAPTCHA is not triggered by edit action in this configuration'
		);
		$testObject->setForceShowCaptcha( true );
		$this->assertTrue( $testObject->triggersCaptcha( 'edit' ), 'Force showing a CAPTCHA if flag is set' );
	}

	/**
	 * @dataProvider provideCanSkipCaptchaHook
	 */
	public function testCanSkipCaptchaHook( $originalCanSkipCaptchaResult, $hookResult, $expected ) {
		$testObject = new SimpleCaptcha();

		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )->willReturn( $originalCanSkipCaptchaResult );

		$this->setTemporaryHook(
			'ConfirmEditCanUserSkipCaptcha',
			static function ( User $user, bool &$result ) use ( $hookResult ) {
				$result = $hookResult;
			}
		);

		$actual = $testObject->canSkipCaptcha( $user );

		$this->assertEquals( $expected, $actual );
	}

	public function provideCanSkipCaptchaHook() {
		return [
			[ false, false, false ],
			[ true, false, false ],
			[ false, true, true ],
			[ true, true, true ]
		];
	}
}
