<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

abstract class ChangeService implements CheckUserQueryInterface {

	protected IConnectionProvider $dbProvider;
	protected CheckUserLookupUtils $checkUserLookupUtils;
	protected TempUserConfig $tempUserConfig;
	private UserIdentityLookup $userIdentityLookup;

	public function __construct(
		IConnectionProvider $dbProvider,
		UserIdentityLookup $userIdentityLookup,
		CheckUserLookupUtils $checkUserLookupUtils,
		TempUserConfig $tempUserConfig
	) {
		$this->dbProvider = $dbProvider;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string[] $targets
	 * @param string $table The table the query is being performed on and must also be one of the tables listed in
	 *    {@link CheckUserQueryInterface::RESULT_TABLES}
	 * @return IExpression|null The conditions to be added to the query, or null if all the targets were invalid.
	 */
	protected function buildTargetExprMultiple( array $targets, string $table = self::CHANGES_TABLE ): ?IExpression {
		$targetExpressions = array_map( function ( $target ) use ( $table ) {
			return $this->buildTargetExpr( $target, $table );
		}, $targets );

		// Remove any null values from the target expressions array.
		$targetExpressions = array_filter( $targetExpressions );

		if ( !$targetExpressions ) {
			// If no targets provided in $targets were valid, then the $targetExpressions array can be empty. In this
			// case there are no conditions that can be generated, so null should be returned.
			return null;
		}

		return $this->dbProvider->getReplicaDatabase()->orExpr( $targetExpressions );
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string $target
	 * @param string $table The table the query is being performed on and must also be one of the tables listed in
	 *   {@link CheckUserQueryInterface::RESULT_TABLES}
	 * @return IExpression|null
	 */
	protected function buildTargetExpr( string $target, string $table = self::CHANGES_TABLE ): ?IExpression {
		if ( IPUtils::isIpAddress( $target ) ) {
			return $this->checkUserLookupUtils->getIPTargetExpr( $target, false, $table );
		} else {
			// TODO: This may filter out invalid values, changing the number of
			// targets. The per-target limit should change too (T246393).
			$user = $this->userIdentityLookup->getUserIdentityByName( $target );
			if ( $user ) {
				$userId = $user->getId();
				if ( $userId !== 0 ) {
					return $this->dbProvider->getReplicaDatabase()->expr( 'actor_user', '=', $userId );
				}
			}
		}

		return null;
	}

	/**
	 * Build conditions which can be used to exclude the given $targets from the results.
	 *
	 * @param string[] $targets The targets to be excluded
	 * @param string $table The table the query is being performed on and must also be one of the tables listed in
	 *    {@link CheckUserQueryInterface::RESULT_TABLES}
	 * @return IExpression|null The conditions to be added to the query, or null if no conditions are necessary
	 */
	protected function buildExcludeTargetsExpr( array $targets, string $table = self::CHANGES_TABLE ): ?IExpression {
		$ipTargets = [];
		$userTargets = [];

		foreach ( $targets as $target ) {
			if ( IPUtils::isIpAddress( $target ) ) {
				$ipTargets[] = IPUtils::toHex( $target );
			} else {
				$user = $this->userIdentityLookup->getUserIdentityByName( $target );
				if ( $user ) {
					$userId = $user->getId();
					if ( $userId !== 0 ) {
						$userTargets[] = $userId;
					}
				}
			}
		}

		if ( !count( $ipTargets ) && !count( $userTargets ) ) {
			// There will be no conditions if there are no users or IPs to exclude, so return null early.
			return null;
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$expressions = [];
		if ( count( $ipTargets ) > 0 ) {
			$expressions[] = $dbr->expr(
				self::RESULT_TABLE_TO_PREFIX[$table] . 'ip_hex', '!=', $ipTargets
			);
		}
		if ( count( $userTargets ) > 0 ) {
			$expressions[] = $dbr
				->expr( 'actor_user', '!=', $userTargets )
				->or( 'actor_user', '=', null );
		}

		return $dbr->andExpr( $expressions );
	}

	/**
	 * Build an IExpression for the start timestamp.
	 *
	 * @param string $start timestamp
	 * @param string $table The table the query is being performed on and must also be one of the tables listed in
	 *    {@link CheckUserQueryInterface::RESULT_TABLES}
	 * @return ?IExpression the WHERE conditions to add to the query, or null if there are none to add.
	 */
	protected function buildStartExpr( string $start, string $table = self::CHANGES_TABLE ): ?IExpression {
		if ( $start === '' ) {
			// If the start is empty, then we do not have any conditions to add.
			return null;
		}

		// TODO: T360712: Add ID fields to the start conds to ensure unique ordering
		$dbr = $this->dbProvider->getReplicaDatabase();
		return $dbr->expr( self::RESULT_TABLE_TO_PREFIX[$table] . 'timestamp', '>=', $dbr->timestamp( $start ) );
	}

	/**
	 * Given a query builder, adds additional clauses depending on the table it
	 * targets in order to make the query exclude temp accounts from results.
	 *
	 * @param IReadableDatabase $dbr Database connection used to build expressions.
	 * @param SelectQueryBuilder $queryBuilder Query builder to add clauses to.
	 * @param string $table Result table the query builder is for.
	 *
	 * @return SelectQueryBuilder
	 */
	protected function excludeTempAccountsFromQuery(
		IReadableDatabase $dbr,
		SelectQueryBuilder $queryBuilder,
		string $table
	): SelectQueryBuilder {
		$temporaryAccountsFilterExpr = $this->tempUserConfig->getMatchCondition(
			$dbr,
			'actor_name',
			IExpression::NOT_LIKE
		);

		// These tables don't store a username directly but only an actor ID:
		// the 'actor_name' below comes from joining with the actor table.
		$condition = match ( $table ) {
			// We cannot use cu_private_event.cupe_title here because that
			// column is not indexed.
			self::PRIVATE_LOG_EVENT_TABLE => $dbr->orExpr( [
					// Explicitly adding rows where cupe_actor is null
					// so that events such as Failed Logins that do not have
					// a value in cupe_actor are still listed.
					$dbr->expr( 'cupe_actor', '=', null ),
					$temporaryAccountsFilterExpr,
				] ),

			// cule_actor & cuc_actor are declared as NOT NULL, so there is no
			// need to explicitly keep rows where the actor ID is NULL for the
			// log and changes tables as it happened for cu_private_event.
			self::CHANGES_TABLE, self::LOG_EVENT_TABLE => $temporaryAccountsFilterExpr,
			default => null,
		};

		if ( $condition ) {
			$queryBuilder->andWhere( $condition );
		}

		return $queryBuilder;
	}

	/**
	 * Given a list of usernames, returns a new array where all temporary
	 * account names have been removed.
	 *
	 * Note that evaluating whether a username represents a temp account is
	 * delegated to the TempUserConfig instance passed through the constructor.
	 *
	 * @param string[] $usernames A list of usernames.
	 * @return string[]
	 */
	protected function removeTempNamesFromArray( array $usernames ): array {
		return array_filter(
			$usernames,
			fn ( $target ) => !$this->tempUserConfig->isTempName( $target )
		);
	}
}
