<?php

class CaptchaCacheStore extends CaptchaStore {
	public function store( $index, $info ) {
		global $wgCaptchaSessionExpiration;

		ObjectCache::getMainStashInstance()->set(
			wfMemcKey( 'captcha', $index ),
			$info,
			$wgCaptchaSessionExpiration
		);
	}

	public function retrieve( $index ) {
		$info = ObjectCache::getMainStashInstance()->get( wfMemcKey( 'captcha', $index ) );
		if ( $info ) {
			return $info;
		} else {
			return false;
		}
	}

	public function clear( $index ) {
		ObjectCache::getMainStashInstance()->delete( wfMemcKey( 'captcha', $index ) );
	}

	public function cookiesNeeded() {
		return false;
	}
}
