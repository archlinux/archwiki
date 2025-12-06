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
	];
	private const CACHE_SITEVERIFY_ERROR_COUNT_KEY = 'confirmedit-hcaptcha-siteverify-error-count';
	private const CONFIRMEDIT_HCAPTCHA_FAILOVER_MODE = 'confirmedit-hcaptcha-failover-mode';

	private ?bool $isAvailable = null;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly BagOStuff $bagOStuffCache,
		private readonly WANObjectCache $wanObjectCache,
		private readonly HttpRequestFactory $requestFactory,
		private readonly FormatterFactory $formatterFactory,
		private readonly StatsFactory $statsFactory
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

		$inFailoverMode = (bool)$this->wanObjectCache->get(
			$this->wanObjectCache->makeGlobalKey( self::CONFIRMEDIT_HCAPTCHA_FAILOVER_MODE )
		);

		// If we're in failover mode, don't do any other checks.
		if ( $inFailoverMode ) {
			$timer->setLabel( 'result', 'false' )->stop();
			$this->isAvailable = false;
			return false;
		}

		// The SiteVerify request error count is incremented in HCaptcha::passCaptcha(),
		// where we increment the count when the SiteVerify request fails with an http or
		// json-decode error
		$failedSiteVerifyRequestCount = (int)$this->bagOStuffCache->get( $this->bagOStuffCache->makeGlobalKey(
			self::CACHE_SITEVERIFY_ERROR_COUNT_KEY
		) );
		$siteVerifyErrorThreshold = $this->options->get(
			'HCaptchaEnterpriseHealthCheckSiteVerifyErrorThreshold'
		);
		if ( $failedSiteVerifyRequestCount >= $siteVerifyErrorThreshold ) {
			$this->setFailoverMode();
			$this->logger->warning(
				'hCaptcha unavailable due to SiteVerify errors: {count} >= {threshold}',
				[ 'count' => $failedSiteVerifyRequestCount, 'threshold' => $siteVerifyErrorThreshold ]
			);
			$this->isAvailable = false;
			$timer->setLabel( 'result', 'false' )->stop();
			return false;
		}

		// SiteVerify is OK, now we should also check that the hCaptcha API JavaScript file
		// is available.
		$this->isAvailable = (bool)$this->wanObjectCache->getWithSetCallback(
			$this->wanObjectCache->makeGlobalKey( 'confirmedit-hcaptcha-apiurl-available' ),
			$this->wanObjectCache::TTL_MINUTE * 5,
			function () {
				$start = microtime( true );
				$retried = false;
				$apiUrlStatus = $this->checkApiUrl();
				if ( !$apiUrlStatus->isGood() ) {
					$retried = true;
					// Give it a second try, in case of intermittent network issues.
					$apiUrlStatus = $this->checkApiUrl();
				}
				$this->statsFactory->withComponent( 'ConfirmEdit' )
					->getTiming( 'hcaptcha_enterprise_health_checker__api_url_available_seconds' )
					->setLabel( 'retry', $retried ? '1' : '0' )
					->observeSeconds( ( microtime( true ) - $start ) );
				if ( !$apiUrlStatus->isGood() ) {
					$this->setFailoverMode();
					$statusFormatter = $this->formatterFactory->getStatusFormatter( RequestContext::getMain() );
					$this->logger->error( ...$statusFormatter->getPsr3MessageAndContext( $apiUrlStatus, [
						'hcaptcha_health_check_type' => 'apiUrl',
					] ) );
					return 0;
				}
				return 1;
			},
			[
				// Regenerating the value should take ~4-5 seconds at most.
				'lockTSE' => 10,
				// Default to assuming availability while the value is regenerated
				'busyValue' => 1,
			]
		);

		$timer->setLabel( 'result', $this->isAvailable ? 'true' : 'false' )->stop();
		return $this->isAvailable;
	}

	private function setFailoverMode(): void {
		$this->logger->warning( 'Entering failover mode' );
		$this->wanObjectCache->set(
			$this->wanObjectCache->makeGlobalKey( self::CONFIRMEDIT_HCAPTCHA_FAILOVER_MODE ),
			true,
			$this->wanObjectCache::TTL_MINUTE * 10
		);
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
			$statusFormatter = $this->formatterFactory->getStatusFormatter( RequestContext::getMain() );
			$this->logger->error( ...$statusFormatter->getPsr3MessageAndContext( $status, [
				'hcaptcha_health_check_type' => 'apiUrl',
			] ) );
			return $status;
		}
		// Since we have the contents, verify that the integrity hash matches.
		$expectedIntegrityHash = $this->options->get( 'HCaptchaApiUrlIntegrityHash' );
		if ( $expectedIntegrityHash ) {
			[ $hashAlgorithm, $expectedIntegrityHashValue ] = explode( '-', $expectedIntegrityHash );
			if ( !in_array( $hashAlgorithm, [ 'sha256', 'sha384', 'sha512' ] ) ) {
				return Status::newFatal( new RawMessage( 'Invalid hash algorithm: $1', [ $hashAlgorithm ] ) );
			}
			$actualIntegrityHash = base64_encode( hash( $hashAlgorithm, $apiUrlRequest->getContent(), true ) );
			if ( $expectedIntegrityHashValue !== $actualIntegrityHash ) {
				return Status::newFatal( new RawMessage( 'Integrity hash $1 does not match expected $2', [
					$actualIntegrityHash, $expectedIntegrityHashValue,
				] ) );
			}
		}
		return Status::newGood();
	}

}
