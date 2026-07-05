<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use Psr\Log\LoggerInterface;

class FilterUser {
	public const SERVICE_NAME = ServiceNames::FilterUser;

	public function __construct(
		private readonly MessageLocalizer $messageLocalizer,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserNameUtils $userNameUtils,
		private readonly LoggerInterface $logger
	) {
	}

	public function getAuthority(): Authority {
		return $this->getUser();
	}

	public function getUserIdentity(): UserIdentity {
		return $this->getUser();
	}

	/**
	 * Compares the given $user to see if they are the same as the FilterUser.
	 *
	 * @return bool
	 */
	public function isSameUserAs( UserIdentity $user ): bool {
		// Checking the usernames are equal is enough, as this is what is done by
		// User::equals and UserIdentityValue::equals.
		return $user->getName() === $this->getFilterUserName();
	}

	/**
	 * @todo Stop using the User class when User::newSystemUser is refactored.
	 * @return User
	 */
	private function getUser(): User {
		$user = User::newSystemUser( $this->getFilterUserName(), [ 'steal' => true ] );
		if ( $user === null ) {
			throw new \UnexpectedValueException( 'Failed to create AbuseFilter system user' );
		}

		// Promote user to 'sysop' so it doesn't look
		// like an unprivileged account is blocking users
		if ( !in_array( 'sysop', $this->userGroupManager->getUserGroups( $user ) ) ) {
			$this->userGroupManager->addUserToGroup( $user, 'sysop' );
		}

		return $user;
	}

	/**
	 * Gets the username for the FilterUser.
	 */
	private function getFilterUserName(): string {
		$username = $this->messageLocalizer->msg( 'abusefilter-blocker' )->inContentLanguage()->text();
		if ( !$this->userNameUtils->getCanonical( $username ) ) {
			// User name is invalid. Don't throw because this is a system message, easy
			// to change and make wrong either by mistake or intentionally to break the site.
			$this->logger->warning(
				'The AbuseFilter user\'s name is invalid. Please change it in ' .
				'MediaWiki:abusefilter-blocker'
			);
			// Use the default name to avoid breaking other stuff. This should have no harm,
			// aside from blocks temporarily attributed to another user.
			// Don't use the database in case the English onwiki message is broken, T284364
			$username = $this->messageLocalizer->msg( 'abusefilter-blocker' )
				->inLanguage( 'en' )
				->useDatabase( false )
				->text();
		}
		return $username;
	}
}
