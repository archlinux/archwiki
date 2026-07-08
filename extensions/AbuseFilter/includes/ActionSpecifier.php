<?php

namespace MediaWiki\Extension\AbuseFilter;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Plain value object that univocally represents an action being filtered
 * @todo Add constants for possible actions?
 * @todo Add the timestamp
 */
class ActionSpecifier {

	/**
	 * @param string $action Action being filtered (e.g. 'edit' or 'createaccount')
	 * @param LinkTarget $title Where the current action is executed. This is the user page
	 *   for account creations.
	 * @param UserIdentity $user
	 * @param string $requestIP
	 * @param string|null $accountName Required iff the action is an account creation
	 */
	public function __construct(
		private readonly string $action,
		private readonly LinkTarget $title,
		private readonly UserIdentity $user,
		private readonly string $requestIP,
		private readonly ?string $accountName
	) {
		if ( $accountName === null && str_contains( $action, 'createaccount' ) ) {
			throw new InvalidArgumentException( '$accountName required for account creations' );
		}
	}

	public function getAction(): string {
		return $this->action;
	}

	public function getTitle(): LinkTarget {
		return $this->title;
	}

	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * @return string
	 * @note It may be an empty string for less recent changes.
	 */
	public function getIP(): string {
		return $this->requestIP;
	}

	public function getAccountName(): ?string {
		return $this->accountName;
	}
}
