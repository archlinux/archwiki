<?php

use MediaWiki\Session\SessionManager;

abstract class CaptchaStore {
	/**
	 * Store the correct answer for a given captcha
	 * @param  $index String
	 * @param  $info String the captcha result
	 */
	abstract public function store( $index, $info );

	/**
	 * Retrieve the answer for a given captcha
	 * @param  $index String
	 * @return String|false
	 */
	abstract public function retrieve( $index );

	/**
	 * Delete a result once the captcha has been used, so it cannot be reused
	 * @param  $index
	 */
	abstract public function clear( $index );

	/**
	 * Whether this type of CaptchaStore needs cookies
	 * @return Bool
	 */
	abstract public function cookiesNeeded();

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
	final public static function get() {
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
	protected function __construct() {
	}
}

class CaptchaSessionStore extends CaptchaStore {
	protected function __construct() {
		// Make sure the session is started
		SessionManager::getGlobalSession()->persist();
	}

	function store( $index, $info ) {
		SessionManager::getGlobalSession()->set( 'captcha' . $index, $info );
	}

	function retrieve( $index ) {
		return SessionManager::getGlobalSession()->get( 'captcha' . $index, false );
	}

	function clear( $index ) {
		SessionManager::getGlobalSession()->remove( 'captcha' . $index );
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

class CaptchaHashStore extends CaptchaStore {
	protected $data = [];

	public function store( $index, $info ) {
		$this->data[$index] = $info;
	}

	public function retrieve( $index ) {
		if ( array_key_exists( $index, $this->data ) ) {
			return $this->data[$index];
		}
		return false;
	}

	public function clear( $index ) {
		unset( $this->data[$index] );
	}

	public function cookiesNeeded() {
		return false;
	}

	public function clearAll() {
		$this->data = [];
	}
}
