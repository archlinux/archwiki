<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use LogicException;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

class TimelineService extends ChangeService {

	/**
	 * Get timeline query info
	 *
	 * @param string[] $targets The targets of the check
	 * @param string[] $excludeTargets The targets to exclude from the check
	 * @param string $start The start offset
	 * @param int $limit The limit for the check
	 * @return array
	 */
	public function getQueryInfo( array $targets, array $excludeTargets, string $start, int $limit ): array {
		// Split the targets into users and IP addresses, so that two queries can be made (one for the users and one
		// for the IPs) and then unioned together.
		$ipTargets = array_filter( $targets, [ IPUtils::class, 'isIPAddress' ] );
		$userTargets = array_diff( $targets, $ipTargets );

		$dbr = $this->dbProvider->getReplicaDatabase();
		$unionQueryBuilder = $dbr->newUnionQueryBuilder()->caller( __METHOD__ );

		// Keep a track of whether a valid SELECT query has been added to the UNION builder.
		$hasValidQuery = false;
		foreach ( self::RESULT_TABLES as $table ) {
			// Generate the queries to be combined in a UNION. If there are no valid targets for the query, then the
			// query will not be run.
			if ( count( $ipTargets ) ) {
				$ipTargetsQuery = $this->getQueryBuilderForTable(
					$table, $ipTargets, true, $excludeTargets, $start, $limit
				);
				if ( $ipTargetsQuery !== null ) {
					$unionQueryBuilder->add( $ipTargetsQuery );
					$hasValidQuery = true;
				}
			}
			if ( count( $userTargets ) ) {
				$userTargetsQuery = $this->getQueryBuilderForTable(
					$table, $userTargets, false, $excludeTargets, $start, $limit
				);
				if ( $userTargetsQuery !== null ) {
					$unionQueryBuilder->add( $userTargetsQuery );
					$hasValidQuery = true;
				}
			}
		}

		if ( !$hasValidQuery ) {
			throw new LogicException( 'Cannot get query info when $targets is empty or contains all invalid targets.' );
		}

		$derivedTable = $unionQueryBuilder->getSQL();

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'namespace', 'title', 'timestamp', 'page_id', 'ip', 'xff', 'agent', 'id', 'user', 'user_text', 'actor',
				'comment_text', 'comment_data', 'type', 'this_oldid', 'last_oldid', 'minor',
				'log_type', 'log_action', 'log_params', 'log_deleted', 'log_id',
			],
		];
	}

	/**
	 * Gets the partial query builder for a specific $table.
	 *
	 * @param string $table The table the query is being performed on and must also be one of the tables listed in
	 *     {@link CheckUserQueryInterface::RESULT_TABLES}
	 * @param array $targets A subset of the $targets provided to ::getQueryInfo, which must all be IPs or accounts
	 * @param bool $targetsAreIPs Whether the targets in $targets are all IP addresses (false is for all
	 *     targets being accounts).
	 * @param array $excludeTargets See ::getQueryInfo
	 * @param string $start See ::getQueryInfo
	 * @param int $limit See ::getQueryInfo
	 * @return SelectQueryBuilder|null The query builder specific to the table, or null if no valid query builder could
	 *    be generated.
	 */
	private function getQueryBuilderForTable(
		string $table, array $targets, bool $targetsAreIPs, array $excludeTargets, string $start, int $limit
	): ?SelectQueryBuilder {
		// Get the query builder for the table which has all table-specific information added but needs the
		// table non-specific information added.
		$queryBuilder = null;
		if ( $table === self::CHANGES_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuChanges( $targets );
		} elseif ( $table === self::LOG_EVENT_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuLogEvent( $targets );
		} elseif ( $table === self::PRIVATE_LOG_EVENT_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuPrivateEvent( $targets, $targetsAreIPs );
		}
		// Don't attempt to add any table-independent conditions if the query builder is null.
		if ( $queryBuilder === null ) {
			return null;
		}
		// Add the WHERE conditions to exclude the targets in the $excludeTargets array, if they can be generated.
		$excludeTargetsExpr = $this->buildExcludeTargetsExpr( $excludeTargets, $table );
		if ( $excludeTargetsExpr !== null ) {
			$queryBuilder->where( $excludeTargetsExpr );
		}
		// Add the start timestamp WHERE conditions to the query, if they can be generated.
		$startExpr = $this->buildStartExpr( $start, $table );
		if ( $startExpr !== null ) {
			$queryBuilder->where( $startExpr );
		}
		// FORCE INDEX is needed to avoid slow queries for users with very high action counts in the result tables.
		$queryBuilder->useIndex( [
			$table => $this->checkUserLookupUtils->getIndexName( $targetsAreIPs ? false : null, $table ),
		] );
		$dbr = $this->dbProvider->getReplicaDatabase();
		if ( $dbr->unionSupportsOrderAndLimit() ) {
			// Add the limit to the query (if using LIMIT is supported in a UNION for this DB type).
			$queryBuilder->limit( $limit + 1 );
		}
		return $queryBuilder;
	}

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_changes for results.
	 *
	 * @param array $targets See ::getQueryInfo
	 * @return ?SelectQueryBuilder The query builder specific to cu_changes, or null if no valid query builder could
	 *   be generated.
	 */
	private function getPartialQueryBuilderForCuChanges( array $targets ): ?SelectQueryBuilder {
		$targetsExpr = $this->buildTargetExprMultiple( $targets, self::CHANGES_TABLE );
		// Don't run the query if no targets are valid.
		if ( $targetsExpr === null ) {
			return null;
		}
		// Important: If updating the fields here, make sure that these are the same and in the same order
		// with the fields in other query builders as the UNION query require that the fields are in the
		// same order and have the same names.
		//
		// Common fields for all queries
		$fields = [
			'namespace' => 'cuc_namespace', 'title' => 'cuc_title', 'timestamp' => 'cuc_timestamp',
			'page_id' => 'cuc_page_id', 'ip' => 'cuc_ip', 'xff' => 'cuc_xff', 'agent' => 'cuc_agent',
			'id' => 'cuc_id', 'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cuc_actor',
			'comment_text', 'comment_data', 'type' => 'cuc_type',
		];
		// Fields only specific to cu_changes
		$fields += [
			'this_oldid' => 'cuc_this_oldid', 'last_oldid' => 'cuc_last_oldid', 'minor' => 'cuc_minor',
		];
		// Fields relevant to cu_log_event and cu_private_event
		$fields += $this->markUnusedFieldsAsNull( [ 'log_type', 'log_action', 'log_params' ] );
		$fields += $this->markUnusedFieldsAsNull( [ 'log_deleted' ], 'smallint' );
		$fields += $this->markUnusedFieldsAsNull( [ 'log_id' ], 'int' );
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->join( 'comment', null, 'comment_id=cuc_comment_id' )
			->where( $targetsExpr )
			->caller( __METHOD__ );
		if ( $dbr->unionSupportsOrderAndLimit() ) {
			// TODO: T360712: Add cuc_id to the ORDER BY clause to ensure unique ordering.
			$queryBuilder->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC );
		}
		return $queryBuilder;
	}

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_log_event for results.
	 *
	 * @param array $targets See ::getQueryInfo
	 * @return ?SelectQueryBuilder The query builder specific to cu_log_event, or null if no valid query builder could
	 *   be generated.
	 */
	private function getPartialQueryBuilderForCuLogEvent( array $targets ): ?SelectQueryBuilder {
		$targetsExpr = $this->buildTargetExprMultiple( $targets, self::LOG_EVENT_TABLE );
		// Don't run the query if no targets are valid.
		if ( $targetsExpr === null ) {
			return null;
		}
		// Important: If updating the fields here, make sure that these are the same and in the same order
		// with the fields in other query builders as the UNION query require that the fields are in the
		// same order and have the same names.
		//
		// Common fields for all queries
		$fields = [
			'namespace' => 'log_namespace', 'title' => 'log_title', 'timestamp' => 'cule_timestamp',
			'page_id' => 'log_page', 'ip' => 'cule_ip', 'xff' => 'cule_xff', 'agent' => 'cule_agent',
			'id' => 'cule_id', 'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cule_actor',
			'comment_text', 'comment_data', 'type' => $this->castValueToType( (string)RC_LOG, 'smallint' ),
		];
		// Fields only specific to cu_changes
		$fields += $this->markUnusedFieldsAsNull( [ 'this_oldid', 'last_oldid' ], 'int' );
		$fields += $this->markUnusedFieldsAsNull( [ 'minor' ], 'smallint' );
		// Fields relevant to cu_log_event and cu_private_event
		$fields += [
			'log_type' => 'log_type', 'log_action' => 'log_action', 'log_params' => 'log_params',
			'log_deleted' => 'log_deleted', 'log_id' => 'cule_log_id',
		];
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->join( 'logging', null, 'log_id=cule_log_id' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( $targetsExpr )
			->caller( __METHOD__ );
		if ( $dbr->unionSupportsOrderAndLimit() ) {
			// TODO: T360712: Add cule_id to the ORDER BY clause to ensure unique ordering.
			$queryBuilder->orderBy( 'cule_timestamp', SelectQueryBuilder::SORT_DESC );
		}
		return $queryBuilder;
	}

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_private_event for results.
	 *
	 * @param array $targets See ::getQueryInfo
	 * @param bool $targetsAreIPs Whether the targets in $targets are all IP addresses (false is for all accounts).
	 * @return ?SelectQueryBuilder The query builder specific to cu_private_event, or null if no valid query builder
	 *   could be generated.
	 */
	private function getPartialQueryBuilderForCuPrivateEvent(
		array $targets, bool $targetsAreIPs
	): ?SelectQueryBuilder {
		$targetsExpr = $this->buildTargetExprMultiple( $targets, self::PRIVATE_LOG_EVENT_TABLE );
		// Don't run the query if no targets are valid.
		if ( $targetsExpr === null ) {
			return null;
		}
		// Important: If updating the fields here, make sure that these are the same and in the same order
		// with the fields in other query builders as the UNION query require that the fields are in the
		// same order and have the same names.
		//
		// Common fields for all queries
		$fields = [
			'namespace' => 'cupe_namespace', 'title' => 'cupe_title', 'timestamp' => 'cupe_timestamp',
			'page_id' => 'cupe_page', 'ip' => 'cupe_ip', 'xff' => 'cupe_xff', 'agent' => 'cupe_agent',
			'id' => 'cupe_id', 'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cupe_actor',
			'comment_text', 'comment_data', 'type' => $this->castValueToType( (string)RC_LOG, 'smallint' ),
		];
		// Fields only specific to cu_changes
		$fields += $this->markUnusedFieldsAsNull( [ 'this_oldid', 'last_oldid' ], 'int' );
		$fields += $this->markUnusedFieldsAsNull( [ 'minor' ], 'smallint' );
		// Fields relevant to cu_log_event and cu_private_event
		$fields += [
			'log_type' => 'cupe_log_type', 'log_action' => 'cupe_log_action', 'log_params' => 'cupe_params',
		];
		// Rows in cu_private_event cannot be revision-deleted, so the value of log_deleted for the rows from this
		// table will always be 0.
		$fields['log_deleted'] = $this->castValueToType( (string)0, 'smallint' );
		// Fields only specific to cu_log_event
		$fields += $this->markUnusedFieldsAsNull( [ 'log_id' ], 'int' );
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_private_event' )
			->join( 'comment', null, 'comment_id=cupe_comment_id' )
			->where( $targetsExpr )
			->caller( __METHOD__ );
		if ( $targetsAreIPs ) {
			// A LEFT JOIN is required because cupe_actor can be NULL if the performer was an IP address and temporary
			// accounts were enabled.
			$queryBuilder->leftJoin( 'actor', null, 'actor_id=cupe_actor' );
		} else {
			// We only need a JOIN if the target of the check is a username because the username will have a valid
			// actor ID.
			$queryBuilder->join( 'actor', null, 'actor_id=cupe_actor' );
		}
		if ( $dbr->unionSupportsOrderAndLimit() ) {
			// TODO: T360712: Add cupe_id to the ORDER BY clause to ensure unique ordering.
			$queryBuilder->orderBy( 'cupe_timestamp', SelectQueryBuilder::SORT_DESC );
		}
		return $queryBuilder;
	}

	/**
	 * Sets the value of every provided field to NULL.
	 *
	 * Used to meet the requirement that all the SELECT sub-queries have the same number of columns. NULL will
	 * indicate that the value is not applicable for this row.
	 *
	 * If using postgres the NULL will be cast to the type specified in the second argument. This is because in
	 * postgres a NULL without a cast is assumed to be of the text type which will not work if the column is
	 * an integer (or other non-text based type) in another table.
	 *
	 * @param array $fields Where the values are the aliased field names that are unused for this query
	 * @param ?string $postgresType
	 * @return string[]
	 */
	private function markUnusedFieldsAsNull( array $fields, ?string $postgresType = null ): array {
		$fieldsToReturn = [];
		foreach ( $fields as $alias ) {
			$fieldsToReturn[$alias] = $this->castValueToType( 'Null', $postgresType );
		}
		return $fieldsToReturn;
	}

	/**
	 * Casts the provided value to the specified type if this is necessary for the current DB type.
	 *
	 * This is necessary because postgres will not automatically cast integers to smallint if the other columns
	 * in the UNION use a smallint type. For DBs other than postgres, this method currently is a no-op.
	 *
	 * @param string $value The value to cast, which should be properly SQL escaped
	 * @param ?string $postgresType The type of the column for postgres, or null to skip casting for all DB types.
	 * @return string The value after appropriate casting, which should be treated as escaped SQL.
	 */
	private function castValueToType( string $value, ?string $postgresType ): string {
		$dbr = $this->dbProvider->getReplicaDatabase();
		if ( $dbr->getType() === 'postgres' && $postgresType !== null ) {
			return "CAST($value AS $postgresType)";
		} else {
			return $value;
		}
	}
}
