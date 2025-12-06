<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use MediaWiki\Api\ApiBase;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaEnterpriseHealthChecker;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Json\FormatJson;
use MediaWiki\Language\RawMessage;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Stats\StatsFactory;

class HCaptcha extends SimpleCaptcha {
	/**
	 * @var string used for hcaptcha-edit, hcaptcha-addurl, hcaptcha-badlogin, hcaptcha-createaccount,
	 * hcaptcha-create, hcaptcha-sendemail via getMessage()
	 */
	protected static $messagePrefix = 'hcaptcha';

	/** @var string|null */
	private $error = null;

	private Config $hCaptchaConfig;
	private HCaptchaOutput $hCaptchaOutput;
	private StatsFactory $statsFactory;
	private LoggerInterface $logger;
	private HCaptchaEnterpriseHealthChecker $healthChecker;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->hCaptchaConfig = $services->getMainConfig();
		$this->hCaptchaOutput = $services->get( 'HCaptchaOutput' );
		$this->statsFactory = $services->getStatsFactory();
		$this->healthChecker = $services->get( 'HCaptchaEnterpriseHealthChecker' );
		$this->logger = LoggerFactory::getInstance( 'captcha' );
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

	protected function logCheckError( Status|array|string $info, UserIdentity $userIdentity ): void {
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
		] );
	}

	/** @inheritDoc */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		$response = $request->getVal(
			'h-captcha-response',
			$request->getVal( 'captchaWord', $request->getVal( 'captchaword' ) )
		);
		return [ '', $response ];
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php and https://docs.hcaptcha.com/
	 *
	 * @param mixed $_ Not used
	 * @param string $token token from the POST data
	 * @param UserIdentity $user
	 * @return bool
	 */
	protected function passCaptcha( $_, $token, $user ) {
		$data = [
			'secret' => $this->hCaptchaConfig->get( 'HCaptchaSecretKey' ),
			'response' => $token,
		];
		$data['remoteip'] = '127.0.0.1';
		if ( $this->hCaptchaConfig->get( 'HCaptchaSendRemoteIP' ) ) {
			$webRequest = RequestContext::getMain()->getRequest();
			$data['remoteip'] = $webRequest->getIP();
		}

		$options = [
			'method' => 'POST',
			'postData' => $data,
			'timeout' => 5,
		];

		$proxy = $this->hCaptchaConfig->get( 'HCaptchaProxy' );
		if ( $proxy ) {
			$options['proxy'] = $proxy;
		}

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $this->hCaptchaConfig->get( 'HCaptchaVerifyUrl' ), $options, __METHOD__ );

		$timer = $this->statsFactory->withComponent( 'ConfirmEdit' )
			->getTiming( 'hcaptcha_siteverify_call' )
			->start();

		$status = $request->execute();

		$timer
			->setLabel( 'status', $status->isOK() ? 'ok' : 'failed' )
			->stop();

		if ( !$status->isOK() ) {
			$this->error = 'http';
			$this->healthChecker->incrementSiteVerifyApiErrorCount();
			$this->logCheckError( $status, $user );
			return false;
		}
		$json = FormatJson::decode( $request->getContent(), true );
		if ( !$json ) {
			$this->error = 'json';
			$this->healthChecker->incrementSiteVerifyApiErrorCount();
			$this->logCheckError( $this->error, $user );
			return false;
		}
		if ( isset( $json['error-codes'] ) ) {
			$this->error = 'hcaptcha-api';
			$this->logCheckError( $json['error-codes'], $user );
			return false;
		}

		$debugLogContext = [
			'event' => 'captcha.solve',
			'user' => $user->getName(),
			'hcaptcha_success' => $json['success'],
			'captcha_type' => self::$messagePrefix,
			'success_message' => $json['success'] ? 'Successful' : 'Failed',
		];
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
			$this->storeSessionScore( 'hCaptcha-score', $json['score'] ?? null );
		}
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
			'key' => $this->getConfig()['HCaptchaSiteKey'] ?? $this->hCaptchaConfig->get( 'HCaptchaSiteKey' ),
		];
	}

	/** @inheritDoc */
	public function getMessage( $action ) {
		if ( $this->error ) {
			$msg = parent::getMessage( $action );
			return new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}

		// For edit action, hide the prompt if there's no error
		if ( $action === CaptchaTriggers::EDIT ) {
			return new RawMessage( '' );
		}

		return parent::getMessage( $action );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
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
	 * Store risk score in global session
	 * @param string $sessionKey
	 * @param mixed $score
	 * @return void
	 */
	public function storeSessionScore( $sessionKey, $score ) {
		SessionManager::getGlobalSession()->set( $sessionKey, $score );
	}

	/**
	 * Retrieve session score from global session
	 *
	 * @stable to call - This may be used by code not visible in codesearch
	 * @param string $sessionKey
	 * @return mixed
	 */
	public function retrieveSessionScore( $sessionKey ) {
		return SessionManager::getGlobalSession()->get( $sessionKey );
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
}
