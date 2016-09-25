<?php
/**
 * Api module to reload FancyCaptcha
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiFancyCaptchaReload extends ApiBase {
	public function execute() {
		# Get a new FancyCaptcha form data
		$captcha = new FancyCaptcha();
		$info = $captcha->getCaptcha();
		$captchaIndex = $captcha->storeCaptcha( $info );

		$result = $this->getResult();
		$result->addValue( null, $this->getModuleName(), [ 'index' => $captchaIndex ] );
		return true;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Get a new FancyCaptcha.';
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return [ 'api.php?action=fancycaptchareload&format=xml' ];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=fancycaptchareload'
				=> 'apihelp-fancycaptchareload-example-1',
		];
	}
}
