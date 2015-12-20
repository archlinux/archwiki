<?php

abstract class CaptchaStore {
	/**
	 * Store the correct answer for a given captcha
	 * @param  $index String
	 * @param  $info String the captcha result
	 */
	public abstract function store( $index, $info );

	/**
	 * Retrieve the answer for a given captcha
	 * @param  $index String
	 * @return String
	 */
	public abstract function retrieve( $index );

	/**
	 * Delete a result once the captcha has been used, so it cannot be reused
	 * @param  $index
	 */
	public abstract function clear( $index );

	/**
	 * Whether this type of CaptchaStore needs cookies
	 * @return Bool
	 */
	public abstract function cookiesNeeded();

	/**
	 * The singleton instance
	 * @var CaptchaStore
	 */
	private static $instance;

	/**
	 * Get somewhere to store captcha data that will persist between requests
	 *
	 * @throws Exception
	 * @return CaptchaStore
	 */
	public final static function get() {
		if ( !self::$instance instanceof self ) {
			global $wgCaptchaStorageClass;
			if ( in_array( 'CaptchaStore', class_parents( $wgCaptchaStorageClass ) ) ) {
				self::$instance = new $wgCaptchaStorageClass;
			} else {
				throw new Exception( "Invalid CaptchaStore class $wgCaptchaStorageClass" );
			}
		}
		return self::$instance;
	}

	/**
	 * Protected constructor: no creating instances except through the factory method above
	 */
	protected function __construct() {}
}

class CaptchaSessionStore extends CaptchaStore {
	protected function __construct() {
		// Make sure the session is started
		if ( session_id() === '' ) {
			wfSetupSession();
		}
	}

	function store( $index, $info ) {
		$_SESSION['captcha' . $info['index']] = $info;
	}

	function retrieve( $index ) {
		if ( isset( $_SESSION['captcha' . $index] ) ) {
			return $_SESSION['captcha' . $index];
		} else {
			return false;
		}
	}

	function clear( $index ) {
		unset( $_SESSION['captcha' . $index] );
	}

	function cookiesNeeded() {
		return true;
	}
}

class CaptchaCacheStore extends CaptchaStore {
	function store( $index, $info ) {
		global $wgCaptchaSessionExpiration;

		ObjectCache::getMainStashInstance()->set(
			wfMemcKey( 'captcha', $index ),
			$info,
			$wgCaptchaSessionExpiration
		);
	}

	function retrieve( $index ) {
		$info = ObjectCache::getMainStashInstance()->get( wfMemcKey( 'captcha', $index ) );
		if ( $info ) {
			return $info;
		} else {
			return false;
		}
	}

	function clear( $index ) {
		ObjectCache::getMainStashInstance()->delete( wfMemcKey( 'captcha', $index ) );
	}

	function cookiesNeeded() {
		return false;
	}
}
