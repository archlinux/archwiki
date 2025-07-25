<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use MediaWiki\Api\ApiBase;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\Language\RawMessage;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;

class HCaptcha extends SimpleCaptcha {
	/**
	 * @var string used for hcaptcha-edit, hcaptcha-addurl, hcaptcha-badlogin, hcaptcha-createaccount,
	 * hcaptcha-create, hcaptcha-sendemail via getMessage()
	 */
	protected static $messagePrefix = 'hcaptcha-';

	/** @var string|null */
	private $error = null;

	/** @var Config */
	private $hCaptchaConfig;

	/** @var string */
	private $siteKey;

	public function __construct() {
		$this->hCaptchaConfig = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'hcaptcha' );
		$this->siteKey = $this->hCaptchaConfig->get( 'HCaptchaSiteKey' );
	}

	/**
	 * Get the captcha form.
	 * @param int $tabIndex
	 * @return array
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		$output = Html::element( 'div', [
			'class' => [
				'h-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
			'data-sitekey' => $this->siteKey
		] );

		if ( $this->hCaptchaConfig->get( 'HCaptchaPassiveMode' ) ) {
			// Uses hcaptcha-privacy-policy; but not called with hcaptcha-prefix
			$output .= $this->getMessage( 'privacy-policy' )->parse();
		}

		$url = $this->hCaptchaConfig->get( 'HCaptchaApiUrl' );
		return [
			'html' => $output,
			'headitems' => [
				"<script src=\"$url\" async defer></script>"
			]
		];
	}

	/** @inheritDoc */
	public static function getCSPUrls() {
		return RequestContext::getMain()->getConfig()->get( 'HCaptchaCSPRules' );
	}

	/**
	 * Adds the CSP policies that are necessary for the captcha module to work in a CSP enforced
	 * setup.
	 *
	 * @param ContentSecurityPolicy $csp The CSP instance to add the policies to, this is usually to be
	 * obtained from {@link OutputPage::getCSP()}
	 */
	public static function addCSPSources( ContentSecurityPolicy $csp ) {
		foreach ( static::getCSPUrls() as $src ) {
			// Since frame-src is not supported
			$csp->addDefaultSrc( $src );
			$csp->addScriptSrc( $src );
			$csp->addStyleSrc( $src );
		}
	}

	/**
	 * @param Status|array|string $info
	 */
	protected function logCheckError( $info ) {
		if ( $info instanceof Status ) {
			$errors = $info->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = implode( ',', $info );
		} else {
			$error = $info;
		}

		\wfDebugLog( 'captcha', 'Unable to validate response: ' . $error );
	}

	/**
	 * @param WebRequest $request
	 * @return array
	 */
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
		if ( $this->hCaptchaConfig->get( 'HCaptchaSendRemoteIP' ) ) {
			$webRequest = RequestContext::getMain()->getRequest();
			$data['remoteip'] = $webRequest->getIP();
		}

		$options = [
			'method' => 'POST',
			'postData' => $data,
		];

		$proxy = $this->hCaptchaConfig->get( 'HCaptchaProxy' );
		if ( $proxy ) {
			$options['proxy'] = $proxy;
		}

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $this->hCaptchaConfig->get( 'HCaptchaVerifyUrl' ), $options, __METHOD__ );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->error = 'http';
			$this->logCheckError( $status );
			return false;
		}
		$json = FormatJson::decode( $request->getContent(), true );
		if ( !$json ) {
			$this->error = 'json';
			$this->logCheckError( $this->error );
			return false;
		}
		if ( isset( $json['error-codes'] ) ) {
			$this->error = 'hcaptcha-api';
			$this->logCheckError( $json['error-codes'] );
			return false;
		}

		LoggerFactory::getInstance( 'captcha' )
			->debug( 'Captcha solution attempt for {user}', [
				'event' => 'captcha.solve',
				'user' => $user->getName(),
				'hcaptcha_success' => $json['success'],
				'hcaptcha_score' => $json['score'] ?? null,
				'hcaptcha_score_reason' => $json['score_reason'] ?? null,
				'hcaptcha_blob' => $json,
			] );

		return $json['success'];
	}

	/**
	 * @param array &$resultArr
	 */
	protected function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['error'] = $this->error;
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		return [
			'type' => 'hcaptcha',
			'mime' => 'application/javascript',
			'key' => $this->siteKey,
		];
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param string $action Action being performed
	 * @return Message
	 */
	public function getMessage( $action ) {
		$msg = parent::getMessage( $action );
		if ( $this->error ) {
			$msg = new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}
		return $msg;
	}

	/**
	 * @param ApiBase $module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
		return true;
	}

	/** @inheritDoc */
	public function getError() {
		return $this->error;
	}

	/** @inheritDoc */
	public function storeCaptcha( $info ) {
		// hCaptcha is stored externally, the ID will be generated at that time as well, and
		// the one returned here won't be used. Just pretend this worked.
		return 'not used';
	}

	/** @inheritDoc */
	public function retrieveCaptcha( $index ) {
		// Just pretend it worked
		return [ 'index' => $index ];
	}

	/** @inheritDoc */
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

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		$req = AuthenticationRequest::getRequestByClass(
			$requests,
			CaptchaAuthenticationRequest::class,
			true
		);
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = Hooks::getInstance();

		$formDescriptor['captchaWord'] = [
			'class' => HTMLHCaptchaField::class,
			'key' => $this->siteKey,
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaWord'];
	}
}
