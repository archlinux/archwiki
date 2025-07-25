<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLFormField;

class HTMLHCaptchaField extends HTMLFormField {
	/** @var string Public key parameter to be passed to hCaptcha. */
	protected $key;

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

		$this->key = $params['key'];
		$this->error = $params['error'];

		$this->mName = 'h-captcha-response';
	}

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		$out = $this->mParent->getOutput();

		$config = $this->mParent->getConfig();
		$url = $config->get( 'HCaptchaApiUrl' );
		$out->addHeadItem(
			'h-captcha',
			"<script src=\"$url\" async defer></script>"
		);
		HCaptcha::addCSPSources( $out->getCSP() );
		$output = Html::element( 'div', [
			'class' => [
				'h-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
			'data-sitekey' => $this->key,
		] );

		if ( $config->get( 'HCaptchaPassiveMode' ) ) {
			$output .= $this->getMessage( 'hcaptcha-privacy-policy' )->parse();
		}

		return $output;
	}
}
