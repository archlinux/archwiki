<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\MakeGlobalVariablesScriptHookHandler;
use MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MobileContext;
use Wikimedia\ArrayUtils\ArrayUtils;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\MakeGlobalVariablesScriptHookHandler
 * @group Database
 */
class MakeGlobalVariablesScriptHookHandlerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideMakeGlobalVariablesScript */
	public function testMakeGlobalVariablesScript( object $testCase ): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'VisualEditor' );
		$this->markTestSkippedIfExtensionNotLoaded( 'MobileFrontend' );

		// Make hCaptcha be used as the captcha for editing, so it will be the captcha specified in the JS config var
		$this->overrideConfigValue( 'CaptchaTriggers', [
			'create' => [
				'trigger' => $testCase->shouldCheckResult,
				'class' => $testCase->createCaptchaClass,
				'config' => [ 'HCaptchaSiteKey' => 'foo', 'HCaptchaAlwaysChallengeSiteKey' => 'foo-always' ]
			],
			'edit' => [
				'trigger' => $testCase->shouldCheckResult,
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'bar' ]
			],
		] );
		$this->overrideConfigValue(
			'HCaptchaVisualEditorOnLoadIntegrationEnabled',
			$testCase->hCaptchaVisualEditorOnLoadIntegrationEnabled
		);
		$this->clearHook( 'ConfirmEditCaptchaClass' );

		$out = RequestContext::getMain()->getOutput();
		$out->setTitle( Title::newFromText( __METHOD__ ) );

		if ( $testCase->isVisualEditorAvailable !== null ) {
			$mockVisualEditorAvailabilityLookup = $this->createMock( VisualEditorAvailabilityLookup::class );
			$mockVisualEditorAvailabilityLookup->method( 'isAvailable' )
				->with( $out->getTitle(), $out->getRequest(), $out->getUser() )
				->willReturn( $testCase->isVisualEditorAvailable );
		} else {
			$mockVisualEditorAvailabilityLookup = null;
		}

		if ( $testCase->isMobileFrontendAvailable !== null ) {
			$mockMobileContext = $this->createMock( MobileContext::class );
			$mockMobileContext->method( 'shouldDisplayMobileView' )
				->willReturn( $testCase->isMobileFrontendAvailable );
		} else {
			$mockMobileContext = null;
		}

		$this->overrideConfigValue(
			'HCaptchaEnabledInMobileFrontend',
			$testCase->isHCaptchaEnabledInMobileFrontend
		);

		/**
		 * @var $mockExtensionRegistry \MediaWiki\Registration\ExtensionRegistry
		 */
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnCallback( static function ( $name ) use ( $testCase ) {
				if ( $name === 'VisualEditor' ) {
					return $testCase->isVisualEditorAvailable !== null;
				}
				if ( $name === 'MobileFrontend' ) {
					return $testCase->isMobileFrontendAvailable !== null;
				}
				if ( $name === 'Abuse Filter' ) {
					return $testCase->isAbuseFilterAvailable;
				}

				return false;
			} );

		Hooks::getInstance( 'create' )
			->setForceShowCaptcha( $testCase->shouldForceShowCaptcha );

		// Call the hook and expect that variable is added if $isAvailable is true
		$vars = [];
		$objectUnderTest = new MakeGlobalVariablesScriptHookHandler(
			$mockExtensionRegistry,
			$this->getServiceContainer()->getMainConfig(),
			$mockVisualEditorAvailabilityLookup,
			$mockMobileContext
		);
		$objectUnderTest->onMakeGlobalVariablesScript( $vars, $out );

		if ( $testCase->expectedConfirmEditNeededForCaptchaValue === null &&
			$testCase->expectedHCaptchaSiteKeyValue === null ) {
			$this->assertCount( 0, $vars );
			$this->assertNotContains(
				'ext.confirmEdit.hCaptcha',
				$out->getModules()
			);
		} else {
			$expected = [];

			if ( $testCase->isMobileFrontendAvailable &&
				strtolower( $testCase->createCaptchaClass ) === 'hcaptcha' &&
				$testCase->isHCaptchaEnabledInMobileFrontend ) {
				$expected['wgMobileFrontendSourceEditorInitializeModules'] = [
					'ext.confirmEdit.hCaptcha'
				];
			}

			if ( $testCase->isMobileFrontendAvailable &&
				strtolower( $testCase->createCaptchaClass ) === 'hcaptcha' &&
				$testCase->isHCaptchaEnabledInMobileFrontend ) {
				$this->assertContains( 'ext.confirmEdit.hCaptcha', $out->getModules() );
			}

			if ( $testCase->expectedMobileHCaptchaAbuseFilterEnabled ) {
				$expected['wgConfirmEditMobileHCaptchaAbuseFilterEnabled'] = true;
			}

			if ( $testCase->expectedConfirmEditNeededForCaptchaValue !== null ) {
				$expected['wgConfirmEditCaptchaNeededForGenericEdit'] =
					$testCase->expectedConfirmEditNeededForCaptchaValue;
				$expected['wgConfirmEditForceShowCaptcha'] = $testCase->shouldForceShowCaptcha;

				if ( $testCase->expectedConfirmEditNeededForCaptchaValue === 'hcaptcha' ) {
					$expected['wgConfirmEditHCaptchaSiteKey'] = 'foo';
				}
			}
			if ( $testCase->expectedHCaptchaSiteKeyValue !== null ) {
				$expected['wgConfirmEditHCaptchaSiteKey'] = $testCase->expectedHCaptchaSiteKeyValue;
			}
			if ( $testCase->expectedHCaptchaVisualEditorIntegrationEnabledValue !== null ) {
				$expected['wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled'] =
					$testCase->expectedHCaptchaVisualEditorIntegrationEnabledValue;
			}

			$this->assertArrayEquals( $expected, $vars, false, true );
		}
	}

	public function testMakeGlobalVariablesScriptUserCanSkipCaptcha(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'MobileFrontend' );

		$this->overrideConfigValue( 'CaptchaTriggers', [
			'edit' => [
				'trigger' => true,
				'class' => 'HCaptcha',
				'config' => [ 'HCaptchaSiteKey' => 'bar' ]
			],
		] );
		$this->overrideConfigValue( 'HCaptchaEnabledInMobileFrontend', true );
		$this->clearHook( 'ConfirmEditCaptchaClass' );

		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, [ 'skipcaptcha' ] );
		RequestContext::getMain()->setUser( $user );

		$out = RequestContext::getMain()->getOutput();
		$out->setTitle( Title::newFromText( __METHOD__ ) );

		$mockMobileContext = $this->createMock( MobileContext::class );
		$mockMobileContext->expects( $this->once() )
			->method( 'shouldDisplayMobileView' )
			->willReturn( true );

		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnCallback( static function ( $name ) {
				if ( $name === 'MobileFrontend' || $name === 'Abuse Filter' ) {
					return true;
				}
				return false;
			} );

		$vars = [];
		$objectUnderTest = new MakeGlobalVariablesScriptHookHandler(
			$mockExtensionRegistry,
			$this->getServiceContainer()->getMainConfig(),
			null,
			$mockMobileContext
		);
		$objectUnderTest->onMakeGlobalVariablesScript( $vars, $out );

		$this->assertNotContains( 'ext.confirmEdit.hCaptcha', $out->getModules() );
		$this->assertArrayNotHasKey( 'wgMobileFrontendSourceEditorInitializeModules', $vars );
		$this->assertArrayNotHasKey( 'wgConfirmEditMobileHCaptchaAbuseFilterEnabled', $vars );
	}

	public static function provideMakeGlobalVariablesScript(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// VisualEditor availability (null for not installed)
			[ true, false, null ],
			// $wgHCaptchaVisualEditorOnLoadIntegrationEnabled value
			[ true, false ],
			// MobileFrontend editor availability (null for not installed)
			[ true, false, null ],
			// Does the user need to complete a captcha for any edit (a "generic" edit)
			[ true, false ],
			// The value returned by SimpleCaptcha::shouldForceShowCaptcha
			[ true, false ],
			// The captcha class used for create actions
			[ 'HCaptcha', 'SimpleCaptcha' ],
			// Whether the AbuseFilter extension is installed and enabled
			[ true, false ],
			// Whether $wgHCaptchaEnabledInMobileFrontend is enabled
			[ true, false ]
		);

		foreach ( $testCases as $params ) {
			$isVisualEditorAvailable = $params[0];
			$isVEIntegrationEnabled = $params[1];
			$isMobileFrontendAvailable = $params[2];
			$shouldCheck = $params[3];
			$shouldForceShowCaptcha = $params[4];
			$createCaptchaClass = $params[5];
			$isAbuseFilterAvailable = $params[6];
			$isHCaptchaEnabledInMobileFrontend = $params[7];

			$expectedConfirmEditNeededForCaptchaValue = null;
			$expectedHCaptchaSiteKeyValue = null;
			$expectedHCaptchaVisualEditorOnLoadIntegrationEnabledValue = null;

			// The behaviour when VisualEditor and MobileFrontend are both not installed is tested by other unit
			// tests, so we don't need to repeat that here
			if ( $isVisualEditorAvailable === null && $isMobileFrontendAvailable === null ) {
				continue;
			}

			// HCaptchaEnabledInMobileFrontend only affects output when MobileFrontend is available
			if ( $isMobileFrontendAvailable === null && !$isHCaptchaEnabledInMobileFrontend ) {
				continue;
			}

			// If VisualEditor is not installed or not available, there is no need to test when
			// $wgHCaptchaVisualEditorOnLoadIntegrationEnabled is true (because it will never be enabled)
			if ( $isVisualEditorAvailable !== true && $isVEIntegrationEnabled === true ) {
				continue;
			}

			// The JavaScript config variables will be set if either VisualEditor or
			// MobileFrontend editor is available.
			if ( $isVisualEditorAvailable === true || $isMobileFrontendAvailable === true ) {
				$expectedConfirmEditNeededForCaptchaValue = false;
			}

			// The JavaScript config variable will have a value of the captcha class in use if:
			// * A captcha is needed for a generic edit or SimpleCaptcha::shouldForceShowCaptcha returns true
			// * Either the VisualEditor or MobileFrontend editor is available
			if (
				( $isVisualEditorAvailable === true || $isMobileFrontendAvailable === true ) &&
				( $shouldCheck === true || $shouldForceShowCaptcha === true )
			) {
				$expectedConfirmEditNeededForCaptchaValue = strtolower( $params[5] );

				if ( $expectedConfirmEditNeededForCaptchaValue === 'hcaptcha' ) {
					$expectedHCaptchaSiteKeyValue = 'foo';
					if ( $shouldForceShowCaptcha === true ) {
						$expectedHCaptchaSiteKeyValue = 'foo-always';
					}

					// wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled is true if both:
					// * VisualEditor is available
					// * $wgHCaptchaVisualEditorOnLoadIntegrationEnabled is true
					$expectedHCaptchaVisualEditorOnLoadIntegrationEnabledValue = $isVisualEditorAvailable === true &&
						$isVEIntegrationEnabled === true;
				}
			}

			// ::shouldForceShowCaptcha values only affect the test output if hCaptcha is needed for an edit,
			// so only test with it as false if not hCaptcha
			if ( $expectedConfirmEditNeededForCaptchaValue !== 'hcaptcha' && $shouldForceShowCaptcha === true ) {
				continue;
			}

			// The $wgHCaptchaVisualEditorOnLoadIntegrationEnabled values only affect the test if hCaptcha
			// is the captcha required for a generic edit, so only test with it as false if not hCaptcha
			if ( $expectedConfirmEditNeededForCaptchaValue !== 'hcaptcha' && $isVEIntegrationEnabled === true ) {
				continue;
			}

			$testCase = new class(
				expectedConfirmEditNeededForCaptchaValue:
					$expectedConfirmEditNeededForCaptchaValue,
				expectedHCaptchaSiteKeyValue: $expectedHCaptchaSiteKeyValue,
				expectedHCaptchaVisualEditorIntegrationEnabledValue:
					$expectedHCaptchaVisualEditorOnLoadIntegrationEnabledValue,
				expectedMobileHCaptchaAbuseFilterEnabled:
					$isMobileFrontendAvailable && $isAbuseFilterAvailable &&
					$isHCaptchaEnabledInMobileFrontend &&
					strtolower( $createCaptchaClass ) === 'hcaptcha',
				hCaptchaVisualEditorOnLoadIntegrationEnabled: $isVEIntegrationEnabled,
				isVisualEditorAvailable: $isVisualEditorAvailable,
				isMobileFrontendAvailable: $isMobileFrontendAvailable,
				isAbuseFilterAvailable: $isAbuseFilterAvailable,
				isHCaptchaEnabledInMobileFrontend: $isHCaptchaEnabledInMobileFrontend,
				shouldCheckResult: $shouldCheck,
				shouldForceShowCaptcha: $shouldForceShowCaptcha,
				createCaptchaClass: $createCaptchaClass,
			) {
				public function __construct(
					public string|bool|null $expectedConfirmEditNeededForCaptchaValue,
					public ?string $expectedHCaptchaSiteKeyValue,
					public ?bool $expectedHCaptchaVisualEditorIntegrationEnabledValue,
					public bool $expectedMobileHCaptchaAbuseFilterEnabled,
					public ?bool $isVisualEditorAvailable,
					public bool $hCaptchaVisualEditorOnLoadIntegrationEnabled,
					public ?bool $isMobileFrontendAvailable,
					public bool $isAbuseFilterAvailable,
					public bool $isHCaptchaEnabledInMobileFrontend,
					public bool $shouldCheckResult,
					public bool $shouldForceShowCaptcha,
					public string $createCaptchaClass,
				) {
				}
			};

			yield sprintf(
				'VisualEditor is %s with integration %s, ' .
					'MobileFrontend editor is %s, AbuseFilter is %s, hCaptcha in MF is %s, ' .
					'ConfirmEdit captcha is %s and %s with force captcha %s',
				match ( $testCase->isVisualEditorAvailable ) {
					null => 'not installed',
					true => 'available',
					false => 'not available',
				},
				$testCase->hCaptchaVisualEditorOnLoadIntegrationEnabled ?
					'enabled' : 'disabled',
				match ( $testCase->isMobileFrontendAvailable ) {
					null => 'not installed',
					true => 'available',
					false => 'not available',
				},
				$testCase->isAbuseFilterAvailable ?
					'available' :
					'not available',
				$testCase->isHCaptchaEnabledInMobileFrontend ? 'enabled' : 'disabled',
				$testCase->createCaptchaClass,
				$testCase->shouldCheckResult ?
					'required for a generic edit' :
					'not needed for a generic edit',
				$testCase->shouldForceShowCaptcha ? 'set' : 'not set'
			) => [
				'testCase' => $testCase
			];
		}
	}
}
