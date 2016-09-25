<?php

use MediaWiki\Auth\AuthManager;

class ConfirmEditHooks {
	protected static $instanceCreated = false;

	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	public static function getInstance() {
		global $wgCaptcha, $wgCaptchaClass;

		if ( !static::$instanceCreated ) {
			static::$instanceCreated = true;
			$wgCaptcha = new $wgCaptchaClass;
		}

		return $wgCaptcha;
	}

	/**
	 * Registers conditional hooks.
	 */
	public static function onRegistration() {
		global $wgDisableAuthManager, $wgAuthManagerAutoConfig;

		if ( class_exists( AuthManager::class ) && !$wgDisableAuthManager ) {
			$wgAuthManagerAutoConfig['preauth'][CaptchaPreAuthenticationProvider::class] = [
				'class' => CaptchaPreAuthenticationProvider::class,
				'sort'=> 10, // run after preauth providers not requiring user input
			];
			Hooks::register( 'AuthChangeFormFields', 'ConfirmEditHooks::onAuthChangeFormFields' );
		} else {
			Hooks::register( 'UserCreateForm', 'ConfirmEditHooks::injectUserCreate' );
			Hooks::register( 'AbortNewAccount', 'ConfirmEditHooks::confirmUserCreate' );
			Hooks::register( 'LoginAuthenticateAudit', 'ConfirmEditHooks::triggerUserLogin' );
			Hooks::register( 'UserLoginForm', 'ConfirmEditHooks::injectUserLogin' );
			Hooks::register( 'AbortLogin', 'ConfirmEditHooks::confirmUserLogin' );
			Hooks::register( 'AddNewAccountApiForm', 'ConfirmEditHooks::addNewAccountApiForm' );
			Hooks::register( 'AddNewAccountApiResult', 'ConfirmEditHooks::addNewAccountApiResult' );
		}
	}

	static function confirmEditMerged( $context, $content, $status, $summary, $user, $minorEdit ) {
		return self::getInstance()->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
	}

	/**
	 * PageContentSaveComplete hook handler.
	 * Clear IP whitelist cache on page saves for [[MediaWiki:captcha-ip-whitelist]].
	 *
	 * @param Page     $wikiPage
	 * @param User     $user
	 * @param Content  $content
	 * @param string   $summary
	 * @param bool     $isMinor
	 * @param bool     $isWatch
	 * @param string   $section
	 * @param int      $flags
	 * @param int      $revision
	 * @param Status   $status
	 * @param int      $baseRevId
	 *
	 * @return bool true
	 */
	static function onPageContentSaveComplete( Page $wikiPage, User $user, Content $content, $summary,
		$isMinor, $isWatch, $section, $flags, $revision, Status $status, $baseRevId
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getText() === 'Captcha-ip-whitelist' && $title->getNamespace() === NS_MEDIAWIKI ) {
			$cache = ObjectCache::getMainWANInstance();
			$cache->delete( $cache->makeKey( 'confirmedit', 'ipwhitelist' ) );
		}

		return true;
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

	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		self::getInstance()->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
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
		$ourFiles = [];
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
			$wgCaptchaTriggers = array_fill_keys( array_keys( $wgCaptchaTriggers ), false );
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
		require_once ( __DIR__ . '/../ReCaptcha/recaptchalib.php' );
	}

	/**
	 * Extension function, moved from ReCaptcha.php when that was decimated.
	 * Make sure the keys are defined.
	 */
	public static function efReCaptcha() {
		global $wgReCaptchaPublicKey, $wgReCaptchaPrivateKey;
		// @codingStandardsIgnoreStart
		global $recaptcha_public_key, $recaptcha_private_key;
		// @codingStandardsIgnoreEnd
		global $wgServerName;

		// Backwards compatibility
		if ( $wgReCaptchaPublicKey == '' ) {
			$wgReCaptchaPublicKey = $recaptcha_public_key;
		}
		if ( $wgReCaptchaPrivateKey == '' ) {
			$wgReCaptchaPrivateKey = $recaptcha_private_key;
		}

		if ( $wgReCaptchaPublicKey == '' || $wgReCaptchaPrivateKey == '' ) {
			die (
				'You need to set $wgReCaptchaPrivateKey and $wgReCaptchaPublicKey in LocalSettings.php to ' .
				"use the reCAPTCHA plugin. You can sign up for a key <a href='" .
				htmlentities( recaptcha_get_signup_url( $wgServerName, "mediawiki" ) ) . "'>here</a>." );
		}
	}
}
