<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\User;

use CannotCreateActorException;
use DBAccessObjectUtils;
use ExternalUserNames;
use InvalidArgumentException;
use MediaWiki\DAO\WikiAwareEntity;
use Psr\Log\LoggerInterface;
use stdClass;
use User;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Service for interacting with the actor table.
 *
 * @package MediaWiki\User
 * @since 1.36
 */
class ActorStore implements UserIdentityLookup, ActorNormalization {

	public const UNKNOWN_USER_NAME = 'Unknown user';

	private const LOCAL_CACHE_SIZE = 100;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var LoggerInterface */
	private $logger;

	/** @var string|false */
	private $wikiId;

	/** @var ActorCache */
	private $cache;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param UserNameUtils $userNameUtils
	 * @param LoggerInterface $logger
	 * @param string|false $wikiId
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		UserNameUtils $userNameUtils,
		LoggerInterface $logger,
		$wikiId = WikiAwareEntity::LOCAL
	) {
		Assert::parameterType( 'string|boolean', $wikiId, '$wikiId' );
		Assert::parameter( $wikiId !== true, '$wikiId', 'must be false or a string' );

		$this->loadBalancer = $loadBalancer;
		$this->userNameUtils = $userNameUtils;
		$this->logger = $logger;
		$this->wikiId = $wikiId;

		$this->cache = new ActorCache( self::LOCAL_CACHE_SIZE );
	}

	/**
	 * Instantiate a new UserIdentity object based on a $row from the actor table.
	 *
	 * Use this method when an actor row was already fetched from the DB via a join.
	 * This method just constructs a new instance and does not try fetching missing
	 * values from the DB again, use {@link UserIdentityLookup} for that.
	 *
	 * @param stdClass $row with the following fields:
	 *  - int actor_id
	 *  - string actor_name
	 *  - int|null actor_user
	 * @return UserIdentity
	 * @throws InvalidArgumentException
	 */
	public function newActorFromRow( stdClass $row ): UserIdentity {
		$actorId = (int)$row->actor_id;
		$userId = isset( $row->actor_user ) ? (int)$row->actor_user : 0;
		if ( $actorId === 0 ) {
			throw new InvalidArgumentException( "Actor ID is 0 for {$row->actor_name} and {$userId}" );
		}

		$normalizedName = $this->normalizeUserName( $row->actor_name );
		if ( $normalizedName === null ) {
			$this->logger->warning( 'Encountered invalid actor name in database', [
				'user_id' => $userId,
				'actor_id' => $actorId,
				'actor_name' => $row->actor_name,
				'wiki_id' => $this->wikiId ?: 'local'
			] );
			// TODO: once we have guaranteed db only contains valid actor names,
			// we can skip normalization here - T273933
			if ( $row->actor_name === '' ) {
				throw new InvalidArgumentException( "Actor name can not be empty for {$userId} and {$actorId}" );
			}
		}

		$actor = new UserIdentityValue( $userId, $row->actor_name, $this->wikiId );
		$this->cache->add( $actorId, $actor );
		return $actor;
	}

	/**
	 * Instantiate a new UserIdentity object based on field values from a DB row.
	 *
	 * Until {@link ActorMigration} is completed, the actor table joins alias actor field names
	 * to legacy field names. This method is convenience to construct the UserIdentity based on
	 * legacy field names. It's more relaxed with typing then ::newFromRow to better support legacy
	 * code, so always prefer ::newFromRow in new code. Eventually, once {@link ActorMigration}
	 * is completed and all queries use explicit join with actor table, this method will be
	 * deprecated and removed.
	 *
	 * @throws InvalidArgumentException
	 * @param int|null $userId
	 * @param string|null $name
	 * @param int|null $actorId
	 * @return UserIdentity
	 */
	public function newActorFromRowFields( $userId, $name, $actorId ): UserIdentity {
		// For backwards compatibility we are quite relaxed about what to accept,
		// but try not to create entirely incorrect objects. As we move more code
		// from ActorMigration aliases to proper join with the actor table,
		// we should use ::newActorFromRow more, and eventually deprecate this method.
		$userId = $userId === null ? 0 : (int)$userId;
		$name = $name ?? '';
		if ( $actorId === null ) {
			throw new InvalidArgumentException( "Actor ID is null for {$name} and {$userId}" );
		}
		if ( (int)$actorId === 0 ) {
			throw new InvalidArgumentException( "Actor ID is 0 for {$name} and {$userId}" );
		}

		$normalizedName = $this->normalizeUserName( $name );
		if ( $normalizedName === null ) {
			$this->logger->warning( 'Encountered invalid actor name in database', [
				'user_id' => $userId,
				'actor_id' => $actorId,
				'actor_name' => $name,
				'wiki_id' => $this->wikiId ?: 'local'
			] );
			// TODO: once we have guaranteed the DB entries only exist for normalized names,
			// we can skip normalization here - T273933
			if ( $name === '' ) {
				throw new InvalidArgumentException( "Actor name can not be empty for {$userId} and {$actorId}" );
			}
		}

		$actorId = (int)$actorId;
		$actor = new UserIdentityValue(
			$userId,
			$name,
			$this->wikiId
		);

		$this->cache->add( $actorId, $actor );
		return $actor;
	}

	/**
	 * @param UserIdentity $actor
	 * @internal for use in User object only
	 */
	public function deleteUserIdentityFromCache( UserIdentity $actor ) {
		$this->cache->remove( $actor );
	}

	/**
	 * Find an actor by $id.
	 *
	 * @param int $actorId
	 * @param IDatabase $db The database connection to operate on.
	 *        The database must correspond to ActorStore's wiki ID.
	 * @return UserIdentity|null Returns null if no actor with this $actorId exists in the database.
	 */
	public function getActorById( int $actorId, IDatabase $db ): ?UserIdentity {
		$this->checkDatabaseDomain( $db );

		if ( !$actorId ) {
			return null;
		}

		return $this->cache->getActor( ActorCache::KEY_ACTOR_ID, $actorId ) ??
			$this->newSelectQueryBuilder( $db )
				->caller( __METHOD__ )
				->conds( [ 'actor_id' => $actorId ] )
				->fetchUserIdentity() ??
			// The actor ID mostly comes from DB, so if we can't find an actor by ID,
			// it's most likely due to lagged replica and not cause it doesn't actually exist.
			// Probably we just inserted it? Try primary database.
			$this->newSelectQueryBuilder( self::READ_LATEST )
				->caller( __METHOD__ )
				->conds( [ 'actor_id' => $actorId ] )
				->fetchUserIdentity();
	}

	/**
	 * Find an actor by $name
	 *
	 * @param string $name
	 * @param int $queryFlags one of IDBAccessObject constants
	 * @return UserIdentity|null
	 */
	public function getUserIdentityByName( string $name, int $queryFlags = self::READ_NORMAL ): ?UserIdentity {
		$normalizedName = $this->normalizeUserName( $name );
		if ( $normalizedName === null ) {
			return null;
		}

		return $this->cache->getActor( ActorCache::KEY_USER_NAME, $normalizedName ) ??
			$this->newSelectQueryBuilder( $queryFlags )
				->caller( __METHOD__ )
				->whereUserNames( $normalizedName )
				->fetchUserIdentity();
	}

	/**
	 * Find an actor by $userId
	 *
	 * @param int $userId
	 * @param int $queryFlags one of IDBAccessObject constants
	 * @return UserIdentity|null
	 */
	public function getUserIdentityByUserId( int $userId, int $queryFlags = self::READ_NORMAL ): ?UserIdentity {
		if ( !$userId ) {
			return null;
		}

		return $this->cache->getActor( ActorCache::KEY_USER_ID, $userId ) ??
			$this->newSelectQueryBuilder( $queryFlags )
				->caller( __METHOD__ )
				->whereUserIds( $userId )
				->fetchUserIdentity();
	}

	/**
	 * Attach the actor ID to $user for backwards compatibility.
	 *
	 * @todo remove this method when no longer needed (T273974).
	 *
	 * @param UserIdentity $user
	 * @param int $id
	 * @param bool $assigned whether a new actor ID was just assigned.
	 */
	private function attachActorId( UserIdentity $user, int $id, bool $assigned ) {
		if ( $user instanceof User ) {
			$user->setActorId( $id );
			if ( $assigned ) {
				$user->invalidateCache();
			}
		}
	}

	/**
	 * Detach the actor ID from $user for backwards compatibility.
	 *
	 * @todo remove this method when no longer needed (T273974).
	 *
	 * @param UserIdentity $user
	 */
	private function detachActorId( UserIdentity $user ) {
		if ( $user instanceof User ) {
			$user->setActorId( 0 );
		}
	}

	/**
	 * Find the actor_id of the given $user.
	 *
	 * @param UserIdentity $user
	 * @param IDatabase $db The database connection to operate on.
	 *        The database must correspond to ActorStore's wiki ID.
	 * @return int|null
	 */
	public function findActorId( UserIdentity $user, IDatabase $db ): ?int {
		// TODO: we want to assert this user belongs to the correct wiki,
		// but User objects are always local and we used to use them
		// on a non-local DB connection. We need to first deprecate this
		// possibility and then throw on mismatching User object - T273972
		// $user->assertWiki( $this->wikiId );
		$this->deprecateInvalidCrossWikiParam( $user );

		// TODO: In the future we would be able to assume UserIdentity name is ok
		// and will be able to skip normalization here - T273933
		$name = $this->normalizeUserName( $user->getName() );
		if ( $name === null ) {
			$this->logger->warning( 'Encountered a UserIdentity with invalid name', [
				'user_name' => $user->getName()
			] );
			return null;
		}

		$id = $this->findActorIdInternal( $name, $db );

		// Set the actor ID in the User object. To be removed, see T274148.
		if ( $id && $user instanceof User ) {
			$user->setActorId( $id );
		}

		return $id;
	}

	/**
	 * Find the actor_id of the given $name.
	 *
	 * @param string $name
	 * @param IDatabase $db The database connection to operate on.
	 *        The database must correspond to ActorStore's wiki ID.
	 * @return int|null
	 */
	public function findActorIdByName( $name, IDatabase $db ): ?int {
		$name = $this->normalizeUserName( $name );
		if ( $name === null ) {
			return null;
		}

		return $this->findActorIdInternal( $name, $db );
	}

	/**
	 * Find actor_id of the given $user using the passed $db connection.
	 *
	 * @param string $name
	 * @param IDatabase $db The database connection to operate on.
	 *        The database must correspond to ActorStore's wiki ID.
	 * @param array $queryOptions
	 * @return int|null
	 */
	private function findActorIdInternal(
		string $name,
		IDatabase $db,
		array $queryOptions = []
	): ?int {
		// Note: UserIdentity::getActorId will be deprecated and removed,
		// and this is the replacement for it. Can't call User::getActorId, cause
		// User always thinks it's local, so we could end up fetching the ID
		// from the wrong database.

		$cachedValue = $this->cache->getActorId( ActorCache::KEY_USER_NAME, $name );
		if ( $cachedValue ) {
			return $cachedValue;
		}

		$row = $db->selectRow(
			'actor',
			[ 'actor_user', 'actor_name', 'actor_id' ],
			[ 'actor_name' => $name ],
			__METHOD__,
			$queryOptions
		);

		if ( !$row || !$row->actor_id ) {
			return null;
		}
		// to cache row
		$this->newActorFromRow( $row );

		return (int)$row->actor_id;
	}

	/**
	 * Attempt to assign an actor ID to the given $user. If it is already assigned,
	 * return the existing ID.
	 *
	 * @note If called within a transaction, the returned ID might become invalid
	 * if the transaction is rolled back, so it should not be passed outside of the
	 * transaction context.
	 *
	 * @param UserIdentity $user
	 * @param IDatabase $dbw The database connection to acquire the ID from.
	 *        The database must correspond to ActorStore's wiki ID.
	 * @return int actor ID greater then 0
	 * @throws CannotCreateActorException if no actor ID has been assigned to this $user
	 */
	public function acquireActorId( UserIdentity $user, IDatabase $dbw ): int {
		$this->checkDatabaseDomain( $dbw );
		[ $userId, $userName ] = $this->validateActorForInsertion( $user );

		// allow cache to be used, because if it is in the cache, it already has an actor ID
		$existingActorId = $this->findActorIdInternal( $userName, $dbw );
		if ( $existingActorId ) {
			$this->attachActorId( $user, $existingActorId, false );
			return $existingActorId;
		}

		$dbw->insert(
			'actor',
			[
				'actor_user' => $userId,
				'actor_name' => $userName,
			],
			__METHOD__,
			[ 'IGNORE' ]
		);

		if ( $dbw->affectedRows() ) {
			$actorId = $dbw->insertId();
		} else {
			// Outdated cache?
			// Use LOCK IN SHARE MODE to bypass any MySQL REPEATABLE-READ snapshot.
			$actorId = $this->findActorIdInternal(
				$userName,
				$dbw,
				[ 'LOCK IN SHARE MODE' ]
			);
			if ( !$actorId ) {
				throw new CannotCreateActorException(
					"Failed to create actor ID for " .
					"user_id={$userId} user_name=\"{$userName}\""
				);
			}
		}

		$this->attachActorId( $user, $actorId, true );
		// Cache row we've just created
		$cachedUserIdentity = $this->newActorFromRowFields( $userId, $userName, $actorId );
		$this->setUpRollbackHandler( $dbw, $cachedUserIdentity, $user );
		return $actorId;
	}

	/**
	 * Create a new actor for the given $user. If an actor with this name already exists,
	 * this method throws.
	 *
	 * @note If called within a transaction, the returned ID might become invalid
	 * if the transaction is rolled back, so it should not be passed outside of the
	 * transaction context.
	 *
	 * @param UserIdentity $user
	 * @param IDatabase $dbw
	 * @return int actor ID greater then 0
	 * @throws CannotCreateActorException if an actor with this name already exist.
	 * @internal for use in user account creation only.
	 */
	public function createNewActor( UserIdentity $user, IDatabase $dbw ): int {
		$this->checkDatabaseDomain( $dbw );
		[ $userId, $userName ] = $this->validateActorForInsertion( $user );

		try {
			$dbw->insert(
				'actor',
				[
					'actor_user' => $userId,
					'actor_name' => $userName,
				],
				__METHOD__
			);
		} catch ( DBQueryError $e ) {
			// We rely on the database to crash on unique actor_name constraint.
			throw new CannotCreateActorException( $e->getMessage() );
		}
		$actorId = $dbw->insertId();

		$this->attachActorId( $user, $actorId, true );
		// Cache row we've just created
		$cachedUserIdentity = $this->newActorFromRowFields( $userId, $userName, $actorId );
		$this->setUpRollbackHandler( $dbw, $cachedUserIdentity, $user );

		return $actorId;
	}

	/**
	 * Attempt to assign an ID to an actor for a system user. If an actor ID already
	 * exists, return it.
	 *
	 * @note For reserved user names this method will overwrite the user ID of the
	 * existing anon actor.
	 *
	 * @note If called within a transaction, the returned ID might become invalid
	 * if the transaction is rolled back, so it should not be passed outside of the
	 * transaction context.
	 *
	 * @param UserIdentity $user
	 * @param IDatabase $dbw
	 * @return int actor ID greater then zero
	 * @throws CannotCreateActorException if the existing actor is associated with registered user.
	 * @internal for use in user account creation only.
	 */
	public function acquireSystemActorId( UserIdentity $user, IDatabase $dbw ): int {
		$this->checkDatabaseDomain( $dbw );
		[ $userId, $userName ] = $this->validateActorForInsertion( $user );

		$existingActorId = $this->findActorIdInternal( $userName, $dbw );
		if ( $existingActorId ) {
			// It certainly will be cached if we just found it.
			$existingActor = $this->cache->getActor( ActorCache::KEY_ACTOR_ID, $existingActorId );

			// If we already have an existing actor with a matching user ID
			// just return it, nothing to do here.
			if ( $existingActor->getId( $this->wikiId ) === $user->getId( $this->wikiId ) ) {
				return $existingActorId;
			}

			// Allow overwriting user ID for already existing actor with reserved user name, see T236444
			if ( $this->userNameUtils->isUsable( $userName ) || $existingActor->isRegistered() ) {
				throw new CannotCreateActorException(
					'Cannot replace user for existing actor: ' .
					"actor_id=$existingActorId, new user_id=$userId"
				);
			}
		}

		$dbw->upsert(
			'actor',
			[ 'actor_name' => $userName, 'actor_user' => $userId ],
			[ 'actor_name' ],
			[ 'actor_user' => $userId ],
			__METHOD__
		);
		if ( !$dbw->affectedRows() ) {
			throw new CannotCreateActorException(
				'Failed to replace user for actor: ' .
				"actor_id=$existingActorId, new user_id=$userId"
			);
		}
		$actorId = $dbw->insertId() ?: $existingActorId;

		$this->cache->remove( $user );
		$this->attachActorId( $user, $actorId, true );
		// Cache row we've just created
		$cachedUserIdentity = $this->newActorFromRowFields( $userId, $userName, $actorId );
		$this->setUpRollbackHandler( $dbw, $cachedUserIdentity, $user );
		return $actorId;
	}

	/**
	 * Delete the actor from the actor table
	 *
	 * @warning this method does very limited validation and is extremely
	 * dangerous since it can break referential integrity of the database
	 * if used incorrectly. Use at your own risk!
	 *
	 * @since 1.37
	 * @param UserIdentity $actor
	 * @param IDatabase $dbw
	 * @return bool true on success, false if nothing was deleted.
	 */
	public function deleteActor( UserIdentity $actor, IDatabase $dbw ): bool {
		$this->checkDatabaseDomain( $dbw );
		$this->deprecateInvalidCrossWikiParam( $actor );

		$normalizedName = $this->normalizeUserName( $actor->getName() );
		if ( $normalizedName === null ) {
			throw new InvalidArgumentException(
				"Unable to normalize the provided actor name {$actor->getName()}"
			);
		}
		$dbw->delete(
			'actor',
			[ 'actor_name' => $normalizedName ],
			__METHOD__
		);
		if ( $dbw->affectedRows() !== 0 ) {
			$this->cache->remove( $actor );
			return true;
		}
		return false;
	}

	/**
	 * Returns a canonical form of user name suitable for storage.
	 *
	 * @internal
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function normalizeUserName( string $name ): ?string {
		if ( $this->userNameUtils->isIP( $name ) ) {
			return IPUtils::sanitizeIP( $name );
		} elseif ( ExternalUserNames::isExternal( $name ) ) {
			// TODO: ideally, we should probably canonicalize external usernames,
			// but it was not done before, so we can not start doing it unless we
			// fix existing DB rows - T273933
			return $name;
		} else {
			$normalized = $this->userNameUtils->getCanonical( $name );
			return $normalized === false ? null : $normalized;
		}
	}

	/**
	 * Validates actor before insertion.
	 *
	 * @param UserIdentity $user
	 * @return array [ $normalizedUserId, $normalizedName ]
	 */
	private function validateActorForInsertion( UserIdentity $user ): array {
		// TODO: we want to assert this user belongs to the correct wiki,
		// but User objects are always local and we used to use them
		// on a non-local DB connection. We need to first deprecate this
		// possibility and then throw on mismatching User object - T273972
		// $user->assertWiki( $this->wikiId );
		$this->deprecateInvalidCrossWikiParam( $user );

		$userName = $this->normalizeUserName( $user->getName() );
		if ( $userName === null || $userName === '' ) {
			$userIdForErrorMessage = $user->getId( $this->wikiId );
			throw new CannotCreateActorException(
				'Cannot create an actor for a user with no name: ' .
				"user_id={$userIdForErrorMessage} user_name=\"{$user->getName()}\""
			);
		}

		$userId = $user->getId( $this->wikiId ) ?: null;
		if ( $userId === null && $this->userNameUtils->isUsable( $user->getName() ) ) {
			throw new CannotCreateActorException(
				'Cannot create an actor for a usable name that is not an existing user: ' .
				"user_name=\"{$user->getName()}\""
			);
		}
		return [ $userId, $userName ];
	}

	/**
	 * Clear in-process caches if transaction gets rolled back.
	 *
	 * @param IDatabase $dbw
	 * @param UserIdentity $cachedActor
	 * @param UserIdentity $originalActor
	 */
	private function setUpRollbackHandler(
		IDatabase $dbw,
		UserIdentity $cachedActor,
		UserIdentity $originalActor
	) {
		if ( $dbw->trxLevel() ) {
			// If called within a transaction and it was rolled back, the cached actor ID
			// becomes invalid, so cache needs to be invalidated as well. See T277795.
			$dbw->onTransactionResolution(
				function ( int $trigger ) use ( $cachedActor, $originalActor ) {
					if ( $trigger === IDatabase::TRIGGER_ROLLBACK ) {
						$this->cache->remove( $cachedActor );
						$this->detachActorId( $originalActor );
					}
				},
				__METHOD__
			);
		}
	}

	/**
	 * @param int $queryFlags a bit field composed of READ_XXX flags
	 * @return array [ IDatabase $db, array $options ]
	 */
	private function getDBConnectionRefForQueryFlags( int $queryFlags ): array {
		[ $mode, $options ] = DBAccessObjectUtils::getDBOptions( $queryFlags );
		return [ $this->loadBalancer->getConnectionRef( $mode, [], $this->wikiId ), $options ];
	}

	/**
	 * Throws an exception if the given database connection does not belong to the wiki this
	 * RevisionStore is bound to.
	 *
	 * @param IDatabase $db
	 */
	private function checkDatabaseDomain( IDatabase $db ) {
		$dbDomain = $db->getDomainID();
		$storeDomain = $this->loadBalancer->resolveDomainID( $this->wikiId );
		if ( $dbDomain !== $storeDomain ) {
			throw new InvalidArgumentException(
				"DB connection domain '$dbDomain' does not match '$storeDomain'"
			);
		}
	}

	/**
	 * In case all reasonable attempts of initializing a proper actor from the
	 * database have failed, entities can be attributed to special 'Unknown user' actor.
	 *
	 * @return UserIdentity
	 */
	public function getUnknownActor(): UserIdentity {
		$actor = $this->getUserIdentityByName( self::UNKNOWN_USER_NAME );
		if ( $actor ) {
			return $actor;
		}
		$actor = new UserIdentityValue( 0, self::UNKNOWN_USER_NAME, $this->wikiId );

		[ $db, ] = $this->getDBConnectionRefForQueryFlags( self::READ_LATEST );
		$this->acquireActorId( $actor, $db );
		return $actor;
	}

	/**
	 * Returns a specialized SelectQueryBuilder for querying the UserIdentity objects.
	 *
	 * @param IDatabase|int $dbOrQueryFlags The database connection to perform the query on,
	 *   or one of self::READ_* constants.
	 * @return UserSelectQueryBuilder
	 */
	public function newSelectQueryBuilder( $dbOrQueryFlags = self::READ_NORMAL ): UserSelectQueryBuilder {
		if ( $dbOrQueryFlags instanceof IDatabase ) {
			[ $db, $options ] = [ $dbOrQueryFlags, [] ];
			$this->checkDatabaseDomain( $db );
		} else {
			[ $db, $options ] = $this->getDBConnectionRefForQueryFlags( $dbOrQueryFlags );
		}

		return ( new UserSelectQueryBuilder( $db, $this ) )->options( $options );
	}

	/**
	 * Emits a deprecation warning if $user does not belong to the
	 * same wiki this store belongs to.
	 *
	 * @param UserIdentity $user
	 */
	private function deprecateInvalidCrossWikiParam( UserIdentity $user ) {
		if ( $user->getWikiId() !== $this->wikiId ) {
			$expected = $this->wikiIdToString( $user->getWikiId() );
			$actual = $this->wikiIdToString( $this->wikiId );
			wfDeprecatedMsg(
				'Deprecated passing invalid cross-wiki user. ' .
				"Expected: {$expected}, Actual: {$actual}.",
				'1.37'
			);
		}
	}

	/**
	 * Convert $wikiId to a string for logging.
	 *
	 * @param string|false $wikiId
	 * @return string
	 */
	private function wikiIdToString( $wikiId ): string {
		return $wikiId === WikiAwareEntity::LOCAL ? 'the local wiki' : "'{$wikiId}'";
	}
}
