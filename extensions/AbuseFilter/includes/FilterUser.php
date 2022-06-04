<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use Psr\Log\LoggerInterface;
use User;

class FilterUser {
	public const SERVICE_NAME = 'AbuseFilterFilterUser';

	/** @var MessageLocalizer */
	private $messageLocalizer;
	/** @var UserGroupManager */
	private $userGroupManager;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param UserGroupManager $userGroupManager
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		MessageLocalizer $messageLocalizer,
		UserGroupManager $userGroupManager,
		LoggerInterface $logger
	) {
		$this->messageLocalizer = $messageLocalizer;
		$this->userGroupManager = $userGroupManager;
		$this->logger = $logger;
	}

	/**
	 * @return Authority
	 */
	public function getAuthority(): Authority {
		return $this->getUser();
	}

	/**
	 * @return UserIdentity
	 */
	public function getUserIdentity(): UserIdentity {
		return $this->getUser();
	}

	/**
	 * @todo Stop using the User class when User::newSystemUser is refactored.
	 * @return User
	 */
	private function getUser(): User {
		$username = $this->messageLocalizer->msg( 'abusefilter-blocker' )->inContentLanguage()->text();
		$user = User::newSystemUser( $username, [ 'steal' => true ] );

		if ( !$user ) {
			// User name is invalid. Don't throw because this is a system message, easy
			// to change and make wrong either by mistake or intentionally to break the site.
			$this->logger->warning(
				'The AbuseFilter user\'s name is invalid. Please change it in ' .
				'MediaWiki:abusefilter-blocker'
			);
			// Use the default name to avoid breaking other stuff. This should have no harm,
			// aside from blocks temporarily attributed to another user.
			// Don't use the database in case the English onwiki message is broken, T284364
			$defaultName = $this->messageLocalizer->msg( 'abusefilter-blocker' )
				->inLanguage( 'en' )
				->useDatabase( false )
				->text();
			$user = User::newSystemUser( $defaultName, [ 'steal' => true ] );
		}
		'@phan-var User $user';

		// Promote user to 'sysop' so it doesn't look
		// like an unprivileged account is blocking users
		if ( !in_array( 'sysop', $this->userGroupManager->getUserGroups( $user ) ) ) {
			$this->userGroupManager->addUserToGroup( $user, 'sysop' );
		}

		return $user;
	}
}
