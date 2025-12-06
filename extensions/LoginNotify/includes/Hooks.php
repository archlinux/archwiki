<?php
/**
 * @file
 * @ingroup Extensions
 */

namespace LoginNotify;

use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\Hook\AuthManagerLoginAuthenticateAuditHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class Hooks implements
	AuthManagerLoginAuthenticateAuditHook,
	LocalUserCreatedHook,
	RecentChange_saveHook
{
	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly LoginNotify $loginNotify,
	) {
	}

	/**
	 * Hook for login auditing
	 *
	 * @param AuthenticationResponse $ret Is login successful?
	 * @param User|null $user User object on successful auth
	 * @param string|null $username Username for failed attempts.
	 * @param string[] $extraData
	 */
	public function onAuthManagerLoginAuthenticateAudit(
		$ret, $user, $username, $extraData
	) {
		if ( !$user && $username !== null ) {
			$user = $this->userFactory->newFromName( $username, UserFactory::RIGOR_USABLE );
		}

		if ( !$user ) {
			return;
		}

		if ( $ret->status === AuthenticationResponse::PASS ) {
			$this->doSuccessfulLogin( $user );
		} elseif (
			$ret->status === AuthenticationResponse::FAIL
			&& $ret->message->getKey() !== 'login-throttled'
		) {
			$this->doFailedLogin( $user );
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
	public function doSuccessfulLogin( User $user ) {
		$this->loginNotify->clearCounters( $user );
		$this->loginNotify->sendSuccessNotice( $user );
		$this->loginNotify->recordKnownWithCookie( $user );
	}

	/**
	 * Handle a failed login (record the failure).
	 *
	 * @param User $user The user that failed to log in.
	 */
	public function doFailedLogin( User $user ) {
		$this->loginNotify->recordFailure( $user );
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
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( !$autocreated ) {
			$this->loginNotify->recordKnownWithCookie( $user );
		}
	}

	/**
	 * @param RecentChange $recentChange
	 */
	public function onRecentChange_save( $recentChange ) {
		$user = $this->userFactory->newFromUserIdentity( $recentChange->getPerformerIdentity() );
		$this->loginNotify->recordKnown( $user );
	}
}
