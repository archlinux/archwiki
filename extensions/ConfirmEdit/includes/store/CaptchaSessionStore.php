<?php

use MediaWiki\Session\SessionManager;

class CaptchaSessionStore extends CaptchaStore {
	protected function __construct() {
		// Make sure the session is started
		SessionManager::getGlobalSession()->persist();
	}

	public function store( $index, $info ) {
		SessionManager::getGlobalSession()->set( 'captcha' . $index, $info );
	}

	public function retrieve( $index ) {
		return SessionManager::getGlobalSession()->get( 'captcha' . $index, false );
	}

	public function clear( $index ) {
		SessionManager::getGlobalSession()->remove( 'captcha' . $index );
	}

	public function cookiesNeeded() {
		return true;
	}
}
