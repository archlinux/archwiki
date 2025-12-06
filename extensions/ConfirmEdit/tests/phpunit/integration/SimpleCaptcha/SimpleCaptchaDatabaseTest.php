<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\SimpleCaptcha;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

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
		$this->overrideConfigValue( 'CaptchaWhitelistIP', [] );
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
}
