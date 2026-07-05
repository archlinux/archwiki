<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;
use MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Session\Session;
use MediaWikiIntegrationTestCase;
use TestLogger;
use Wikimedia\Timestamp\ConvertibleTimestamp;

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

	public function setUp(): void {
		parent::setUp();

		Hooks::unsetInstanceForTests();

		$this->getSession()->remove(
			SimpleCaptcha::ABUSEFILTER_CAPTCHA_CONSEQUENCE_SESSION_KEY
		);
		$this->getSession()->remove(
			CaptchaConsequence::FILTER_ID_SESSION_KEY
		);
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

		$this->assertFalse(
			$this->getSession()->exists(
				SimpleCaptcha::ABUSEFILTER_CAPTCHA_CONSEQUENCE_SESSION_KEY
			)
		);
	}

	public function testConsequenceSetsSessionKeyOnMatch() {
		ConvertibleTimestamp::setFakeTime( 1750000000 );
		$this->overrideConfigValue(
			'CaptchaAbuseFilterCaptchaConsequenceTTL',
			600
		);

		$filter = $this->createMock( ExistingFilter::class );
		$filter
			->expects( $this->once() )
			->method( 'getID' )
			->willReturn( 123 );

		$parameters = $this->createMock( Parameters::class );
		$parameters
			->method( 'getAction' )
			->willReturn( 'edit' );
		$parameters
			->method( 'getFilter' )
			->willReturn( $filter );

		$captchaConsequence = new CaptchaConsequence( $parameters );
		$simpleCaptcha = Hooks::getInstance( CaptchaTriggers::EDIT );
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$this->assertFalse(
			$this->getSession()->exists(
				SimpleCaptcha::ABUSEFILTER_CAPTCHA_CONSEQUENCE_SESSION_KEY
			)
		);

		$captchaConsequence->execute();

		$this->assertTrue( $simpleCaptcha->shouldForceShowCaptcha() );
		$this->assertEquals(
			// current timestamp + 10 minutes
			1750000600,
			$this->getSession()->get(
				SimpleCaptcha::ABUSEFILTER_CAPTCHA_CONSEQUENCE_SESSION_KEY
			)
		);
		$this->assertEquals(
			123,
			$this->getSession()->get(
				CaptchaConsequence::FILTER_ID_SESSION_KEY
			)
		);
	}

	private function getSession(): Session {
		return RequestContext::getMain()->getRequest()->getSession();
	}
}
