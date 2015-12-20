<?php
class ReCaptchaNoCaptcha extends SimpleCaptcha {
	private $error = null;
	/**
	 * Get the captcha form.
	 * @return string
	 */
	function getForm( OutputPage $out ) {
		global $wgReCaptchaSiteKey;

		// Insert reCAPTCHA script.
		// See https://developers.google.com/recaptcha/docs/faq
		$out->addHeadItem(
			'g-recaptchascript',
			'<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
		);
		$output = Html::element( 'div', array(
			'class' => array(
				'g-recaptcha',
				'mw-confirmedit-captcha-fail' => !!$this->error,
			),
			'data-sitekey' => $wgReCaptchaSiteKey
		) );
		$htmlUrlencoded = htmlspecialchars( urlencode( $wgReCaptchaSiteKey ) );
		$output .= <<<HTML
<noscript>
  <div style="width: 302px; height: 422px;">
    <div style="width: 302px; height: 422px; position: relative;">
      <div style="width: 302px; height: 422px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k={$htmlUrlencoded}"
                frameborder="0" scrolling="no"
                style="width: 302px; height:422px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 300px; height: 60px; border-style: none;
                  bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;
                  background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 40px; border: 1px solid #c1c1c1;
                         margin: 10px 25px; padding: 0px; resize: none;" >
        </textarea>
      </div>
    </div>
  </div>
</noscript>
HTML;
		return $output;
	}

	protected function logCheckError( $info ) {
		if ( $info instanceof Status ) {
			$errors = $status->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = implode( ',', $info );
		} else {
			$error = $info;
		}
		wfDebugLog( 'captcha', 'Unable to validate response: ' . $error );
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php
	 *
	 * @return boolean
	 */
	function passCaptcha() {
		global $wgRequest, $wgReCaptchaSecretKey, $wgReCaptchaSendRemoteIP;

		$url = 'https://www.google.com/recaptcha/api/siteverify';
		// Build data to append to request
		$data = array(
			'secret' => $wgReCaptchaSecretKey,
			'response' => $wgRequest->getVal( 'g-recaptcha-response' ),
		);
		if ( $wgReCaptchaSendRemoteIP ) {
			$data['remoteip'] = $wgRequest->getIP();
		}
		$url = wfAppendQuery( $url, $data );
		$request = MWHttpRequest::factory( $url, array( 'method' => 'GET' ) );
		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->error = 'http';
			$this->logStatusError( $status );
			return false;
		}
		$response = FormatJson::decode( $request->getContent(), true );
		if ( !$response ) {
			$this->error = 'json';
			$this->logStatusError( $this->error );
			return false;
		}
		if ( isset( $response['error-codes'] ) ) {
			$this->error = 'recaptcha-api';
			$this->logCheckError( $response['error-codes'] );
			return false;
		}

		return $response['success'];
	}

	function addCaptchaAPI( &$resultArr ) {
		global $wgReCaptchaSiteKey;

		$resultArr['captcha']['type'] = 'recaptchanocaptcha';
		$resultArr['captcha']['mime'] = 'image/png';
		$resultArr['captcha']['key'] = $wgReCaptchaSiteKey;
		$resultArr['captcha']['error'] = $this->error;
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param $action string Action being performed
	 * @return string Wikitext
	 */
	function getMessage( $action ) {
		$name = 'renocaptcha-' . $action;
		$msg = wfMessage( $name );

		$text = $msg->isDisabled() ? wfMessage( 'renocaptcha-edit' )->text() : $msg->text();
		if ( $this->error ) {
			$text = '<div class="error">' . $text . '</div>';
		}
		return $text;
	}

	public function APIGetAllowedParams( &$module, &$params, $flags ) {
		if ( $flags && $this->isAPICaptchaModule( $module ) ) {
			$params['g-recaptcha-response'] = null;
		}

		return true;
	}

	public function APIGetParamDescription( &$module, &$desc ) {
		if ( $this->isAPICaptchaModule( $module ) ) {
			$desc['g-recaptcha-response'] = 'Field from the ReCaptcha widget';
		}

		return true;
	}
}
