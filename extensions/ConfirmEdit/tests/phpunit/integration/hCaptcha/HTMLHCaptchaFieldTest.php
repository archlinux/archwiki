<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\hCaptcha;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HTMLHCaptchaField;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\hCaptcha\HTMLHCaptchaField
 * @covers \MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput
 * @group Database
 */
class HTMLHCaptchaFieldTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideOptions
	 */
	public function testRender(
		array $configOverrides,
		array $params,
		string $expectedHtml
	): void {
		$this->overrideConfigValues( $configOverrides );

		$defaultSrcs = [];
		$scriptSrcs = [];
		$styleSrcs = [];
		$modules = [];
		$jsConfigVars = [];

		$csp = $this->createMock( ContentSecurityPolicy::class );
		$csp->method( 'addDefaultSrc' )
			->willReturnCallback( static function ( $src ) use ( &$defaultSrcs ): void {
				$defaultSrcs[] = $src;
			} );
		$csp->method( 'addScriptSrc' )
			->willReturnCallback( static function ( $src ) use ( &$scriptSrcs ): void {
				$scriptSrcs[] = $src;
			} );
		$csp->method( 'addStyleSrc' )
			->willReturnCallback( static function ( $src ) use ( &$styleSrcs ): void {
				$styleSrcs[] = $src;
			} );

		$shouldSecureEnclaveModeBeEnabled = $configOverrides['HCaptchaEnterprise'] &&
			$configOverrides['HCaptchaSecureEnclave'];

		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )
			->willReturn( $this->createMock( Title::class ) );
		$output->method( 'getCSP' )
			->willReturn( $csp );
		$output->expects( $shouldSecureEnclaveModeBeEnabled ? $this->never() : $this->once() )
			->method( 'addHeadItem' )
			->with(
				'h-captcha',
				"<script src=\"{$configOverrides['HCaptchaApiUrl']}\" async=\"\" defer=\"\"></script>"
			);
		$output->method( 'msg' )
			->willReturnCallback( static fn ( $key ) => wfMessage( $key ) );
		$output->method( 'addModules' )
			->willReturnCallback( static function ( $module ) use ( &$modules ): void {
				$modules[] = $module;
			} );
		$output->method( 'addJsConfigVars' )
			->willReturnCallback( static function ( $key, $value ) use ( &$jsConfigVars ): void {
				if ( is_array( $key ) ) {
					$jsConfigVars = array_merge( $jsConfigVars, $key );
				} else {
					$jsConfigVars[$key] = $value;
				}
			} );

		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );
		$context->setOutput( $output );

		$form = HTMLForm::factory( 'ooui', [], $context );
		$field = new HTMLHCaptchaField( $params + [ 'parent' => $form, 'name' => 'ignored' ] );

		$this->assertSame( 'h-captcha-response', $field->getName() );
		$this->assertSame( $expectedHtml, $field->getInputHTML( null ) );

		$this->assertSame( $configOverrides['HCaptchaCSPRules'], $defaultSrcs );
		$this->assertSame( $configOverrides['HCaptchaCSPRules'], $styleSrcs );
		$this->assertSame( $configOverrides['HCaptchaCSPRules'], $scriptSrcs );

		$this->assertSame( [ 'ext.confirmEdit.hCaptcha' ], $modules );
	}

	public static function provideOptions(): iterable {
		$testApiUrl = 'https://hcaptcha.example.com/api';
		$testSiteKey = 'foo';
		$testCspRules = [
			'https://hcaptcha.example.com',
			'https://hcaptcha-2.example.com'
		];

		yield 'active mode, no prior error' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => false,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => false,
				'HCaptchaSecureEnclave' => false,
			],
			[],
			'<div id="h-captcha" class="h-captcha" data-sitekey="' . $testSiteKey . '"></div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
		];

		yield 'invisible mode, no prior error' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => true,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => false,
				'HCaptchaSecureEnclave' => false,
			],
			[],
			'<div id="h-captcha" class="h-captcha" data-sitekey="' . $testSiteKey . '" ' .
			'data-size="invisible"></div>' .
			'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
			"<div id=\"h-captcha\" class=\"h-captcha\" data-sitekey=\"$testSiteKey\" data-size=\"invisible\"></div>" .
			'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>'
		];

		yield 'active mode, prior error set' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => false,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => false,
				'HCaptchaSecureEnclave' => false,
			],
			[ 'error' => 'some-error' ],
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
			'data-sitekey="' . $testSiteKey . '"></div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
		];

		yield 'invisible mode, prior error set' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => true,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => false,
				'HCaptchaSecureEnclave' => false,
			],
			[ 'error' => 'some-error' ],
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
			'data-sitekey="' . $testSiteKey . '" data-size="invisible"></div>' .
			'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
				"data-sitekey=\"$testSiteKey\" data-size=\"invisible\"></div>" .
			'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>'
		];

		yield 'active mode, secure enclave mode enabled without enterprise mode enabled' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => false,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => false,
				'HCaptchaSecureEnclave' => true,
			],
			[ 'error' => 'some-error' ],
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
			'data-sitekey="' . $testSiteKey . '"></div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
		];

		yield 'active mode, secure enclave mode enabled' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => false,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => true,
				'HCaptchaSecureEnclave' => true,
			],
			[ 'error' => 'some-error' ],
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
			'data-sitekey="' . $testSiteKey . '"></div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
		];

		yield 'invisible mode, secure enclave mode enabled' => [
			[
				'HCaptchaApiUrl' => $testApiUrl,
				'HCaptchaInvisibleMode' => true,
				'HCaptchaCSPRules' => $testCspRules,
				'HCaptchaSiteKey' => $testSiteKey,
				'HCaptchaEnterprise' => true,
				'HCaptchaSecureEnclave' => true,
			],
			[ 'error' => 'some-error' ],
			'<div id="h-captcha" class="h-captcha mw-confirmedit-captcha-fail" ' .
			'data-sitekey="' . $testSiteKey . '" data-size="invisible"></div>' .
			'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>' .
			'<noscript class="h-captcha-noscript-container">' .
			'<div class="h-captcha-noscript-message cdx-message cdx-message--error">' .
			'(hcaptcha-noscript)</div></noscript>',
				"data-sitekey=\"$testSiteKey\" data-size=\"invisible\"></div>" .
				'<div class="h-captcha-privacy-policy">(hcaptcha-privacy-policy)</div>',
		];
	}

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate( mixed $input, string|bool $expected ): void {
		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );

		$form = HTMLForm::factory( 'ooui', [], $context );
		$field = new HTMLHCaptchaField( [ 'parent' => $form, 'name' => 'test' ] );

		$result = $field->validate( $input, [] );

		if ( is_string( $expected ) ) {
			$this->assertInstanceOf( Message::class, $result );
			$this->assertSame( $expected, $result->getKey() );
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	public static function provideValidate(): iterable {
		yield 'null input' => [ null, 'hcaptcha-missing-token' ];
		yield 'empty input' => [ '', 'hcaptcha-missing-token' ];
		yield 'non-empty input' => [ 'foo123', true ];
	}

	/**
	 * Test that renderNoJavaScriptOutput shows noscript content only for edit action
	 */
	public function testRenderNoJavaScriptOutput(): void {
		$this->setUserLang( 'qqx' );

		$outputPage = RequestContext::getMain()->getOutput();
		$outputPage->setTitle( $this->getServiceContainer()->getTitleFactory()->newFromText( __METHOD__ ) );
		/** @var HCaptchaOutput $service */
		$hCaptchaOutput = $this->getServiceContainer()->get( 'HCaptchaOutput' );
		$result = $hCaptchaOutput->addHCaptchaToForm( $outputPage, false );

		$this->assertStringContainsString( '<noscript class="h-captcha-noscript-container">', $result );
		$this->assertStringContainsString( '(hcaptcha-noscript)', $result );
	}

	public function testSiteKeyOverriddenForAction(): void {
		$this->overrideConfigValue( 'HCaptchaSiteKey', 'baz' );
		$this->overrideConfigValue( 'CaptchaTriggers', [
			'create' => [
				'trigger' => 'true',
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'foo' ]
			],
			'edit' => [
				'trigger' => 'true',
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'bar' ]
			],
		] );

		$outputPage = RequestContext::getMain()->getOutput();
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CreateAccount' );
		$outputPage->setTitle( $page->getPageTitle() );
		/** @var HCaptchaOutput $service */
		$hCaptchaOutput = $this->getServiceContainer()->get( 'HCaptchaOutput' );
		$result = $hCaptchaOutput->addHCaptchaToForm( $outputPage, false );
		$this->assertStringContainsString( 'data-sitekey="baz"', $result );

		$title = $this->getServiceContainer()->getTitleFactory()->newFromText( 'Test' );
		$outputPage->setTitle( $title );
		$result = $hCaptchaOutput->addHCaptchaToForm( $outputPage, false );
		$this->assertStringContainsString( 'data-sitekey="foo"', $result );

		$this->editPage( 'Test', 'Test' );
		$outputPage->setTitle( $title );
		$result = $hCaptchaOutput->addHCaptchaToForm( $outputPage, false );
		$this->assertStringContainsString( 'data-sitekey="bar"', $result );
	}

}
