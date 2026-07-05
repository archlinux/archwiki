<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\RawMessage;
use MediaWiki\Status\Status;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Service used to update and query health check status for hCaptcha Enterprise
 */
class HCaptchaEnterpriseHealthChecker {

	public const CONSTRUCTOR_OPTIONS = [
		'HCaptchaApiUrl',
		'HCaptchaVerifyUrl',
		'HCaptchaProxy',
		'HCaptchaApiUrlIntegrityHash',
		'HCaptchaEnterpriseHealthCheckSiteVerifyErrorThreshold',
		'HCaptchaEnterpriseHealthCheckApiUrlErrorThreshold',
		'HCaptchaEnterpriseHealthCheckFailoverDuration',
		'HCaptchaEnterpriseHealthCheckApiUrlRetryCount',
		'HCaptchaEnterpriseHealthCheckApiUrlRetryDelayMs',
	];
	private const INTEGRITY_FAILURE = 'integrity_failure';
	private const CACHE_SITEVERIFY_ERROR_COUNT_KEY = 'confirmedit-hcaptcha-siteverify-error-count';
	private const CACHE_APIURL_ERROR_COUNT_KEY = 'confirmedit-hcaptcha-apiurl-error-count';
	private const CACHE_AVAILABLE_KEY = 'confirmedit-hcaptcha-available';
	private const SERVER_CACHE_TTL = 30;

	private ?bool $isAvailable = null;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly BagOStuff $bagOStuffCache,
		private readonly WANObjectCache $wanObjectCache,
		private readonly HttpRequestFactory $requestFactory,
		private readonly FormatterFactory $formatterFactory,
		private readonly StatsFactory $statsFactory,
		private readonly BagOStuff $serverCache
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Intended to be called from HCaptcha::passCaptcha(), when the request to /siteverify
	 * fails due to HTTP or JSON decoding errors. We do not want to accumulate other errors
	 * (e.g. incorrect token, or expired token, etc.) here.
	 *
	 * @see HCaptcha::passCaptcha()
	 */
	public function incrementSiteVerifyApiErrorCount(): void {
		$this->bagOStuffCache->incrWithInit(
			$this->bagOStuffCache->makeGlobalKey( self::CACHE_SITEVERIFY_ERROR_COUNT_KEY ),
			$this->bagOStuffCache::TTL_MINUTE
		);
	}

	/**
	 * Intended for use in a hook handler for onConfirmEditCaptchaClass, to decide if a fallback
	 * captcha type is needed in place of hCaptcha Enterprise.
	 *
	 * There are two things we want to check in considering that the hCaptcha service is functional:
	 *   1. Are user-generated requests to $wgHCaptchaVerifyUrl failing?
	 *   2. Is the script at $wgHCaptchaApiUrl reachable, and does it pass the integrity check, if configured?
	 *
	 * If either of those fail, then we enter a failover mode.
	 *
	 * @return bool true if the hCaptcha service is considered to be available, false otherwise.
	 */
	public function isAvailable(): bool {
		$timer = $this->statsFactory->withComponent( 'ConfirmEdit' )
			->getTiming( 'hcaptcha_enterprise_health_checker_is_available_seconds' )
			->setLabel( 'result', 'unknown' )
			->start();

		// In-process cache, since this method can be invoked multiple times per request.
		if ( $this->isAvailable !== null ) {
			$timer->setLabel( 'result', $this->isAvailable ? 'true' : 'false' )->stop();
			return $this->isAvailable;
		}

		// Local server cache (APCu) provides a per-pod circuit breaker so
		// that memcached failures do not cause every request to run the
		// expensive health-check callback (T412947, T421204).
		// Tradeoff: failover detection is delayed by up to SERVER_CACHE_TTL
		// seconds per pod when hCaptcha becomes unavailable.
		$serverCacheKey = $this->serverCache->makeGlobalKey( self::CACHE_AVAILABLE_KEY );
		$serverCacheValue = $this->serverCache->get( $serverCacheKey );
		if ( $serverCacheValue !== false ) {
			$this->isAvailable = (bool)$serverCacheValue;
			$timer->setLabel( 'result', $this->isAvailable ? 'true' : 'false' )->stop();
			return $this->isAvailable;
		}

		// Single cache lookup that combines all health checks. The callback
		// only runs when the cached value expires (~once per minute when
		// healthy).
		$this->isAvailable = (bool)$this->wanObjectCache->getWithSetCallback(
			$this->wanObjectCache->makeGlobalKey( self::CACHE_AVAILABLE_KEY ),
			$this->wanObjectCache::TTL_MINUTE,
			function ( $oldValue, &$ttl ) {
				$failoverDuration = $this->options->get( 'HCaptchaEnterpriseHealthCheckFailoverDuration' );
				// The SiteVerify request error count is incremented in
				// HCaptcha::passCaptcha(), when the SiteVerify request fails
				// with an HTTP or json-decode error.
				$failedSiteVerifyRequestCount = (int)$this->bagOStuffCache->get(
					$this->bagOStuffCache->makeGlobalKey( self::CACHE_SITEVERIFY_ERROR_COUNT_KEY )
				);
				$siteVerifyErrorThreshold = $this->options->get(
					'HCaptchaEnterpriseHealthCheckSiteVerifyErrorThreshold'
				);
				if ( $failedSiteVerifyRequestCount >= $siteVerifyErrorThreshold ) {
					$this->logger->warning(
						'hCaptcha unavailable due to SiteVerify errors: {count} >= {threshold}',
						[ 'count' => $failedSiteVerifyRequestCount, 'threshold' => $siteVerifyErrorThreshold ]
					);
					// Back off for a period of time before rechecking.
					$ttl = $failoverDuration;
					$this->recordFailover( 'siteverify_errors' );
					return 0;
				}

				// SiteVerify is OK, now check that the hCaptcha API JavaScript
				// file is available.
				$start = microtime( true );
				$retryCount = $this->options->get( 'HCaptchaEnterpriseHealthCheckApiUrlRetryCount' );
				$retryDelayMs = $this->options->get( 'HCaptchaEnterpriseHealthCheckApiUrlRetryDelayMs' );
				$maxAttempts = 1 + $retryCount;
				$attempt = 0;
				$apiUrlStatus = $this->checkApiUrl();
				$attempt++;
				while ( !$apiUrlStatus->isGood() && $attempt < $maxAttempts ) {
					$this->logger->info(
						'apiUrl check attempt {attempt} of {maxAttempts} failed, retrying in {retryDelayMs}ms',
						[ 'attempt' => $attempt, 'maxAttempts' => $maxAttempts, 'retryDelayMs' => $retryDelayMs ]
					);
					if ( $retryDelayMs > 0 ) {
						usleep( $retryDelayMs * 1000 );
					}
					$apiUrlStatus = $this->checkApiUrl();
					$attempt++;
				}
				$retried = $attempt > 1;
				if ( $retried ) {
					if ( $apiUrlStatus->isGood() ) {
						$this->logger->info(
							'apiUrl check failed on first attempt but succeeded on attempt {attempt} of {maxAttempts}',
							[ 'attempt' => $attempt, 'maxAttempts' => $maxAttempts ]
						);
					} else {
						$this->logger->warning(
							'apiUrl check failed on all {maxAttempts} attempts',
							[ 'maxAttempts' => $maxAttempts ]
						);
					}
				}
				$this->statsFactory->withComponent( 'ConfirmEdit' )
					->getTiming( 'hcaptcha_enterprise_health_checker__api_url_available_seconds' )
					->setLabel( 'retry', $retried ? '1' : '0' )
					->observeSeconds( ( microtime( true ) - $start ) );
				if ( !$apiUrlStatus->isGood() ) {
					$statusFormatter = $this->formatterFactory->getStatusFormatter( RequestContext::getMain() );
					$this->logger->error( ...$statusFormatter->getPsr3MessageAndContext( $apiUrlStatus, [
						'hcaptcha_health_check_type' => 'apiUrl',
					] ) );

					if ( $apiUrlStatus->value === self::INTEGRITY_FAILURE ) {
						// Integrity failures are security-sensitive: immediate failover
						$this->logger->warning(
							'apiUrl integrity check failure, entering immediate failover'
						);
						$ttl = $failoverDuration;
						$this->recordFailover( 'integrity_failure' );
						return 0;
					}

					// HTTP/network failure: apply counter-based threshold
					$errorCountKey = $this->bagOStuffCache->makeGlobalKey( self::CACHE_APIURL_ERROR_COUNT_KEY );
					$errorCount = $this->bagOStuffCache->incrWithInit(
						$errorCountKey,
						$this->bagOStuffCache::TTL_MINUTE * 30
					);
					$threshold = $this->options->get( 'HCaptchaEnterpriseHealthCheckApiUrlErrorThreshold' );
					if ( $errorCount >= $threshold ) {
						$this->logger->warning(
							'hCaptcha unavailable due to apiUrl errors: {count} >= {threshold}',
							[ 'count' => $errorCount, 'threshold' => $threshold ]
						);
						$ttl = $failoverDuration;
						$this->recordFailover( 'apiurl_errors' );
						return 0;
					}
					// Below threshold, recheck sooner to accumulate errors faster
					// during sustained outages
					$this->logger->warning(
						'apiUrl check failed, error count {count} below threshold {threshold}',
						[ 'count' => $errorCount, 'threshold' => $threshold ]
					);
					$ttl = $this->wanObjectCache::TTL_SECOND * 30;
					return 1;
				}
				// Reset the error counter if we could reach the URL
				$this->bagOStuffCache->delete(
					$this->bagOStuffCache->makeGlobalKey( self::CACHE_APIURL_ERROR_COUNT_KEY )
				);
				return 1;
			},
			[
				// Regenerating the value should take ~7 seconds at most
				// (2s timeout + 0.2s delay + 2s + 0.2s + 2s with default retry settings).
				'lockTSE' => 10,
				// Default to assuming availability while the value is regenerated
				'busyValue' => 1,
			]
		);

		// Cache in APCu so other requests on this pod skip the
		// WANObjectCache round-trip for the next 30 seconds.
		$this->serverCache->set( $serverCacheKey, $this->isAvailable ? 1 : 0, self::SERVER_CACHE_TTL );

		$timer->setLabel( 'result', $this->isAvailable ? 'true' : 'false' )->stop();
		return $this->isAvailable;
	}

	private function recordFailover( string $reason ): void {
		$this->statsFactory->withComponent( 'ConfirmEdit' )
			->getCounter( 'hcaptcha_enterprise_failover_total' )
			->setLabel( 'reason', $reason )
			->increment();
	}

	private function checkApiUrl(): Status {
		$options = [ 'timeout' => 2 ];
		$proxy = $this->options->get( 'HCaptchaProxy' );
		if ( $proxy ) {
			$options['proxy'] = $proxy;
		}
		$apiUrlRequest = $this->requestFactory->create(
			$this->options->get( 'HCaptchaApiUrl' ),
			$options,
			__METHOD__
		);
		$status = $apiUrlRequest->execute();
		if ( !$status->isGood() ) {
			return $status;
		}
		// Since we have the contents, verify that the integrity hash matches.
		$expectedIntegrityHash = $this->options->get( 'HCaptchaApiUrlIntegrityHash' );
		if ( $expectedIntegrityHash ) {
			[ $hashAlgorithm, $expectedIntegrityHashValue ] = explode( '-', $expectedIntegrityHash );
			if ( !in_array( $hashAlgorithm, [ 'sha256', 'sha384', 'sha512' ] ) ) {
				$status = Status::newFatal( new RawMessage( 'Invalid hash algorithm: $1', [ $hashAlgorithm ] ) );
				$status->value = self::INTEGRITY_FAILURE;
				return $status;
			}
			$actualIntegrityHash = base64_encode( hash( $hashAlgorithm, $apiUrlRequest->getContent(), true ) );
			if ( $expectedIntegrityHashValue !== $actualIntegrityHash ) {
				$status = Status::newFatal( new RawMessage( 'Integrity hash $1 does not match expected $2', [
					$actualIntegrityHash, $expectedIntegrityHashValue,
				] ) );
				$status->value = self::INTEGRITY_FAILURE;
				return $status;
			}
		}
		return Status::newGood();
	}

}
