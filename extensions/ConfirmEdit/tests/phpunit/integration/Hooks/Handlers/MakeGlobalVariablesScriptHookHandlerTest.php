<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\Hooks\Handlers;

use ExtensionRegistry;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\MakeGlobalVariablesScriptHookHandler;
use MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MobileContext;
use Wikimedia\ArrayUtils\ArrayUtils;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\RLRegisterModulesHandler
 * @group Database
 */
class MakeGlobalVariablesScriptHookHandlerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideMakeGlobalVariablesScript */
	public function testMakeGlobalVariablesScript(
		bool|null $isVisualEditorAvailable, bool|null $isMobileFrontendAvailable, bool $shouldCheckResult,
		string|bool|null $javaScriptConfigVariableValue
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'VisualEditor' );
		$this->markTestSkippedIfExtensionNotLoaded( 'MobileFrontend' );

		// Make hCaptcha be used as the captcha for editing, so it will be the captcha specified in the JS config var
		$this->overrideConfigValue( 'CaptchaTriggers', [
			'create' => [
				'trigger' => $shouldCheckResult,
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'foo' ]
			],
			'edit' => [
				'trigger' => $shouldCheckResult,
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'bar' ]
			],
		] );
		$this->clearHook( 'ConfirmEditCaptchaClass' );

		$out = RequestContext::getMain()->getOutput();
		$out->setTitle( Title::newFromText( __METHOD__ ) );

		if ( $isVisualEditorAvailable !== null ) {
			$mockVisualEditorAvailabilityLookup = $this->createMock( VisualEditorAvailabilityLookup::class );
			$mockVisualEditorAvailabilityLookup->method( 'isAvailable' )
				->with( $out->getTitle(), $out->getRequest(), $out->getUser() )
				->willReturn( $isVisualEditorAvailable );
		} else {
			$mockVisualEditorAvailabilityLookup = null;
		}

		if ( $isMobileFrontendAvailable !== null ) {
			$mockMobileContext = $this->createMock( MobileContext::class );
			$mockMobileContext->method( 'shouldDisplayMobileView' )
				->willReturn( $isMobileFrontendAvailable );
		} else {
			$mockMobileContext = null;
		}

		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnCallback( static function ( $name ) use (
				$isVisualEditorAvailable, $isMobileFrontendAvailable
			) {
				if ( $name === 'VisualEditor' ) {
					return $isVisualEditorAvailable !== null;
				}
				if ( $name === 'MobileFrontend' ) {
					return $isMobileFrontendAvailable !== null;
				}
				return false;
			} );

		// Call the hook and expect that variable is added if $isAvailable is true
		$vars = [];
		$objectUnderTest = new MakeGlobalVariablesScriptHookHandler(
			$mockExtensionRegistry,
			$mockVisualEditorAvailabilityLookup,
			$mockMobileContext
		);
		$objectUnderTest->onMakeGlobalVariablesScript( $vars, $out );

		if ( $javaScriptConfigVariableValue !== null ) {
			$expected['wgConfirmEditCaptchaNeededForGenericEdit'] = $javaScriptConfigVariableValue;
			if ( $javaScriptConfigVariableValue === 'hcaptcha' ) {
				$expected['wgConfirmEditHCaptchaSiteKey'] = 'foo';
			}
			$this->assertArrayEquals(
				$expected, $vars, false, true
			);
		} else {
			$this->assertCount( 0, $vars );
		}
	}

	public static function provideMakeGlobalVariablesScript(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// VisualEditor availability (null for not installed)
			[ true, false, null ],
			// MobileFrontend editor availability (null for not installed)
			[ true, false, null ],
			// Does the user need to complete a captcha for any edit (a "generic" edit)
			[ true, false ],
		);

		foreach ( $testCases as $params ) {
			$expectedConfigVariableValue = null;

			// The behaviour when VisualEditor and MobileFrontend are both not installed is tested by other unit
			// tests, so we don't need to repeat that here
			if ( $params[0] === null && $params[1] === null ) {
				continue;
			}

			// The JavaScript config variable will be set if either VisualEditor or
			// MobileFrontend editor is available.
			if ( $params[0] === true || $params[1] === true ) {
				$expectedConfigVariableValue = false;
			}

			// The JavaScript config variable will have a value of 'hcaptcha' if:
			// * A captcha is needed for a generic edit
			// * Either the VisualEditor or MobileFrontend editor is available
			if ( ( $params[0] === true || $params[1] === true ) && $params[2] === true ) {
				$expectedConfigVariableValue = 'hcaptcha';
			}
			$params[3] = $expectedConfigVariableValue;

			yield sprintf(
				'VisualEditor is %s, MobileFrontend editor is %s, ConfirmEdit captcha is %s',
				match ( $params[0] ) {
					null => 'not installed',
					true => 'available',
					false => 'not available',
				},
				match ( $params[1] ) {
					null => 'not installed',
					true => 'available',
					false => 'not available',
				},
				$params[2] ? 'required for a generic edit' : 'not needed for a generic edit'
			) => $params;
		}
	}
}
