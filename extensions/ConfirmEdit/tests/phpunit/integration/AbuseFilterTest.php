<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;
use MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use TestLogger;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence
 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks
 */
class AbuseFilterTest extends MediaWikiIntegrationTestCase {

	public static function setUpBeforeClass(): void {
		// Cannot use markTestSkippedIfExtensionNotLoaded() because we need to skip the entire class.
		// Specifically, skip MediaWikiCoversValidator because AbuseFilterHooks.php fails.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) ) {
			self::markTestSkipped( "AbuseFilter extension is required for this test" );
		}
	}

	public function testOnAbuseFilterCustomActions() {
		$config = new HashConfig( [ 'ConfirmEditEnabledAbuseFilterCustomActions' => [ 'showcaptcha' ] ] );
		$abuseFilterHooks = new AbuseFilterHooks( $config );
		$actions = [];
		$abuseFilterHooks->onAbuseFilterCustomActions( $actions );
		$this->assertArrayHasKey( 'showcaptcha', $actions );
	}

	public function testConsequence() {
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getAction' )->willReturn( 'edit' );
		$captchaConsequence = new CaptchaConsequence( $parameters );
		$simpleCaptcha = Hooks::getInstance( 'edit' );
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$captchaConsequence->execute();
		$this->assertTrue( $simpleCaptcha->shouldForceShowCaptcha() );
	}

	public function testConsequenceActionDoesNotMatch() {
		$logger = new TestLogger( true );
		$this->setLogger( 'ConfirmEdit', $logger );
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getAction' )->willReturn( 'foo' );
		$captchaConsequence = new CaptchaConsequence( $parameters );
		$simpleCaptcha = Hooks::getInstance( 'bar' );
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$captchaConsequence->execute();
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$this->assertEquals(
			'Filter {filter}: {action} is not defined in the list of triggers known to ConfirmEdit',
			$logger->getBuffer()[0][1]
		);
	}
}
