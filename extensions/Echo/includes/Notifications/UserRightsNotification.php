<?php

namespace MediaWiki\Extension\Notifications\Notifications;

use MediaWiki\Notification\AgentAware;
use MediaWiki\Notification\Notification;
use MediaWiki\User\UserIdentity;

/**
 * MediaWiki Core notification that represents user rights change
 *
 * @since 1.44
 * @unstable
 */
class UserRightsNotification extends Notification implements AgentAware {

	private UserIdentity $agent;

	/**
	 * Create new notification
	 *
	 * @param UserIdentity $target
	 * @param UserIdentity $agent
	 * @param string $reason
	 */
	public function __construct( UserIdentity $target, UserIdentity $agent, string $reason ) {
		parent::__construct( 'user-rights', [
			// user is required in presentation
			'user' => $target->getId(),
			'reason' => $reason,
		] );
		$this->agent = $agent;
	}

	/**
	 * Named constructor to create a Notification to represent expiration change
	 *
	 * @param UserIdentity $target
	 * @param UserIdentity $agent
	 * @param string $reason
	 * @param string[] $expiryChanged strings corresponding to rights with updated expiry
	 * @return UserRightsNotification
	 */
	public static function newForExpiryChanged(
		UserIdentity $target, UserIdentity $agent, string $reason, array $expiryChanged
	): self {
		$notification = new self( $target, $agent, $reason );
		$notification->setProperty( 'expiry-changed', $expiryChanged );
		return $notification;
	}

	/**
	 * Named constructor to create a Notification to represent user rights change
	 *
	 * @param UserIdentity $target
	 * @param UserIdentity $agent
	 * @param string $reason
	 * @param string[] $added strings corresponding to rights added
	 * @param string[] $removed strings corresponding to rights added
	 * @return UserRightsNotification
	 */
	public static function newForRightsChange(
		UserIdentity $target, UserIdentity $agent, string $reason, array $added, array $removed
	): self {
		$notification = new self( $target, $agent, $reason );
		$notification->setProperty( 'add', $added );
		$notification->setProperty( 'remove', $removed );
		return $notification;
	}

	public function getAgent(): UserIdentity {
		return $this->agent;
	}

}
