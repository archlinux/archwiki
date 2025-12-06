<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use MediaWiki\Extension\ConfirmEdit\hCaptcha\Services\HCaptchaOutput;
use MediaWiki\HTMLForm\HTMLFormField;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;

class HTMLHCaptchaField extends HTMLFormField {
	/** @var string Error returned by hCaptcha in the previous round. */
	protected $error;

	/**
	 * Parameters:
	 * - key: (string, required) Public key
	 * - error: (string) Error from the previous captcha round
	 * @param array $params
	 */
	public function __construct( array $params ) {
		$params += [ 'error' => null ];
		parent::__construct( $params );

		$this->error = $params['error'];

		$this->mName = 'h-captcha-response';
	}

	/**
	 * Show a more informative error for form submissions without an hCaptcha token,
	 * which may be from non-JS clients.
	 * @inheritDoc
	 */
	public function validate( $value, $alldata ): bool|Message|string {
		if ( !$value ) {
			return $this->msg( 'hcaptcha-missing-token' );
		}

		return parent::validate( $value, $alldata );
	}

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		$out = $this->mParent->getOutput();

		/** @var HCaptchaOutput $output */
		$output = MediaWikiServices::getInstance()->get( 'HCaptchaOutput' )
			->addHCaptchaToForm( $out, (bool)$this->error );
		HCaptcha::addCSPSources( $out->getCSP() );

		return $output;
	}
}
