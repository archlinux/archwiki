<?php

class ConfirmEditHooks {
	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	static function getInstance() {
		global $wgCaptcha, $wgCaptchaClass;

		static $done = false;

		if ( !$done ) {
			$done = true;
			$wgCaptcha = new $wgCaptchaClass;
		}

		return $wgCaptcha;
	}

	static function confirmEditMerged( $context, $content, $status, $summary, $user, $minorEdit ) {
		return self::getInstance()->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
	}

	static function confirmEditPage( $editpage, $buttons, $tabindex ) {
		self::getInstance()->editShowCaptcha( $editpage );
	}

	static function confirmEditAPI( $editPage, $newtext, &$resultArr ) {
		return self::getInstance()->confirmEditAPI( $editPage, $newtext, $resultArr );
	}

	static function showEditFormFields( &$editPage, &$out ) {
		return self::getInstance()->showEditFormFields( $editPage, $out );
	}

	static function addNewAccountApiForm( $apiModule, $loginForm ) {
		return self::getInstance()->addNewAccountApiForm( $apiModule, $loginForm );
	}

	static function addNewAccountApiResult( $apiModule, $loginPage, &$result ) {
		return self::getInstance()->addNewAccountApiResult( $apiModule, $loginPage, $result );
	}

	static function injectUserCreate( &$template ) {
		return self::getInstance()->injectUserCreate( $template );
	}

	static function confirmUserCreate( $u, &$message, &$status = null ) {
		return self::getInstance()->confirmUserCreate( $u, $message, $status );
	}

	static function triggerUserLogin( $user, $password, $retval ) {
		return self::getInstance()->triggerUserLogin( $user, $password, $retval );
	}

	static function injectUserLogin( &$template ) {
		return self::getInstance()->injectUserLogin( $template );
	}

	static function confirmUserLogin( $u, $pass, &$retval ) {
		return self::getInstance()->confirmUserLogin( $u, $pass, $retval );
	}

	static function injectEmailUser( &$form ) {
		return self::getInstance()->injectEmailUser( $form );
	}

	static function confirmEmailUser( $from, $to, $subject, $text, &$error ) {
		return self::getInstance()->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	// Default $flags to 1 for backwards-compatible behavior
	public static function APIGetAllowedParams( &$module, &$params, $flags = 1 ) {
		return self::getInstance()->APIGetAllowedParams( $module, $params, $flags );
	}

	public static function APIGetParamDescription( &$module, &$desc ) {
		return self::getInstance()->APIGetParamDescription( $module, $desc );
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @param array &$files
	 * @return boolean
	 */
	public static function onUnitTestsList( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = array();
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Set up $wgWhitelistRead
	 */
	public static function confirmEditSetup() {
		global $wgGroupPermissions, $wgCaptchaTriggers, $wgWikimediaJenkinsCI;

		// There is no need to run (core) tests with enabled ConfirmEdit - bug T44145
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI === true ) {
			$wgCaptchaTriggers = false;
		}

		if ( !$wgGroupPermissions['*']['read'] && $wgCaptchaTriggers['badlogin'] ) {
			// We need to ensure that the captcha interface is accessible
			// so that unauthenticated users can actually get in after a
			// mistaken password typing.
			global $wgWhitelistRead;
			$image = SpecialPage::getTitleFor( 'Captcha', 'image' );
			$help = SpecialPage::getTitleFor( 'Captcha', 'help' );
			$wgWhitelistRead[] = $image->getPrefixedText();
			$wgWhitelistRead[] = $help->getPrefixedText();
		}
	}
	/**
	 * Callback for extension.json of FancyCaptcha to set a default captcha directory,
	 * which depends on wgUploadDirectory
	 */
	public static function onFancyCaptchaSetup() {
		global $wgCaptchaDirectory, $wgUploadDirectory;
		if ( !$wgCaptchaDirectory ) {
			$wgCaptchaDirectory = "$wgUploadDirectory/captcha";
		}
	}

	/**
	 * Callback for extension.json of ReCaptcha to require the recaptcha library php file.
	 * FIXME: This should be done in a better way, e.g. only load the libraray, if really needed.
	 */
	public static function onReCaptchaSetup() {
		require_once( __DIR__ . '/../ReCaptcha/recaptchalib.php' );
	}

	/**
	 * Extension function, moved from ReCaptcha.php when that was decimated.
	 * Make sure the keys are defined.
	 */
	public static function efReCaptcha() {
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

		if ( $wgReCaptchaPublicKey == '' || $wgReCaptchaPrivateKey == '' ) {
			die ( 'You need to set $wgReCaptchaPrivateKey and $wgReCaptchaPublicKey in LocalSettings.php to ' .
				"use the reCAPTCHA plugin. You can sign up for a key <a href='" .
				htmlentities( recaptcha_get_signup_url ( $wgServerName, "mediawiki" ) ) . "'>here</a>." );
		}
	}
}
