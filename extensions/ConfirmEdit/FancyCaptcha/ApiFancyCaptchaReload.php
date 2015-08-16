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

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Get a new FancyCaptcha.';
	}

	public function getAllowedParams() {
		return array();
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array( 'api.php?action=fancycaptchareload&format=xml' );
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=fancycaptchareload'
				=> 'apihelp-fancycaptchareload-example-1',
		);
	}
}
