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
namespace Wikimedia\Rdbms;

use stdClass;
use Wikimedia\Rdbms\Database\DbQuoter;
use Wikimedia\Rdbms\Database\IDatabaseFlags;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

/**
 * A database connection without write operations.
 *
 * @since 1.40
 * @ingroup Database
 */
interface IReadableDatabase extends ISQLPlatform, DbQuoter, IDatabaseFlags {
	/**
	 * Get a human-readable string describing the current software version
	 *
	 * Use getServerVersion() to get machine-friendly information.
	 *
	 * @return string Version information from the database server
	 */
	public function getServerInfo();

	/**
	 * Get/set the table prefix
	 *
	 * @param string|null $prefix The table prefix to set, or omitted to leave it unchanged
	 * @return string The previous table prefix
	 */
	public function tablePrefix( $prefix = null );

	/**
	 * Get/set the db schema
	 *
	 * @param string|null $schema The database schema to set, or omitted to leave it unchanged
	 * @return string The previous db schema
	 */
	public function dbSchema( $schema = null );

	/**
	 * Get the last query that sent on account of IDatabase::query()
	 *
	 * @deprecated since 1.40 without replacement
	 * @return string SQL text or empty string if there was no such query
	 */
	public function lastQuery();

	/**
	 * @return bool Whether a connection to the database open
	 */
	public function isOpen();

	/**
	 * Return the currently selected domain ID
	 *
	 * Null components (database/schema) might change once a connection is established
	 *
	 * @return string
	 */
	public function getDomainID();

	/**
	 * Get the RDBMS type of the server (e.g. "mysql", "sqlite")
	 *
	 * @return string
	 */
	public function getType();

	/**
	 * Get the RDBMS-specific error code from the last attempted query statement
	 *
	 * @return int|string Error code (integer or hexadecimal depending on RDBMS type)
	 */
	public function lastErrno();

	/**
	 * Get the RDBMS-specific error description from the last attempted query statement
	 *
	 * @return string
	 */
	public function lastError();

	/**
	 * Returns a wikitext style link to the DB's website (e.g. "[https://www.mysql.com/ MySQL]")
	 *
	 * Should at least contain plain text, if for some reason your database has no website.
	 *
	 * @return string Wikitext of a link to the server software's web site
	 */
	public function getSoftwareLink();

	/**
	 * A string describing the current software version, like from mysql_get_server_info()
	 *
	 * @return string Version information from the database server.
	 */
	public function getServerVersion();

	/**
	 * Close the database connection
	 *
	 * This should only be called after any transactions have been resolved,
	 * aside from read-only automatic transactions (assuming no callbacks are registered).
	 * If a transaction is still open anyway, it will be rolled back.
	 *
	 * @internal Only for use by LoadBalancer
	 *
	 * @param string $fname Caller name
	 * @return bool Success
	 * @throws DBError
	 */
	public function close( $fname = __METHOD__ );

	/**
	 * Create an empty SelectQueryBuilder which can be used to run queries
	 * against this connection.
	 *
	 * @note A new query builder must be created per query. Query builders
	 *   should not be reused since this uses a fluent interface and the state of
	 *   the builder changes during the query which may cause unexpected results.
	 *
	 * @return SelectQueryBuilder
	 */
	public function newSelectQueryBuilder(): SelectQueryBuilder;

	/**
	 * A SELECT wrapper which returns a single field from a single result row
	 *
	 * If no result rows are returned from the query, false is returned.
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::fetchField}
	 * instead, which is more readable and less error-prone.
	 *
	 * @param string|array $table Table name. {@see select} for details.
	 * @param string|array $var The field name to select. This must be a valid SQL fragment: do not
	 *  use unvalidated user input. Can be an array, but must contain exactly 1 element then.
	 *  {@see select} for details.
	 * @param string|array $cond The condition array. {@see select} for details.
	 * @param string $fname The function name of the caller.
	 * @param string|array $options The query options. {@see select} for details.
	 * @param string|array $join_conds The query join conditions. {@see select} for details.
	 * @return mixed|false The value from the field, or false if nothing was found
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function selectField(
		$table, $var, $cond = '', $fname = __METHOD__, $options = [], $join_conds = []
	);

	/**
	 * A SELECT wrapper which returns a list of single field values from result rows
	 *
	 * If no result rows are returned from the query, an empty array is returned.
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::fetchFieldValues}
	 * instead, which is more readable and less error-prone.
	 *
	 * @param string|array $table Table name. {@see select} for details.
	 * @param string $var The field name to select. This must be a valid SQL
	 *   fragment: do not use unvalidated user input.
	 * @param string|array $cond The condition array. {@see select} for details.
	 * @param string $fname The function name of the caller.
	 * @param string|array $options The query options. {@see select} for details.
	 * @param string|array $join_conds The query join conditions. {@see select} for details.
	 *
	 * @return array The values from the field in the order they were returned from the DB
	 * @throws DBError If an error occurs, {@see query}
	 * @since 1.25
	 */
	public function selectFieldValues(
		$table, $var, $cond = '', $fname = __METHOD__, $options = [], $join_conds = []
	): array;

	/**
	 * Execute a SELECT query constructed using the various parameters provided
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::fetchResultSet}
	 * instead, which is more readable and less error-prone.
	 *
	 * @param string|array $table Table name(s)
	 *
	 * May be either an array of table names, or a single string holding a table
	 * name. If an array is given, table aliases can be specified, for example:
	 *
	 *    [ 'a' => 'user' ]
	 *
	 * This includes the user table in the query, with the alias "a" available
	 * for use in field names (e.g. a.user_name).
	 *
	 * A derived table, defined by the result of selectSQLText(), requires an alias
	 * key and a Subquery instance value which wraps the SQL query, for example:
	 *
	 *    [ 'c' => new Subquery( 'SELECT ...' ) ]
	 *
	 * Joins using parentheses for grouping (since MediaWiki 1.31) may be
	 * constructed using nested arrays. For example,
	 *
	 *    [ 'tableA', 'nestedB' => [ 'tableB', 'b2' => 'tableB2' ] ]
	 *
	 * along with `$join_conds` like
	 *
	 *    [ 'b2' => [ 'JOIN', 'b_id = b2_id' ], 'nestedB' => [ 'LEFT JOIN', 'b_a = a_id' ] ]
	 *
	 * will produce SQL something like
	 *
	 *    FROM tableA LEFT JOIN (tableB JOIN tableB2 AS b2 ON (b_id = b2_id)) ON (b_a = a_id)
	 *
	 * All of the table names given here are automatically run through
	 * Database::tableName(), which causes the table prefix (if any) to be
	 * added, and various other table name mappings to be performed.
	 *
	 * Do not use untrusted user input as a table name. Alias names should
	 * not have characters outside of the Basic multilingual plane.
	 *
	 * @param string|array $vars Field name(s)
	 *
	 * May be either a field name or an array of field names. The field names
	 * can be complete fragments of SQL, for direct inclusion into the SELECT
	 * query. If an array is given, field aliases can be specified, for example:
	 *
	 *   [ 'maxrev' => 'MAX(rev_id)' ]
	 *
	 * This includes an expression with the alias "maxrev" in the query.
	 *
	 * If an expression is given, care must be taken to ensure that it is
	 * DBMS-independent.
	 *
	 * Untrusted user input must not be passed to this parameter.
	 *
	 * @param string|array $conds
	 *
	 * May be either a string containing a single condition, or an array of
	 * conditions. If an array is given, the conditions constructed from each
	 * element are combined with AND.
	 *
	 * Array elements may take one of two forms:
	 *
	 *   - Elements with a numeric key are interpreted as raw SQL fragments.
	 *   - Elements with a string key are interpreted as equality conditions,
	 *     where the key is the field name.
	 *     - If the value of such an array element is a scalar (such as a
	 *       string), it will be treated as data and thus quoted appropriately.
	 *       If it is null, an IS NULL clause will be added.
	 *     - If the value is an array, an IN (...) clause will be constructed
	 *       from its non-null elements, and an IS NULL clause will be added
	 *       if null is present, such that the field may match any of the
	 *       elements in the array. The non-null elements will be quoted.
	 *
	 * Note that expressions are often DBMS-dependent in their syntax.
	 * DBMS-independent wrappers are provided for constructing several types of
	 * expression commonly used in condition queries. See:
	 *    - IDatabase::buildLike()
	 *    - IDatabase::conditional()
	 *
	 * Untrusted user input is safe in the values of string keys, however untrusted
	 * input must not be used in the array key names or in the values of numeric keys.
	 * Escaping of untrusted input used in values of numeric keys should be done via
	 * IDatabase::addQuotes()
	 *
	 * Use an empty array, string, or IDatabase::ALL_ROWS to select all rows.
	 *
	 * You *can* put simple join conditions here, but this is strongly discouraged.
	 * Instead of
	 *
	 *     // $conds...
	 *     'rev_actor = actor_id',
	 *
	 * use (see below for $join_conds):
	 *
	 *     // $join_conds...
	 *     'actor' => [ 'JOIN', 'rev_actor = actor_id' ],
	 *
	 * @param string $fname Caller function name
	 *
	 * @param string|array $options Query options
	 *
	 * Optional: Array of query options. Boolean options are specified by
	 * including them in the array as a string value with a numeric key, for
	 * example:
	 *
	 *    [ 'FOR UPDATE' ]
	 *
	 * The supported options are:
	 *
	 *   - OFFSET: Skip this many rows at the start of the result set. OFFSET
	 *     with LIMIT can theoretically be used for paging through a result set,
	 *     but this is discouraged for performance reasons.
	 *
	 *   - LIMIT: Integer: return at most this many rows. The rows are sorted
	 *     and then the first rows are taken until the limit is reached. LIMIT
	 *     is applied to a result set after OFFSET.
	 *
	 *   - LOCK IN SHARE MODE: Boolean: lock the returned rows so that they can't be
	 *     changed until the next COMMIT. Cannot be used with aggregate functions
	 *     (COUNT, MAX, etc., but also DISTINCT).
	 *
	 *   - FOR UPDATE: Boolean: lock the returned rows so that they can't be
	 *     changed nor read with LOCK IN SHARE MODE until the next COMMIT.
	 *     Cannot be used with aggregate functions (COUNT, MAX, etc., but also DISTINCT).
	 *
	 *   - DISTINCT: Boolean: return only unique result rows.
	 *
	 *   - GROUP BY: May be either an SQL fragment string naming a field or
	 *     expression to group by, or an array of such SQL fragments.
	 *
	 *   - HAVING: May be either an string containing a HAVING clause or an array of
	 *     conditions building the HAVING clause. If an array is given, the conditions
	 *     constructed from each element are combined with AND.
	 *
	 *   - ORDER BY: May be either an SQL fragment giving a field name or
	 *     expression to order by, or an array of such SQL fragments.
	 *
	 *   - USE INDEX: This may be either a string giving the index name to use
	 *     for the query, or an array. If it is an associative array, each key
	 *     gives the table name (or alias), each value gives the index name to
	 *     use for that table. All strings are SQL fragments and so should be
	 *     validated by the caller.
	 *
	 *   - IGNORE INDEX: This may be either be a string giving an index name to
	 *     ignore for the query, or an array. If it is an associative array,
	 *     each key gives the table name (or alias), each value gives the index
	 *     name to ignore for that table. All strings are SQL fragments and so
	 *     should be validated by the caller.
	 *
	 *   - EXPLAIN: In MySQL, this causes an EXPLAIN SELECT query to be run,
	 *     instead of SELECT.
	 *
	 *   - MAX_EXECUTION_TIME: (only in MySQL/MariaDB) maximum allowed time to
	 *     run the query in milliseconds (if database supports it).
	 *
	 * And also the following boolean MySQL extensions, see the MySQL manual
	 * for documentation:
	 *
	 *    - STRAIGHT_JOIN
	 *    - SQL_BIG_RESULT
	 *    - SQL_BUFFER_RESULT
	 *    - SQL_SMALL_RESULT
	 *    - SQL_CALC_FOUND_ROWS
	 *
	 * @param string|array $join_conds Join conditions
	 *
	 * Optional associative array of table-specific join conditions.
	 * Simple conditions can also be specified in the regular $conds,
	 * but this is strongly discouraged in favor of the more explicit syntax here.
	 *
	 * The key of the array contains the table name or alias. The value is an
	 * array with two elements, numbered 0 and 1. The first gives the type of
	 * join, the second is the same as the $conds parameter. Thus it can be
	 * an SQL fragment, or an array where the string keys are equality and the
	 * numeric keys are SQL fragments all AND'd together. For example:
	 *
	 *    [ 'page' => [ 'LEFT JOIN', 'page_latest=rev_id' ] ]
	 *
	 * @return IResultWrapper Resulting rows
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function select(
		$table,
		$vars,
		$conds = '',
		$fname = __METHOD__,
		$options = [],
		$join_conds = []
	);

	/**
	 * Wrapper to IDatabase::select() that only fetches one row (via LIMIT)
	 *
	 * If the query returns no rows, false is returned.
	 *
	 * This method is convenient for fetching a row based on a unique key condition.
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::fetchRow}
	 * instead, which is more readable and less error-prone.
	 *
	 * @param string|array $table Table name
	 * @param string|array $vars Field names
	 * @param string|array $conds Conditions
	 * @param string $fname Caller function name
	 * @param string|array $options Query options
	 * @param array|string $join_conds Join conditions
	 * @return stdClass|false
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function selectRow(
		$table,
		$vars,
		$conds,
		$fname = __METHOD__,
		$options = [],
		$join_conds = []
	);

	/**
	 * Estimate the number of rows in dataset
	 *
	 * MySQL allows you to estimate the number of rows that would be returned
	 * by a SELECT query, using EXPLAIN SELECT. The estimate is provided using
	 * index cardinality statistics, and is notoriously inaccurate, especially
	 * when large numbers of rows have recently been added or deleted.
	 *
	 * For DBMSs that don't support fast result size estimation, this function
	 * will actually perform the SELECT COUNT(*).
	 *
	 * Takes the same arguments as IDatabase::select().
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::estimateRowCount}
	 * instead, which is more readable and less error-prone.
	 *
	 * @param string|string[] $tables Table name(s)
	 * @param string $var Column for which NULL values are not counted [default "*"]
	 * @param array|string $conds Filters on the table
	 * @param string $fname Function name for profiling
	 * @param array $options Options for select
	 * @param array|string $join_conds Join conditions
	 * @return int Row count
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function estimateRowCount(
		$tables, $var = '*', $conds = '', $fname = __METHOD__, $options = [], $join_conds = []
	);

	/**
	 * Get the number of rows in dataset
	 *
	 * This is useful when trying to do COUNT(*) but with a LIMIT for performance.
	 *
	 * Takes the same arguments as IDatabase::select().
	 *
	 * New callers should use {@link newSelectQueryBuilder} with {@link SelectQueryBuilder::fetchRowCount}
	 * instead, which is more readable and less error-prone.
	 *
	 * @since 1.27 Added $join_conds parameter
	 *
	 * @param string|string[] $tables Table name(s)
	 * @param string $var Column for which NULL values are not counted [default "*"]
	 * @param array|string $conds Filters on the table
	 * @param string $fname Function name for profiling
	 * @param array $options Options for select
	 * @param array $join_conds Join conditions (since 1.27)
	 * @return int Row count
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function selectRowCount(
		$tables, $var = '*', $conds = '', $fname = __METHOD__, $options = [], $join_conds = []
	);

	/**
	 * Returns true if DBs are assumed to be on potentially different servers
	 *
	 * In systems like mysql/mariadb, different databases can easily be referenced on a single
	 * connection merely by name, even in a single query via JOIN. On the other hand, Postgres
	 * treats databases as logically separate, with different database users, requiring special
	 * mechanisms like postgres_fdw to "mount" foreign DBs. This is true even among DBs on the
	 * same server. Changing the selected database via selectDomain() requires a new connection.
	 *
	 * @return bool
	 * @since 1.29
	 */
	public function databasesAreIndependent();

	/**
	 * Set the current domain (database, schema, and table prefix)
	 *
	 * This will throw an error for some database types if the database is unspecified
	 *
	 * This should only be called by a load balancer or if the handle is not attached to one
	 *
	 * @param string|DatabaseDomain $domain
	 * @throws DBConnectionError If databasesAreIndependent() is true and connection change fails
	 * @throws DBError On query error, if domain changes are disallowed, or the domain is invalid
	 * @since 1.32
	 */
	public function selectDomain( $domain );

	/**
	 * Get the current database name; null if there isn't one
	 *
	 * @return string|null
	 */
	public function getDBname();

	/**
	 * Get the hostname or IP address of the server
	 *
	 * @return string|null
	 */
	public function getServer();

	/**
	 * Get the readable name for the server
	 *
	 * @return string Readable server name, falling back to the hostname or IP address
	 * @since 1.36
	 */
	public function getServerName();

	/**
	 * Determines if the last failure was due to a deadlock
	 *
	 * Note that during a deadlock, the prior transaction will have been lost
	 *
	 * @return bool
	 */
	public function wasDeadlock();

	/**
	 * Determines if the last failure was due to a lock timeout
	 *
	 * Note that during a lock wait timeout, the prior transaction will have been lost
	 *
	 * @return bool
	 */
	public function wasLockTimeout();

	/**
	 * Determines if the last query error was due to a dropped connection
	 *
	 * Note that during a connection loss, the prior transaction will have been lost
	 *
	 * @return bool
	 * @since 1.31
	 */
	public function wasConnectionLoss();

	/**
	 * Determines if the last failure was due to the database being read-only
	 *
	 * @return bool
	 */
	public function wasReadOnlyError();

	/**
	 * Determines if the last query error was due to something outside of the query itself
	 *
	 * Note that the transaction may have been lost, discarding prior writes and results
	 *
	 * @return bool
	 * @deprecated Since 1.40
	 */
	public function wasErrorReissuable();

	/**
	 * Wait for the replica server to catch up to a given primary server position
	 *
	 * Note that this does not start any new transactions.
	 *
	 * Callers might want to flush any existing transaction before invoking this method.
	 * Upon success, this assures that replica server queries will reflect all changes up
	 * to the given position, without interference from prior REPEATABLE-READ snapshots.
	 *
	 * @param DBPrimaryPos $pos
	 * @param int $timeout The maximum number of seconds to wait for synchronisation
	 * @return int|null Zero if the replica DB server was past that position already,
	 *   greater than zero if we waited for some period of time, less than
	 *   zero if it timed out, and null on error
	 * @throws DBError If an error occurs, {@see query}
	 * @since 1.37
	 */
	public function primaryPosWait( DBPrimaryPos $pos, $timeout );

	/**
	 * Get the replication position of this replica DB
	 *
	 * @return DBPrimaryPos|false False if this is not a replica DB
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function getReplicaPos();

	/**
	 * Ping the server and try to reconnect if it there is no connection
	 *
	 * @return bool Success or failure
	 */
	public function ping();

	/**
	 * Get the amount of replication lag for this database server
	 *
	 * Callers should avoid using this method while a transaction is active
	 *
	 * @return float|int|false Database replication lag in seconds or false on error
	 * @throws DBError If an error occurs, {@see query}
	 */
	public function getLag();

	/**
	 * Get the replica server lag when the current transaction started
	 * or a general lag estimate if not transaction is active
	 *
	 * This is useful when transactions might use snapshot isolation
	 * (e.g. REPEATABLE-READ in innodb), so the "real" lag of that data
	 * is this lag plus transaction duration. If they don't, it is still
	 * safe to be pessimistic. In AUTOCOMMIT mode, this still gives an
	 * indication of the staleness of subsequent reads.
	 *
	 * @return array ('lag': seconds or false on error, 'since': UNIX timestamp of BEGIN)
	 * @throws DBError If an error occurs, {@see query}
	 * @since 1.27
	 */
	public function getSessionLagStatus();

	/**
	 * Some DBMSs have a special format for inserting into blob fields, they
	 * don't allow simple quoted strings to be inserted. To insert into such
	 * a field, pass the data through this function before passing it to
	 * IDatabase::insert().
	 *
	 * @param string $b
	 * @return string|Blob
	 * @throws DBError
	 */
	public function encodeBlob( $b );

	/**
	 * Some DBMSs return a special placeholder object representing blob fields
	 * in result objects. Pass the object through this function to return the
	 * original string.
	 *
	 * @param string|Blob $b
	 * @return string
	 * @throws DBError
	 */
	public function decodeBlob( $b );

	/**
	 * Get a debugging string that mentions the database type, the ID of this instance,
	 * and the ID of any underlying connection resource or driver object if one is present
	 *
	 * @return string "<db type> object #<X>" or "<db type> object #<X> (resource/handle id #<Y>)"
	 * @since 1.34
	 */
	public function __toString();
}
