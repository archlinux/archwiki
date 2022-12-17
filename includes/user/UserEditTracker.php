<?php

namespace MediaWiki\User;

use ActorMigration;
use DeferredUpdates;
use InvalidArgumentException;
use JobQueueGroup;
use UserEditCountInitJob;
use UserEditCountUpdate;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Track info about user edit counts and timings
 *
 * @since 1.35
 *
 * @author DannyS712
 */
class UserEditTracker {

	private const FIRST_EDIT = 1;
	private const LATEST_EDIT = 2;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @var array
	 *
	 * Mapping of user id to edit count for caching
	 * To avoid using non-sequential numerical keys, keys are in the form: `u⧼user id⧽`
	 */
	private $userEditCountCache = [];

	/**
	 * @param ActorMigration $actorMigration
	 * @param ILoadBalancer $loadBalancer
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		ActorMigration $actorMigration,
		ILoadBalancer $loadBalancer,
		JobQueueGroup $jobQueueGroup
	) {
		$this->actorMigration = $actorMigration;
		$this->loadBalancer = $loadBalancer;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Get a user's edit count from the user_editcount field, falling back to initialize
	 *
	 * @param UserIdentity $user
	 * @return int|null Null for anonymous users
	 */
	public function getUserEditCount( UserIdentity $user ): ?int {
		if ( !$user->isRegistered() ) {
			return null;
		}

		$userId = $user->getId();
		$cacheKey = 'u' . (string)$userId;

		if ( isset( $this->userEditCountCache[ $cacheKey ] ) ) {
			return $this->userEditCountCache[ $cacheKey ];
		}

		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$count = $dbr->selectField(
			'user',
			'user_editcount',
			[ 'user_id' => $userId ],
			__METHOD__
		);

		if ( $count === null ) {
			// it has not been initialized. do so.
			$count = $this->initializeUserEditCount( $user );
		}

		$this->userEditCountCache[ $cacheKey ] = $count;
		return $count;
	}

	/**
	 * @internal For use in UserEditCountUpdate class
	 * @param UserIdentity $user
	 * @return int
	 */
	public function initializeUserEditCount( UserIdentity $user ): int {
		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$actorWhere = $this->actorMigration->getWhere( $dbr, 'rev_user', $user );

		$count = (int)$dbr->selectField(
			[ 'revision' ] + $actorWhere['tables'],
			'COUNT(*)',
			[ $actorWhere['conds'] ],
			__METHOD__,
			[],
			$actorWhere['joins']
		);

		// Defer updating the edit count via a job (T259719)
		$this->jobQueueGroup->push( new UserEditCountInitJob( [
			'userId' => $user->getId(),
			'editCount' => $count,
		] ) );

		return $count;
	}

	/**
	 * Schedule a job to increase a user's edit count
	 *
	 * @since 1.37
	 * @param UserIdentity $user
	 */
	public function incrementUserEditCount( UserIdentity $user ) {
		if ( !$user->isRegistered() ) {
			// Anonymous users don't have edit counts
			return;
		}

		DeferredUpdates::addUpdate(
			new UserEditCountUpdate( $user, 1 ),
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * Get the user's first edit timestamp
	 *
	 * @param UserIdentity $user
	 * @return string|false Timestamp of first edit, or false for non-existent/anonymous user
	 *  accounts.
	 */
	public function getFirstEditTimestamp( UserIdentity $user ) {
		return $this->getUserEditTimestamp( $user, self::FIRST_EDIT );
	}

	/**
	 * Get the user's latest edit timestamp
	 *
	 * @param UserIdentity $user
	 * @return string|false Timestamp of latest edit, or false for non-existent/anonymous user
	 *  accounts.
	 */
	public function getLatestEditTimestamp( UserIdentity $user ) {
		return $this->getUserEditTimestamp( $user, self::LATEST_EDIT );
	}

	/**
	 * Get the timestamp of a user's edit, either their first or latest
	 *
	 * @param UserIdentity $user
	 * @param int $type either self::FIRST_EDIT or ::LATEST_EDIT
	 * @return string|false Timestamp of edit, or false for non-existent/anonymous user accounts.
	 */
	private function getUserEditTimestamp( UserIdentity $user, int $type ) {
		if ( !$user->isRegistered() ) {
			return false;
		}

		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$actorWhere = $this->actorMigration->getWhere( $dbr, 'rev_user', $user );

		$sortOrder = ( $type === self::FIRST_EDIT ) ? 'ASC' : 'DESC';
		$time = $dbr->selectField(
			[ 'revision' ] + $actorWhere['tables'],
			'rev_timestamp',
			[ $actorWhere['conds'] ],
			__METHOD__,
			[ 'ORDER BY' => "rev_timestamp $sortOrder" ],
			$actorWhere['joins']
		);

		if ( !$time ) {
			return false; // no edits
		}

		return ConvertibleTimestamp::convert( TS_MW, $time );
	}

	/**
	 * @internal For use by User::clearInstanceCache()
	 * @param UserIdentity $user
	 */
	public function clearUserEditCache( UserIdentity $user ) {
		if ( !$user->isRegistered() ) {
			return;
		}

		$userId = $user->getId();
		$cacheKey = 'u' . (string)$userId;

		unset( $this->userEditCountCache[ $cacheKey ] );
	}

	/**
	 * @internal For use by User::loadFromRow() and tests
	 * @param UserIdentity $user
	 * @param int $editCount
	 * @throws InvalidArgumentException If the user is not registered
	 */
	public function setCachedUserEditCount( UserIdentity $user, int $editCount ) {
		if ( !$user->isRegistered() ) {
			throw new InvalidArgumentException( __METHOD__ . ' with an anonymous user' );
		}

		$userId = $user->getId();
		$cacheKey = 'u' . (string)$userId;

		$this->userEditCountCache[ $cacheKey ] = $editCount;
	}

}
