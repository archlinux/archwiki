<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\hCaptcha;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaEnterpriseHealthChecker;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\FormatterFactory;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Psr\Log\NullLogger;
use TestLogger;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaEnterpriseHealthChecker
 */
class HCaptchaEnterpriseHealthCheckerTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testIncrementSiteverifyApiErrorCountAboveThreshold() {
		$this->installMockHttp(
			$this->makeFakeHttpRequest()
		);
		/** @var HCaptchaEnterpriseHealthChecker $healthChecker */
		$healthChecker = $this->getServiceContainer()->getService( 'HCaptchaEnterpriseHealthChecker' );
		for ( $i = 1; $i <= 10; $i++ ) {
			$healthChecker->incrementSiteVerifyApiErrorCount();
		}
		$this->assertFalse( $healthChecker->isAvailable() );
	}

	public function testIncrementSiteverifyApiErrorCountBelowThreshold() {
		$this->installMockHttp(
			$this->makeFakeHttpRequest()
		);
		/** @var HCaptchaEnterpriseHealthChecker $healthChecker */
		$healthChecker = $this->getServiceContainer()->getService( 'HCaptchaEnterpriseHealthChecker' );
		$healthChecker->incrementSiteVerifyApiErrorCount();
		$this->assertTrue( $healthChecker->isAvailable() );
	}

	public function testInProcessCache() {
		/** @var HCaptchaEnterpriseHealthChecker $healthChecker */
		$healthChecker = $this->getServiceContainer()->getService( 'HCaptchaEnterpriseHealthChecker' );
		$wrapper = TestingAccessWrapper::newFromObject( $healthChecker );
		$wrapper->isAvailable = true;
		// This check would fail if the process cache wasn't it, because MockHttp is not installed.
		$this->assertTrue( $healthChecker->isAvailable() );
	}

	public function testHttpFailures() {
		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 500 )
		);
		/** @var HCaptchaEnterpriseHealthChecker $healthChecker */
		$healthChecker = $this->getServiceContainer()->getService( 'HCaptchaEnterpriseHealthChecker' );
		$this->assertFalse( $healthChecker->isAvailable() );
	}

	public function testInFailoverMode() {
		$bag = new HashBagOStuff();
		$wanObjectCacheMock = new WANObjectCache( [
			'cache' => $bag,
		] );
		$wanObjectCacheMock->set(
			$wanObjectCacheMock->makeGlobalKey( 'confirmedit-hcaptcha-failover-mode' ),
			true
		);
		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'ConfirmEdit' );
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			new NullLogger(),
			$this->createNoOpMock( BagOStuff::class ),
			$wanObjectCacheMock,
			$this->createNoOpMock( HttpRequestFactory::class ),
			$this->createNoOpMock( FormatterFactory::class ),
			$statsHelper->getStatsFactory()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$this->assertSame( 1, $statsHelper->count(
			'hcaptcha_enterprise_health_checker_is_available_seconds{result="false"}' )
		);
	}

	public function testIntegrityHashCheckFailure() {
		$this->overrideConfigValue( 'HCaptchaApiUrlIntegrityHash', 'sha384-foo' );
		$logger = new TestLogger( true );
		$this->installMockHttp(
			$this->makeFakeHttpRequest( 'bar' )
		);

		$services = $this->getServiceContainer();
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$logger,
			$services->getObjectCacheFactory()->getLocalClusterInstance(),
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$services->getStatsFactory()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$this->assertEquals(
			'Entering failover mode',
			$logger->getBuffer()[0][1]
		);
		$this->assertEquals(
			'Integrity hash {parameter1} does not match expected {parameter2}',
			$logger->getBuffer()[1][1]
		);
	}

	public function testIntegrityHashAlgorithmInvalid() {
		$this->overrideConfigValue( 'HCaptchaApiUrlIntegrityHash', 'sha123-foo' );
		$logger = new TestLogger( true );
		$this->installMockHttp(
			$this->makeFakeHttpRequest( 'bar' )
		);

		$services = $this->getServiceContainer();
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$logger,
			$services->getObjectCacheFactory()->getLocalClusterInstance(),
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$services->getStatsFactory()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$this->assertEquals(
			'Entering failover mode',
			$logger->getBuffer()[0][1]
		);
		$this->assertEquals(
			'Invalid hash algorithm: {parameter1}',
			$logger->getBuffer()[1][1]
		);
	}

	public function testIntegrityHashValid() {
		$this->overrideConfigValue(
			'HCaptchaApiUrlIntegrityHash',
			'sha384-mMEf/f3VQGdrGhN8saIrKnA1DJpEFx1rEYDGvly7LuP3nVMsih3Z7y6OCOdSo7q7'
		);
		$logger = new TestLogger( true );
		$this->installMockHttp(
			$this->makeFakeHttpRequest( 'foo' )
		);

		$services = $this->getServiceContainer();
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$logger,
			$services->getObjectCacheFactory()->getLocalClusterInstance(),
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$services->getStatsFactory()
		);
		$this->assertTrue( $healthChecker->isAvailable() );
		$this->assertEquals( [], $logger->getBuffer() );
	}
}
