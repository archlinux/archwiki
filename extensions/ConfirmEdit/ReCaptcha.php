<?php

/**
 * Captcha class using the reCAPTCHA widget. 
 * Stop Spam. Read Books.  
 *
 * @addtogroup Extensions
 * @author Mike Crawford <mike.crawford@gmail.com>
 * @copyright Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * @licence MIT/X11
 */

if( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$wgExtensionMessagesFiles['ReCaptcha'] = dirname( __FILE__ ) . '/ReCaptcha.i18n.php';

require_once( 'recaptchalib.php' );

// Set these in LocalSettings.php
$wgReCaptchaPublicKey = '';
$wgReCaptchaPrivateKey = '';
// For backwards compatibility
$recaptcha_public_key = '';
$recaptcha_private_key = '';

$wgExtensionFunctions[] = 'efReCaptcha';

/**
 * Make sure the keys are defined.
 */
function efReCaptcha() {
	global $wgReCaptchaPublicKey, $wgReCaptchaPrivateKey;
	global $recaptcha_public_key, $recaptcha_private_key;
	global $wgServerName;

	// Backwards compatibility
	if ( $wgReCaptchaPublicKey == '' ) {
		$wgReCaptchaPublicKey = $recaptcha_public_key;
	}
	if ( $wgReCaptchaPrivateKey == '' ) {
		$wgReCaptchaPrivateKey = $recaptcha_private_key;
	}

	if ($wgReCaptchaPublicKey == '' || $wgReCaptchaPrivateKey == '') {
		die ('You need to set $wgReCaptchaPrivateKey and $wgReCaptchaPublicKey in LocalSettings.php to ' .
		     "use the reCAPTCHA plugin. You can sign up for a key <a href='" .
		     htmlentities(recaptcha_get_signup_url ($wgServerName, "mediawiki")) . "'>here</a>.");
	}	
}


class ReCaptcha extends SimpleCaptcha {

	//reCAPTHCA error code returned from recaptcha_check_answer
	private $recaptcha_error = null;

	/**
	 * Displays the reCAPTCHA widget.
         * If $this->recaptcha_error is set, it will display an error in the widget.
	 *
         */
	function getForm() {
		global $wgReCaptchaPublicKey;
		$useHttps = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );
		return "<script>var RecaptchaOptions = { tabindex : 1 }; </script> " .
			recaptcha_get_html($wgReCaptchaPublicKey, $this->recaptcha_error, $useHttps);
	}

	/**
	 * Calls the library function recaptcha_check_answer to verify the users input.
	 * Sets $this->recaptcha_error if the user is incorrect.
         * @return boolean
         *
         */
	function passCaptcha() {
		global $wgReCaptchaPrivateKey;
		$recaptcha_response = recaptcha_check_answer ($wgReCaptchaPrivateKey,
							      wfGetIP (),
							      $_POST['recaptcha_challenge_field'],
							      $_POST['recaptcha_response_field']);
                if (!$recaptcha_response->is_valid) {
			$this->recaptcha_error = $recaptcha_response->error;
			return false;
                }
		$recaptcha_error = null;
                return true;

	}

	/**
	 * Called on all edit page saves. (EditFilter events)
	 * @return boolean - true if page save should continue, false if should display Captcha widget.
	 */
	function confirmEdit( $editPage, $newtext, $section, $merged = false ) {
		if( $this->shouldCheck( $editPage, $newtext, $section ) ) {

			if (!isset($_POST['recaptcha_response_field'])) {
					//User has not yet been presented with Captcha, show the widget.
					$editPage->showEditForm( array( &$this, 'editCallback' ) );
					return false;
			}

			if( $this->passCaptcha() ) {
					return true;
			} else {
					//Try again - show the widget
					$editPage->showEditForm( array( &$this, 'editCallback' ) );
					return false;
			}

		} else {
			wfDebug( "ConfirmEdit: no need to show captcha.\n" );
			return true;
		}
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param $action Action being performed
	 * @return string
	 */
	function getMessage( $action ) {
		$name = 'recaptcha-' . $action;
		$text = wfMsg( $name );
		# Obtain a more tailored message, if possible, otherwise, fall back to
		# the default for edits
		return wfEmptyMsg( $name, $text ) ? wfMsg( 'recaptcha-edit' ) : $text;
	}

}
