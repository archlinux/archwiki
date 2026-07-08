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
		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'ConfirmEdit' );
		$services = $this->getServiceContainer();
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			new NullLogger(),
			$services->getObjectCacheFactory()->getLocalClusterInstance(),
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$statsHelper->getStatsFactory(),
			new HashBagOStuff()
		);
		for ( $i = 1; $i <= 10; $i++ ) {
			$healthChecker->incrementSiteVerifyApiErrorCount();
		}
		$this->assertFalse( $healthChecker->isAvailable() );
		$this->assertSame( 1, $statsHelper->count(
			'hcaptcha_enterprise_failover_total{reason="siteverify_errors"}' )
		);
	}

	public function testIncrementSiteverifyApiErrorCountBelowThreshold() {
		$this->overrideConfigValue( 'HCaptchaApiUrlIntegrityHash', '' );
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

	public function testServerCacheHit() {
		$serverCache = new HashBagOStuff();
		// Pre-populate the server cache with "available"
		$serverCache->set(
			$serverCache->makeGlobalKey( 'confirmedit-hcaptcha-available' ),
			1
		);
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			new NullLogger(),
			$this->createNoOpMock( BagOStuff::class ),
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( HttpRequestFactory::class ),
			$this->createNoOpMock( FormatterFactory::class ),
			StatsFactory::newNull(),
			$serverCache
		);
		// Should return true from server cache without touching WANObjectCache or HTTP.
		$this->assertTrue( $healthChecker->isAvailable() );
	}

	public function testServerCacheHitUnavailable() {
		$serverCache = new HashBagOStuff();
		// Pre-populate the server cache with "unavailable"
		$serverCache->set(
			$serverCache->makeGlobalKey( 'confirmedit-hcaptcha-available' ),
			0
		);
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			new NullLogger(),
			$this->createNoOpMock( BagOStuff::class ),
			$this->createNoOpMock( WANObjectCache::class ),
			$this->createNoOpMock( HttpRequestFactory::class ),
			$this->createNoOpMock( FormatterFactory::class ),
			StatsFactory::newNull(),
			$serverCache
		);
		// Should return false from server cache without touching WANObjectCache or HTTP.
		$this->assertFalse( $healthChecker->isAvailable() );
	}

	public function testHttpFailuresBelowThreshold() {
		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 500 )
		);
		$logger = new TestLogger( true );
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
			$services->getStatsFactory(),
			new HashBagOStuff()
		);
		// A single failure should not trigger failover (default threshold is 3).
		$this->assertTrue( $healthChecker->isAvailable() );
		$logMessages = array_column( $logger->getBuffer(), 1 );
		$this->assertContains(
			'apiUrl check failed on all {maxAttempts} attempts',
			$logMessages
		);
		$this->assertContains(
			'apiUrl check failed, error count {count} below threshold {threshold}',
			$logMessages
		);
	}

	public function testHttpFailuresAboveThreshold() {
		$this->overrideConfigValue( 'HCaptchaEnterpriseHealthCheckApiUrlErrorThreshold', 3 );
		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 500 )
		);
		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'ConfirmEdit' );
		$services = $this->getServiceContainer();
		$bagOStuff = $services->getObjectCacheFactory()->getLocalClusterInstance();
		// Simulate that we're already at 2 errors (one below the threshold of 3).
		// The next failure will push us to 3, meeting the threshold.
		$bagOStuff->incrWithInit(
			$bagOStuff->makeGlobalKey( 'confirmedit-hcaptcha-apiurl-error-count' ),
			BagOStuff::TTL_MINUTE * 30
		);
		$bagOStuff->incrWithInit(
			$bagOStuff->makeGlobalKey( 'confirmedit-hcaptcha-apiurl-error-count' ),
			BagOStuff::TTL_MINUTE * 30
		);
		$healthChecker = new HCaptchaEnterpriseHealthChecker(
			new ServiceOptions(
				HCaptchaEnterpriseHealthChecker::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			new NullLogger(),
			$bagOStuff,
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory(),
			$statsHelper->getStatsFactory(),
			new HashBagOStuff()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$this->assertSame( 1, $statsHelper->count(
			'hcaptcha_enterprise_failover_total{reason="apiurl_errors"}' )
		);
	}

	public function testCachedUnavailable() {
		$bag = new HashBagOStuff();
		$wanObjectCacheMock = new WANObjectCache( [
			'cache' => $bag,
		] );
		// Simulate a previous health check that cached unavailability (e.g.
		// the callback returned 0 with a 10-minute TTL).
		$wanObjectCacheMock->set(
			$wanObjectCacheMock->makeGlobalKey( 'confirmedit-hcaptcha-available' ),
			0
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
			$statsHelper->getStatsFactory(),
			new HashBagOStuff()
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

		$statsHelper = StatsFactory::newUnitTestingHelper()->withComponent( 'ConfirmEdit' );
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
			$statsHelper->getStatsFactory(),
			new HashBagOStuff()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$logMessages = array_column( $logger->getBuffer(), 1 );
		$this->assertContains(
			'apiUrl check failed on all {maxAttempts} attempts',
			$logMessages
		);
		$this->assertContains(
			'Integrity hash {parameter1} does not match expected {parameter2}',
			$logMessages
		);
		$this->assertContains(
			'apiUrl integrity check failure, entering immediate failover',
			$logMessages
		);
		$this->assertSame( 1, $statsHelper->count(
			'hcaptcha_enterprise_failover_total{reason="integrity_failure"}' )
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
			$services->getStatsFactory(),
			new HashBagOStuff()
		);
		$this->assertFalse( $healthChecker->isAvailable() );
		$logMessages = array_column( $logger->getBuffer(), 1 );
		$this->assertContains(
			'apiUrl check failed on all {maxAttempts} attempts',
			$logMessages
		);
		$this->assertContains(
			'Invalid hash algorithm: {parameter1}',
			$logMessages
		);
		$this->assertContains(
			'apiUrl integrity check failure, entering immediate failover',
			$logMessages
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
			$services->getStatsFactory(),
			new HashBagOStuff()
		);
		$this->assertTrue( $healthChecker->isAvailable() );
		$this->assertEquals( [], $logger->getBuffer() );
	}

	public function testRetrySucceedsOnLaterAttempt() {
		$this->overrideConfigValues( [
			'HCaptchaApiUrlIntegrityHash' => '',
			'HCaptchaEnterpriseHealthCheckApiUrlRetryCount' => 2,
			'HCaptchaEnterpriseHealthCheckApiUrlRetryDelayMs' => 0,
		] );
		// First request fails (500), subsequent requests succeed (200).
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( '', 500 ),
			$this->makeFakeHttpRequest( 'ok', 200 ),
		] );
		$logger = new TestLogger( true );
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
			$services->getStatsFactory(),
			new HashBagOStuff()
		);
		$this->assertTrue( $healthChecker->isAvailable() );
		$logMessages = array_column( $logger->getBuffer(), 1 );
		$this->assertContains(
			'apiUrl check attempt {attempt} of {maxAttempts} failed, retrying in {retryDelayMs}ms',
			$logMessages
		);
		$this->assertContains(
			'apiUrl check failed on first attempt but succeeded on attempt {attempt} of {maxAttempts}',
			$logMessages
		);
	}

	public function testRetryCountZeroDisablesRetries() {
		$this->overrideConfigValues( [
			'HCaptchaEnterpriseHealthCheckApiUrlRetryCount' => 0,
			'HCaptchaEnterpriseHealthCheckApiUrlRetryDelayMs' => 0,
		] );
		// Only one request, and it fails.
		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 500 )
		);
		$logger = new TestLogger( true );
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
			$services->getStatsFactory(),
			new HashBagOStuff()
		);
		// Should still be available because error count is below threshold,
		// but no retry log messages should appear.
		$this->assertTrue( $healthChecker->isAvailable() );
		$logMessages = array_column( $logger->getBuffer(), 1 );
		$this->assertNotContains(
			'apiUrl check failed on all {maxAttempts} attempts',
			$logMessages
		);
		$this->assertNotContains(
			'apiUrl check failed on first attempt but succeeded on attempt {attempt} of {maxAttempts}',
			$logMessages
		);
		$this->assertContains(
			'apiUrl check failed, error count {count} below threshold {threshold}',
			$logMessages
		);
	}
}
