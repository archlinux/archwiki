<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\MediaWikiServices;

class HCaptchaAuthenticationRequest extends CaptchaAuthenticationRequest {
	public function __construct() {
		parent::__construct( '', [] );
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		// unhack the hack in parent
		return AuthenticationRequest::loadFromSubmission( $data );
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		$ret = [
			'captchaWord' => [
				'type' => 'string',
			],
		];

		// Only display if there's potentially a captcha to solve or interact with...
		if ( !MediaWikiServices::getInstance()->getMainConfig()->get( 'HCaptchaPassiveMode' ) ) {
			$fieldInfo = parent::getFieldInfo();
			$ret['captchaWord']['label'] = $fieldInfo['captchaWord']['label'];
			$ret['captchaWord']['help'] = \wfMessage( 'hcaptcha-help' );
		}

		return $ret;
	}
}
