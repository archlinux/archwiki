<?php

namespace MediaWiki\Extension\ConfirmEdit\Auth;

use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Helper to count login attempts per IP and per username.
 *
 * @internal
 */
class LoginAttemptCounter {
	private SimpleCaptcha $captcha;

	public function __construct( SimpleCaptcha $captcha ) {
		$this->captcha = $captcha;
	}

	/**
	 * Increase bad login counter after a failed login.
	 * The user might be required to solve a captcha if the count is high.
	 * @param string $username
	 * TODO use Throttler
	 */
	public function increaseBadLoginCounter( $username ) {
		global $wgCaptchaBadLoginExpiration, $wgCaptchaBadLoginPerUserExpiration;

		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();

		if ( $this->captcha->triggersCaptcha( CaptchaTriggers::BAD_LOGIN ) ) {
			$key = $this->badLoginKey( $cache );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginExpiration );

			// Longer period of time
			$key = $this->badLoginKey( $cache, true );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginExpiration * 300 );
		}

		if ( $this->captcha->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER ) && $username ) {
			$key = $this->badLoginPerUserKey( $username, $cache );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginPerUserExpiration );

			$key = $this->badLoginPerUserKey( $username, $cache, true );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginPerUserExpiration * 300 );
		}
	}

	/**
	 * Reset bad login counter after a successful login.
	 * @param string $username
	 */
	public function resetBadLoginCounter( $username ) {
		if ( $this->captcha->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER ) && $username ) {
			$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
			$cache->delete( $this->badLoginPerUserKey( $username, $cache ) );
			$cache->delete( $this->badLoginPerUserKey( $username, $cache, true ) );
		}
	}

	/**
	 * Check if a bad login has already been registered for this
	 * IP address. If so, require a captcha.
	 * @return bool
	 */
	public function isBadLoginTriggered() {
		global $wgCaptchaBadLoginAttempts;

		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
		return $this->captcha->triggersCaptcha( CaptchaTriggers::BAD_LOGIN )
			&& (
				(int)$cache->get( $this->badLoginKey( $cache ) ) >= $wgCaptchaBadLoginAttempts ||
				(int)$cache->get( $this->badLoginKey( $cache, true ) ) >= ( $wgCaptchaBadLoginAttempts * 30 )
			);
	}

	/**
	 * Is the per-user captcha triggered?
	 *
	 * @param User|string $u User object, or name
	 * @return bool
	 */
	public function isBadLoginPerUserTriggered( $u ) {
		global $wgCaptchaBadLoginPerUserAttempts;

		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();

		if ( is_object( $u ) ) {
			$u = $u->getName();
		}
		return $this->captcha->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER )
			&& (
				(int)$cache->get( $this->badLoginPerUserKey( $u, $cache ) ) >= $wgCaptchaBadLoginPerUserAttempts ||
				(int)$cache->get( $this->badLoginPerUserKey( $u, $cache, true ) )
					>= ( $wgCaptchaBadLoginPerUserAttempts * 30 )
			);
	}

	/**
	 * Internal cache key for badlogin checks.
	 * @param BagOStuff $cache
	 * @param bool $long
	 * @return string
	 */
	private function badLoginKey( BagOStuff $cache, $long = false ) {
		global $wgRequest;
		$ip = $wgRequest->getIP();
		if ( !$long ) {
			return $cache->makeGlobalKey( 'captcha', 'badlogin', 'ip', $ip );
		}
		return $cache->makeGlobalKey( 'captcha', 'badlogin', 'ip', 'long', $ip );
	}

	/**
	 * Cache key for badloginPerUser checks.
	 * @param string $username
	 * @param BagOStuff $cache
	 * @param bool $long
	 * @return string
	 */
	private function badLoginPerUserKey( $username, BagOStuff $cache, $long = false ) {
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$username = $userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_USABLE ) ?: $username;
		if ( !$long ) {
			return $cache->makeGlobalKey(
				'captcha', 'badlogin', 'user', md5( $username )
			);
		}
		return $cache->makeGlobalKey(
			'captcha', 'badlogin', 'user', 'long', md5( $username )
		);
	}
}
