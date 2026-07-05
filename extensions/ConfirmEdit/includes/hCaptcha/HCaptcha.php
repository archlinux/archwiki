<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaEnterpriseHealthChecker;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Language\RawMessage;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Stats\StatsFactory;

class HCaptcha extends SimpleCaptcha {
	/**
	 * @var string used for hcaptcha-edit, hcaptcha-addurl, hcaptcha-badlogin, hcaptcha-createaccount,
	 * hcaptcha-create, hcaptcha-sendemail via getMessage()
	 */
	protected static $messagePrefix = 'hcaptcha';

	/** @var string|null */
	private $error = null;

	private ?bool $result = null;
	private bool $resultUsesForceShowCaptchaSiteKey = false;

	private Config $hCaptchaConfig;
	private HttpRequestFactory $httpRequestFactory;
	private HCaptchaOutput $hCaptchaOutput;
	private StatsFactory $statsFactory;
	private LoggerInterface $logger;
	private HCaptchaEnterpriseHealthChecker $healthChecker;
	private BagOStuff $scoreCache;

	private const SCORE_CACHE_TTL = 300;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->hCaptchaConfig = $services->getMainConfig();
		$this->httpRequestFactory = $services->getHttpRequestFactory();
		$this->hCaptchaOutput = $services->get( 'HCaptchaOutput' );
		$this->statsFactory = $services->getStatsFactory();
		$this->healthChecker = $services->get( 'HCaptchaEnterpriseHealthChecker' );
		$this->logger = LoggerFactory::getInstance( 'captcha' );
		$this->scoreCache = $services->getObjectCacheFactory()->getLocalClusterInstance();
	}

	/** @inheritDoc */
	public function getFormInformation( $tabIndex = 1, ?OutputPage $out = null ) {
		if ( $out === null ) {
			$out = RequestContext::getMain()->getOutput();
		}

		return [
			'html' => $this->hCaptchaOutput->addHCaptchaToForm( $out, (bool)$this->error ),
		];
	}

	/** @inheritDoc */
	public static function getCSPUrls() {
		return RequestContext::getMain()->getConfig()->get( 'HCaptchaCSPRules' );
	}

	/** @inheritDoc */
	public static function addCSPSources( ContentSecurityPolicy $csp ) {
		foreach ( static::getCSPUrls() as $src ) {
			// Since frame-src is not supported
			$csp->addDefaultSrc( $src );
			$csp->addScriptSrc( $src );
			$csp->addStyleSrc( $src );
		}
	}

	protected function logCheckError( Status|array|string $info, UserIdentity $userIdentity, string $token ): void {
		if ( $info instanceof Status ) {
			$errors = $info->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = implode( ',', $info );
		} else {
			$error = $info;
		}

		$this->logger->error( 'Unable to validate response. Error: {error}', [
			'error' => $error,
			'user' => $userIdentity->getName(),
			'captcha_type' => self::$messagePrefix,
			'captcha_action' => $this->action ?? '-',
			'captcha_trigger' => $this->trigger ?? '-',
			'hcaptcha_token' => $token,
		] + RequestContext::getMain()->getRequest()->getSecurityLogContext( $userIdentity ) );
	}

	/** @inheritDoc */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		$response = $request->getVal(
			'h-captcha-response',
			// This is sad, but apparently all of these are valid
			$request->getVal( 'captchaWord',
				$request->getVal( 'captchaword',
					$request->getVal( 'wpCaptchaWord' )
				)
			)
		);
		return [ '', $response ];
	}

	/** @inheritDoc */
	public function shouldCheck( WikiPage $page, $content, $section, $context, $oldtext = null ) {
		// If the "showcaptcha" consequence has been invoked by AbuseFilter, and we have
		// already attempted to verify the token, return early. This ensures that we're
		// only going to verify the token once, because shouldCheck is invoked once in the EditFilterMergedContent
		// hook by AbuseFilter, and once by ConfirmEdit
		if (
			$context->getRequest()->getVal( 'wgConfirmEditForceShowCaptcha' ) &&
			$this->result !== null &&
			!( $this->result && !$this->resultUsesForceShowCaptchaSiteKey )
		) {
			return false;
		}
		return parent::shouldCheck( $page, $content, $section, $context, $oldtext );
	}

	/**
	 * Don't render hCaptcha form field at the top of the edit form, as we want it to be only
	 * ever at the bottom of the page.
	 *
	 * The default behaviour is to render the form fields both at the top and bottom of the edit field
	 * when in a 'addurl' trigger (which causes issues because we can only have one hCaptcha widget on the page).
	 *
	 * @inheritDoc
	 */
	public function showEditFormFields( EditPage $editPage, OutputPage $out ) {
	}

	/**
	 * Sets the value returned by {@link self::shouldForceShowCaptcha}.
	 *
	 * Additionally, for edits with the flag set to true, the JS configuration
	 * variable wgHCaptchaTriggerFormSubmission is set cause the frontend
	 * to immediately submit the form. That is done to avoid the user needing to
	 * resubmit the form when an AbuseFilter requires a different site key to be
	 * used for the edit.
	 *
	 * @inheritDoc
	 */
	public function setForceShowCaptcha( bool $forceShowCaptcha ): void {
		parent::setForceShowCaptcha( $forceShowCaptcha );

		$isPageSubmission = in_array( $this->action, [ 'edit', 'create' ] );

		if ( $isPageSubmission && $forceShowCaptcha ) {
			$output = RequestContext::getMain()->getOutput();
			$output->addJsConfigVars(
				'wgHCaptchaTriggerFormSubmission',
				true
			);
		}
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php and https://docs.hcaptcha.com/
	 *
	 * @param mixed $_ Not used
	 * @param null|string $token token from the POST data
	 * @param UserIdentity $user
	 * @return bool
	 */
	protected function passCaptcha( $_, $token, $user ) {
		$webRequest = RequestContext::getMain()->getRequest();
		// If we have a result, "showcaptcha" consequence has been invoked, but the submission
		// is in the context of a request where the user wasn't yet required to complete a CAPTCHA,
		// then return false to avoid making a duplicate API request, and to ensure that the user
		// has to complete the "always challenge" CAPTCHA.
		if ( $this->result &&
			$this->shouldForceShowCaptcha() &&
			$webRequest->getVal( 'wgConfirmEditForceShowCaptcha' ) === null ) {
			// Set an error here, so that the page will display an appropriate
			// message for the user to resubmit the form.
			$this->error = 'forceshowcaptcha';
			return false;
		}

		if ( !$token ) {
			$this->error = 'missing-token';
			$this->logger->warning(
				'No hCaptcha token present in the request; skipping siteverify call.',
				[
					'error' => $this->error,
					'user' => $user->getName(),
					'captcha_type' => self::$messagePrefix,
					'captcha_action' => $this->action ?? '-',
					'captcha_trigger' => $this->trigger ?? '-',
				] + $webRequest->getSecurityLogContext( $user )
			);
			return false;
		}
		$data = [
			'secret' => $this->hCaptchaConfig->get( 'HCaptchaSecretKey' ),
			'response' => $token,
		];
		$data['remoteip'] = '127.0.0.1';
		if ( $this->hCaptchaConfig->get( 'HCaptchaSendRemoteIP' ) ) {
			$data['remoteip'] = $webRequest->getIP();
		}

		$options = [
			'method' => 'POST',
			'postData' => $data,
			'timeout' => 1,
		];

		$proxy = $this->hCaptchaConfig->get( 'HCaptchaProxy' );
		if ( $proxy ) {
			$options['proxy'] = $proxy;
		}

		$verifyUrl = $this->hCaptchaConfig->get( 'HCaptchaVerifyUrl' );

		// Initial attempt plus up to two retries, each preceded by a 10ms delay.
		$maxAttempts = 3;
		$attempt = 1;
		$status = $this->executeSiteVerifyRequest( $verifyUrl, $options, false );
		while ( !$status->isOK() && $attempt < $maxAttempts ) {
			$this->logger->warning(
				'SiteVerify API request failed on attempt {attempt} of {maxAttempts}, retrying. Error: {error}',
				[
					'attempt' => $attempt,
					'maxAttempts' => $maxAttempts,
					'error' => $status->getMessage()->text(),
				]
			);
			usleep( 10_000 );
			$status = $this->executeSiteVerifyRequest( $verifyUrl, $options, true );
			$attempt++;
		}

		if ( !$status->isOK() ) {
			$this->logger->error(
				'All SiteVerify API attempts failed. Error: {error}',
				[ 'error' => $status->getMessage()->text() ]
			);
			$this->statsFactory->withComponent( 'ConfirmEdit' )
				->getCounter( 'hcaptcha_siteverify_exhausted_total' )
				->increment();
			$this->error = 'http';
			$this->healthChecker->incrementSiteVerifyApiErrorCount();
			$this->logCheckError( $status, $user, $token );
			return false;
		}

		if ( $attempt > 1 ) {
			$this->logger->info(
				'SiteVerify API call succeeded on attempt {attempt} of {maxAttempts} after initial failure',
				[
					'attempt' => $attempt,
					'maxAttempts' => $maxAttempts,
				]
			);
		}
		$json = FormatJson::decode( $status->getValue(), true );
		if ( !$json ) {
			$this->error = 'json';
			$this->healthChecker->incrementSiteVerifyApiErrorCount();
			$this->logCheckError( $this->error, $user, $token );
			return false;
		}
		if ( isset( $json['error-codes'] ) ) {
			$this->error = 'hcaptcha-api';
			$this->logCheckError( $json['error-codes'], $user, $token );
			return false;
		}

		// Verify that the sitekey is among those allowed in order to prevent
		// client-side tampering (T410024, T410657).
		$siteKeyUsed = $json['sitekey'] ?? null;

		if ( !in_array( $siteKeyUsed, $this->getAllowedSiteKeysForCurrentAction() ) ) {
			$this->error = 'sitekey-mismatch';
			$this->logCheckError( $this->error, $user, $token );
			return false;
		}

		$debugLogContext = [
			'event' => 'captcha.solve',
			'user' => $user->getName(),
			'hcaptcha_success' => $json['success'],
			'hcaptcha_token' => $token,
			'captcha_type' => self::$messagePrefix,
			'success_message' => $json['success'] ? 'Successful' : 'Failed',
			'captcha_action' => $this->action ?? '-',
			'captcha_trigger' => $this->trigger ?? '-',
			'hcaptcha_response_sitekey' => $json['sitekey'] ?? '-',
		] + $webRequest->getSecurityLogContext( $user );
		if ( $this->hCaptchaConfig->get( 'HCaptchaDeveloperMode' ) ) {
			$debugLogContext = array_merge( [
				'hcaptcha_score' => $json['score'] ?? null,
				'hcaptcha_score_reason' => $json['score_reason'] ?? null,
				'hcaptcha_blob' => $json,
			], $debugLogContext );
		}
		$this->logger->info( '{success_message} captcha solution attempt for {user}', $debugLogContext );

		if ( $this->hCaptchaConfig->get( 'HCaptchaDeveloperMode' )
			|| $this->hCaptchaConfig->get( 'HCaptchaUseRiskScore' ) ) {
			// T398333
			$this->storeSessionScore( 'hCaptcha-score', $json['score'] ?? null, $user->getName() );
		}
		$this->result = $json['success'];
		$this->resultUsesForceShowCaptchaSiteKey = $this->shouldForceShowCaptcha();
		return $json['success'];
	}

	/** @inheritDoc */
	protected function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType( $this->action );
		$resultArr['captcha']['error'] = $this->error;
	}

	/** @inheritDoc */
	public function describeCaptchaType( ?string $action = null ) {
		return [
			'type' => 'hcaptcha',
			'mime' => 'application/javascript',
			'key' => $this->getSiteKeyForAction(),
		];
	}

	/** @inheritDoc */
	public function getMessage( $action ) {
		if ( $this->error ) {
			if ( $this->shouldForceShowCaptcha() &&
				in_array( $action, [ CaptchaTriggers::EDIT, CaptchaTriggers::CREATE ] ) ) {
				$msg = wfMessage( 'hcaptcha-force-show-captcha-edit' );
			} else {
				$msg = parent::getMessage( $action );
			}
			return new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}

		// For edit action, hide the prompt if there's no error
		if ( $action === CaptchaTriggers::EDIT ) {
			return new RawMessage( '' );
		}

		return parent::getMessage( $action );
	}

	/** @inheritDoc */
	protected function getConfirmEditMergedFatalStatusMessageKey(): null|string {
		if ( !$this->shouldForceShowCaptcha() ) {
			return parent::getConfirmEditMergedFatalStatusMessageKey();
		}

		// If the hCaptcha captcha is being force shown, then this may cause the user to have to submit the form
		// twice (so that the second attempt always shows a challenge). In this case, we need a clear message
		// to show that the user needs to press submit again.
		return 'hcaptcha-force-show-captcha-edit';
	}

	/** @inheritDoc */
	protected function isAPICaptchaModule( $module ) {
		return parent::isAPICaptchaModule( $module )
			|| $module->getModuleName() === 'visualeditoredit';
	}

	/** @inheritDoc */
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
		parent::apiGetAllowedParams( $module, $params, $flags );

		if ( $this->isAPICaptchaModule( $module ) ) {
			$params['wgConfirmEditForceShowCaptcha'] = [
				ParamValidator::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_HELP_MSG => 'confirmedit-hcaptcha-apihelp-param-forceshowcaptcha',
			];
		}

		return true;
	}

	/** @inheritDoc */
	public function getError() {
		return $this->error;
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function storeCaptcha( $info ) {
		// hCaptcha is stored externally, the ID will be generated at that time as well, and
		// the one returned here won't be used. Just pretend this worked.
		return 'not used';
	}

	/**
	 * Store risk score in global session. If a username is provided, the score will be
	 * also recorded in the cache, so that any jobs can read from it.
	 * @param string $sessionKey
	 * @param mixed $score
	 * @param string|null $userName
	 * @return void
	 */
	public function storeSessionScore( $sessionKey, $score, $userName = null ) {
		SessionManager::getGlobalSession()->set( $sessionKey, $score );
		if ( $userName !== null ) {
			$this->scoreCache->set(
				$this->getScoreCacheKey( $sessionKey, $userName ),
				$score,
				self::SCORE_CACHE_TTL
			);
		}
	}

	/**
	 * Retrieve session score from global session. If a username is provided, it will attempt
	 * to read the score from the cache, if it's not present in the session.
	 *
	 * @stable to call - This may be used by code not visible in codesearch
	 * @param string $sessionKey
	 * @param string|null $userName
	 * @return mixed
	 */
	public function retrieveSessionScore( $sessionKey, $userName = null ) {
		$score = SessionManager::getGlobalSession()->get( $sessionKey );
		if ( $score !== null ) {
			return $score;
		}
		if ( $userName !== null ) {
			return $this->scoreCache->get( $this->getScoreCacheKey( $sessionKey, $userName ) );
		}
		return null;
	}

	private function getScoreCacheKey( string $sessionKey, string $userName ): string {
		return $this->scoreCache->makeGlobalKey( 'hcaptcha-score', $sessionKey . '-' . $userName );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function retrieveCaptcha( $index ) {
		// Just pretend it worked
		return [ 'index' => $index ];
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function getCaptcha() {
		// hCaptcha is handled by frontend code, and an external provider; nothing to do here.
		return [];
	}

	/**
	 * @return HCaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		return new HCaptchaAuthenticationRequest();
	}

	/** @inheritDoc */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		/** @var CaptchaAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$requests,
			CaptchaAuthenticationRequest::class,
			true
		);
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = Hooks::getInstance( $req->getAction() );

		$formDescriptor['captchaWord'] = [
			'class' => HTMLHCaptchaField::class,
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaWord'];
	}

	/** @inheritDoc */
	public function showHelp( OutputPage $out ) {
		$out->addWikiMsg( 'hcaptcha-privacy-policy' );
	}

	/**
	 * Get the SiteKey for the instance.
	 *
	 * This returns a value from the following sources, in order of priority:
	 * - the HCaptchaAlwaysChallengeSiteKey from $wgCaptchaTriggers for the current action,
	 *   if ::shouldForceShowCaptcha mode is enabled
	 * - the HCaptchaSiteKey config property from $wgCaptchaTriggers for the current action
	 * - the global $wgHCaptchaSiteKey
	 *
	 * @return string The hCaptcha SiteKey associated with this instance
	 */
	public function getSiteKeyForAction(): string {
		$siteKey = $this->getPrimarySiteKey();
		if ( $this->shouldForceShowCaptcha() ) {
			$siteKey = $this->getConfig()['HCaptchaAlwaysChallengeSiteKey'] ?? $siteKey;
		}

		return $siteKey;
	}

	/**
	 * Execute a SiteVerify API request and record timing metrics.
	 *
	 * On success, the response body is available via {@see Status::getValue()}.
	 */
	private function executeSiteVerifyRequest(
		string $verifyUrl,
		array $options,
		bool $isRetry
	): Status {
		$request = $this->httpRequestFactory->create( $verifyUrl, $options, __METHOD__ );

		$timer = $this->statsFactory->withComponent( 'ConfirmEdit' )
			->getTiming( 'hcaptcha_siteverify_call' )
			->start();

		$status = $request->execute();

		$timer
			->setLabel( 'status', $status->isOK() ? 'ok' : 'failed' )
			->setLabel( 'is_retry', $isRetry ? 'true' : 'false' )
			->stop();

		if ( $status->isOK() ) {
			$status->value = $request->getContent();
		}

		return $status;
	}

	/**
	 * Get a list of allowed SiteKeys for the current action.
	 *
	 * If Always Challenge Mode is enabled and HCaptchaAlwaysChallengeSiteKey is set,
	 * this method returns an array containing only that key. If Always Challenge
	 * Mode is enabled but HCaptchaAlwaysChallengeSiteKey is not set, this method
	 * falls back to normal mode behavior (primary key plus additional keys).
	 *
	 * Otherwise, this returns a list containing the primary SiteKey for the current
	 * action plus any additional key provided under the HCaptchaAdditionalValidSiteKeys
	 * configuration key for the current action.
	 *
	 * @return string[] A list of allowed hCaptcha SiteKeys allowed for this instance
	 */
	private function getAllowedSiteKeysForCurrentAction(): array {
		$triggerConfig = $this->getConfig();

		// In Always Challenge Mode, return only the Always Challenge SiteKey if set.
		// If not set, fallback to normal mode behavior to avoid unexpected edge cases.
		if ( $this->shouldForceShowCaptcha() ) {
			if ( isset( $triggerConfig['HCaptchaAlwaysChallengeSiteKey'] ) ) {
				return [ $triggerConfig['HCaptchaAlwaysChallengeSiteKey'] ];
			}
			// Fall through to normal mode behavior
		}

		// For normal mode, return the primary SiteKey for the current action
		// as well as any additional keys listed as valid for it.
		$allowedKeys = array_merge(
			// Use getPrimarySiteKey() which handles fallback to global config
			[ $this->getPrimarySiteKey() ],
			// Include HCaptchaAlwaysChallengeSiteKey, because the second POST
			// after an AbuseFilter challenge will be using this SiteKey
			[ $triggerConfig['HCaptchaAlwaysChallengeSiteKey'] ?? '' ],
			// Retrieve any additional key listed as allowed for the requested action
			// (i.e. those at self::getConfig()['HCaptchaAdditionalValidSiteKeys']).
			$triggerConfig['HCaptchaAdditionalValidSiteKeys'] ?? []
		);

		// Remove duplicates and empty values, if any
		return array_values( array_filter( array_unique( $allowedKeys ) ) );
	}

	/**
	 * Returns the hCaptcha primary SiteKey to be used by this instance.
	 *
	 * The primary SiteKey is either the value for HCaptchaSiteKey set in the
	 * trigger config for the current action or, if not set or empty, the HCaptchaSiteKey
	 * set in the root-level configuration.
	 *
	 * @return string
	 */
	private function getPrimarySiteKey(): string {
		$key = $this->getConfig()['HCaptchaSiteKey'] ??
			$this->hCaptchaConfig->get( 'HCaptchaSiteKey' );

		if ( $key === null ) {
			throw new LogicException( 'wgHCaptchaSiteKey is not set' );
		}

		return $key;
	}
}
