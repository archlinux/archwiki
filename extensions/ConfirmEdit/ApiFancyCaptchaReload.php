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
		$captchaIndex = $captcha->getCaptchaIndex();

		$result = $this->getResult();
		$result->addValue( null, $this->getModuleName(), array ( 'index' => $captchaIndex ) );
		return true;
	}

	public function getDescription() {
		return 'Get a new FancyCaptcha.';
	}

	public function getAllowedParams() {
		return array();
	}

	public function getParamDescription() {
		return array();
	}

	public function getExamples() {
		return array( 'api.php?action=fancycaptchareload&format=xml' );
	}
}
