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

use Iterator;
use MediaWiki\Block\HideUserUtils;
use MediaWiki\User\TempUser\TempUserConfig;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\PreconditionException;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class UserSelectQueryBuilder extends SelectQueryBuilder {

	/** @var ActorStore */
	private $actorStore;
	private TempUserConfig $tempUserConfig;
	private HideUserUtils $hideUserUtils;

	private bool $userJoined = false;

	/**
	 * @internal
	 * @param IReadableDatabase $db
	 * @param ActorStore $actorStore
	 * @param TempUserConfig $tempUserConfig
	 */
	public function __construct(
		IReadableDatabase $db,
		ActorStore $actorStore,
		TempUserConfig $tempUserConfig,
		HideUserUtils $hideUserUtils
	) {
		parent::__construct( $db );

		$this->actorStore = $actorStore;
		$this->tempUserConfig = $tempUserConfig;
		$this->hideUserUtils = $hideUserUtils;
		$this->table( 'actor' );
	}

	/**
	 * Find by provided user ids.
	 *
	 * @param int|int[] $userIds
	 * @return UserSelectQueryBuilder
	 */
	public function whereUserIds( $userIds ): self {
		Assert::parameterType( [ 'integer', 'array' ], $userIds, '$userIds' );
		$this->conds( [ 'actor_user' => $userIds ] );
		return $this;
	}

	/**
	 * Find by provided user ids.
	 * @deprecated since 1.37, use whereUserIds instead
	 * @param int|int[] $userIds
	 * @return UserSelectQueryBuilder
	 */
	public function userIds( $userIds ): self {
		return $this->whereUserIds( $userIds );
	}

	/**
	 * Find by provided user names.
	 *
	 * @param string|string[] $userNames
	 * @return UserSelectQueryBuilder
	 */
	public function whereUserNames( $userNames ): self {
		Assert::parameterType( [ 'string', 'array' ], $userNames, '$userIds' );
		$userNames = array_map( function ( $name ) {
			return $this->actorStore->normalizeUserName( (string)$name );
		}, (array)$userNames );
		$this->conds( [ 'actor_name' => $userNames ] );
		return $this;
	}

	/**
	 * Find by provided user names.
	 * @deprecated since 1.37, use whereUserNames instead
	 * @param string|string[] $userNames
	 * @return UserSelectQueryBuilder
	 */
	public function userNames( $userNames ): self {
		return $this->whereUserNames( $userNames );
	}

	/**
	 * Find users with names starting from the provided prefix.
	 *
	 * @note this could produce a huge number of results, like User00000 ... User99999,
	 * so you must set a limit when using this condition.
	 *
	 * @param string $prefix
	 * @return UserSelectQueryBuilder
	 */
	public function whereUserNamePrefix( string $prefix ): self {
		if ( !isset( $this->options['LIMIT'] ) ) {
			throw new PreconditionException( 'Must set a limit when using a user name prefix' );
		}
		$this->conds(
			$this->db->expr( 'actor_name', IExpression::LIKE, new LikeValue( $prefix, $this->db->anyString() ) )
		);
		return $this;
	}

	/**
	 * Find users with names starting from the provided prefix.
	 *
	 * @note this could produce a huge number of results, like User00000 ... User99999,
	 * so you must set a limit when using this condition.
	 * @deprecated since 1.37 use whereUserNamePrefix instead
	 * @param string $prefix
	 * @return UserSelectQueryBuilder
	 */
	public function userNamePrefix( string $prefix ): self {
		return $this->whereUserNamePrefix( $prefix );
	}

	/**
	 * Find registered users who registered
	 *
	 * @param string $timestamp
	 * @param bool $direction Direction flag (if true, user_registration must be before $timestamp)
	 * @since 1.42
	 * @return UserSelectQueryBuilder
	 */
	public function whereRegisteredTimestamp( string $timestamp, bool $direction ): self {
		if ( !$this->userJoined ) {
			$this->join( 'user', null, [ "actor_user=user_id" ] );
			$this->userJoined = true;
		}

		$this->conds( 'user_registration ' .
			( $direction ? '< ' : '> ' ) .
			$this->db->addQuotes( $this->db->timestamp( $timestamp ) ) );
		return $this;
	}

	/**
	 * Order results by name in $direction
	 *
	 * @param string $dir one of self::SORT_ACS or self::SORT_DESC
	 * @return UserSelectQueryBuilder
	 */
	public function orderByName( string $dir = self::SORT_ASC ): self {
		$this->orderBy( 'actor_name', $dir );
		return $this;
	}

	/**
	 * Order results by user id.
	 *
	 * @param string $dir one of self::SORT_ACS or self::SORT_DESC
	 * @return UserSelectQueryBuilder
	 */
	public function orderByUserId( string $dir = self::SORT_ASC ): self {
		$this->orderBy( 'actor_user', $dir );
		return $this;
	}

	/**
	 * Only return registered users.
	 *
	 * @return UserSelectQueryBuilder
	 */
	public function registered(): self {
		$this->conds( $this->db->expr( 'actor_user', '!=', null ) );
		return $this;
	}

	/**
	 * Only return anonymous users.
	 *
	 * @return UserSelectQueryBuilder
	 */
	public function anon(): self {
		$this->conds( [ 'actor_user' => null ] );
		return $this;
	}

	/**
	 * Only return named users.
	 *
	 * @return UserSelectQueryBuilder
	 */
	public function named(): self {
		if ( !$this->tempUserConfig->isEnabled() ) {
			// nothing to do: getMatchCondition throws if temp accounts aren't enabled
			return $this;
		}
		$this->conds( $this->tempUserConfig->getMatchCondition( $this->db, 'actor_name', IExpression::NOT_LIKE ) );
		return $this;
	}

	/**
	 * Only return temp users
	 *
	 * @return UserSelectQueryBuilder
	 */
	public function temp(): self {
		if ( !$this->tempUserConfig->isEnabled() ) {
			// nothing to do: getMatchCondition throws if temp accounts aren't enabled
			return $this;
		}
		$this->conds( $this->tempUserConfig->getMatchCondition( $this->db, 'actor_name', IExpression::LIKE ) );
		return $this;
	}

	/**
	 * Filter based on user hidden status
	 *
	 * @since 1.38
	 * @param bool $hidden True - only hidden users, false - no hidden users
	 * @return $this
	 */
	public function hidden( bool $hidden ): self {
		$this->conds( $this->hideUserUtils->getExpression(
			$this->db,
			'actor_user',
			$hidden ? HideUserUtils::HIDDEN_USERS : HideUserUtils::SHOWN_USERS
		) );
		return $this;
	}

	/**
	 * Fetch a single UserIdentity that matches specified criteria.
	 *
	 * @return UserIdentity|null
	 */
	public function fetchUserIdentity(): ?UserIdentity {
		$this->fields( [ 'actor_id', 'actor_name', 'actor_user' ] );
		$row = $this->fetchRow();
		if ( !$row ) {
			return null;
		}
		return $this->actorStore->newActorFromRow( $row );
	}

	/**
	 * Fetch UserIdentities for the specified query.
	 *
	 * @return Iterator<UserIdentity>
	 */
	public function fetchUserIdentities(): Iterator {
		$this->fields( [ 'actor_id', 'actor_name', 'actor_user' ] );

		$result = $this->fetchResultSet();
		foreach ( $result as $row ) {
			yield $this->actorStore->newActorFromRow( $row );
		}
		$result->free();
	}

	/**
	 * Returns an array of user names matching the query.
	 *
	 * @return string[]
	 */
	public function fetchUserNames(): array {
		$this->field( 'actor_name' );
		return $this->fetchFieldValues();
	}
}
