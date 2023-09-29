<?php
/**
 * Body of LoginNotify extension
 *
 * @file
 * @ingroup Extensions
 */

namespace LoginNotify;

use EchoAttributeManager;
use EchoEvent;
use EchoUserLocator;
use MediaWiki\Auth\AuthenticationResponse;
use User;

class Hooks {
	/**
	 * Add LoginNotify events to Echo
	 *
	 * @param string[] &$notifications Array of Echo notifications
	 * @param string[] &$notificationCategories Array of Echo notification categories
	 * @param string[] &$icons Array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		global $wgLoginNotifyEnableOnSuccess, $wgNotifyTypeAvailabilityByCategory;

		$icons['LoginNotify-user-avatar'] = [
			'path' => 'LoginNotify/UserAvatar.svg'
		];

		$notificationCategories['login-fail'] = [
			'priority' => 7,
			'tooltip' => 'echo-pref-tooltip-login-fail',
		];

		$loginBase = [
			EchoAttributeManager::ATTR_LOCATORS => [
				[ [ EchoUserLocator::class, 'locateEventAgent' ] ],
			],
			'canNotifyAgent' => true,
			'category' => 'login-fail',
			'group' => 'negative',
			'presentation-model' => PresentationModel::class,
			// fixme, what does this actually do?
			'title-message' => 'loginnotify-login-fail',
			'title-params' => [],
			// FIXME Should count be a parameter
			'email-subject-params' => [ 'agent', 'count' ],
			'email-body-batch-params' => [ 'agent', 'count' ],
			// FIXME is it ok not to set batch email messages, since
			// we have immediate flag?
			'icon' => 'LoginNotify-user-avatar',
			'immediate' => true,
		];
		$notifications['login-fail-new'] = [
			'bundle' => [
				'web' => true,
				'expandable' => false
			]
		] + $loginBase;
		$notifications['login-fail-known'] = [
			'bundle' => [
				'web' => true,
				'expandable' => false
			]
		] + $loginBase;
		if ( $wgLoginNotifyEnableOnSuccess ) {
			$notificationCategories['login-success'] = [
				'priority' => 7,
				'tooltip' => 'echo-pref-tooltip-login-success',
			];
			$notifications['login-success'] = [
				'category' => 'login-success',
			] + $loginBase;
			$wgNotifyTypeAvailabilityByCategory['login-success'] = [
				'web' => false,
				'email' => true,
			];
		}

		return true;
	}

	/**
	 * @param EchoEvent $event
	 * @param string &$bundleString
	 * @return bool
	 */
	public static function onEchoGetBundleRules( EchoEvent $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'login-fail-new':
				$bundleString = 'login-fail';
				break;
		}
		return true;
	}

	/**
	 * Hook for login auditing
	 *
	 * @param AuthenticationResponse $ret Is login successful?
	 * @param User|null $user User object on successful auth
	 * @param string $username Username for failed attempts.
	 */
	public static function onAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $ret, $user, $username
	) {
		if ( $user ) {
			$userObj = $user;
		} else {
			$userObj = User::newFromName( $username, 'usable' );
		}
		if ( !$userObj ) {
			return;
		}

		if ( $ret->status === AuthenticationResponse::PASS ) {
			self::doSuccessfulLogin( $userObj );
		} elseif (
			$ret->status === AuthenticationResponse::FAIL
			&& $ret->message->getKey() !== 'login-throttled'
		) {
			self::doFailedLogin( $userObj );
		}
		// Other statuses include Abstain, Redirect, or UI. We ignore such
		// statuses.
	}

	/**
	 * Handle a successful login (clear the attempt counter, send a notice, and record the
	 * current IP address as known).
	 *
	 * @param User $user The user who logged in.
	 */
	public static function doSuccessfulLogin( User $user ) {
		$loginNotify = new LoginNotify();
		$loginNotify->clearCounters( $user );
		$loginNotify->sendSuccessNotice( $user );
		$loginNotify->setCurrentAddressAsKnown( $user );
	}

	/**
	 * Handle a failed login (record the failure).
	 *
	 * @param User $user The user that failed to log in.
	 */
	public static function doFailedLogin( User $user ) {
		$loginNotify = new LoginNotify();
		$loginNotify->recordFailure( $user );
	}

	/**
	 * Hook handler for new account creation.
	 *
	 * Called immediately after a local user has been created and saved to the database
	 *
	 * @todo This still sets cookies if user creates account well logged in as someone else.
	 * @param User $user User created
	 * @param bool $autocreated Whether this was an auto-created account
	 */
	public static function onLocalUserCreated( $user, $autocreated ) {
		if ( !$autocreated ) {
			$loginNotify = new LoginNotify();
			$loginNotify->setCurrentAddressAsKnown( $user );
		}
	}
}
