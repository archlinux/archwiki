<?php

namespace MediaWiki\Extension\ConfirmEdit\Test;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;
use MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

class AbuseFilterTest extends MediaWikiIntegrationTestCase {

	public static function setUpBeforeClass(): void {
		// Cannot use markTestSkippedIfExtensionNotLoaded() because we need to skip the entire class.
		// Specifically, skip MediaWikiCoversValidator because AbuseFilterHooks.php fails.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) ) {
			self::markTestSkipped( "AbuseFilter extension is required for this test" );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks
	 */
	public function testOnAbuseFilterCustomActions() {
		$config = new HashConfig( [ 'ConfirmEditEnabledAbuseFilterCustomActions' => [ 'showcaptcha' ] ] );
		$abuseFilterHooks = new AbuseFilterHooks( $config );
		$actions = [];
		$abuseFilterHooks->onAbuseFilterCustomActions( $actions );
		$this->assertArrayHasKey( 'showcaptcha', $actions );
	}

	/**
	 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence
	 */
	public function testConsequence() {
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getAction' )->willReturn( 'edit' );
		$captchaConsequence = new CaptchaConsequence( $parameters );
		$simpleCaptcha = Hooks::getInstance();
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$captchaConsequence->execute();
		$this->assertTrue( $simpleCaptcha->shouldForceShowCaptcha() );
	}
}
