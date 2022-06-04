<?php

namespace MediaWiki\Extension\AbuseFilter;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Plain value object that univocally represents an action being filtered
 * @todo Add constants for possible actions?
 */
class ActionSpecifier {
	/** @var string */
	private $action;
	/** @var LinkTarget */
	private $title;
	/** @var UserIdentity */
	private $user;
	/** @var string|null */
	private $accountName;

	/**
	 * @param string $action Action being filtered (e.g. 'edit' or 'createaccount')
	 * @param LinkTarget $title Where the current action is executed. This is the user page
	 *   for account creations.
	 * @param UserIdentity $user
	 * @param string|null $accountName Required iff the action is an account creation
	 */
	public function __construct( string $action, LinkTarget $title, UserIdentity $user, ?string $accountName ) {
		if ( $accountName === null && strpos( $action, 'createaccount' ) !== false ) {
			throw new InvalidArgumentException( '$accountName required for account creations' );
		}
		$this->action = $action;
		$this->title = $title;
		$this->user = $user;
		$this->accountName = $accountName;
	}

	/**
	 * @return string
	 */
	public function getAction(): string {
		return $this->action;
	}

	/**
	 * @return LinkTarget
	 */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/**
	 * @return UserIdentity
	 */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * @return string|null
	 */
	public function getAccountName(): ?string {
		return $this->accountName;
	}
}
