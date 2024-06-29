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

use FSLockManager;
use LockManager;
use NullLockManager;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Wikimedia\Rdbms\Platform\SqlitePlatform;
use Wikimedia\Rdbms\Platform\SQLPlatform;
use Wikimedia\Rdbms\Replication\ReplicationReporter;

/**
 * This is the SQLite database abstraction layer.
 *
 * See docs/sqlite.txt for development notes about MediaWiki's sqlite schema.
 *
 * @ingroup Database
 */
class DatabaseSqlite extends Database {
	/** @var string|null Directory for SQLite database files listed under their DB name */
	protected $dbDir;
	/** @var string|null Explicit path for the SQLite database file */
	protected $dbPath;
	/** @var string Transaction mode */
	protected $trxMode;

	/** @var PDO|null */
	protected $conn;

	/** @var LockManager|null (hopefully on the same server as the DB) */
	protected $lockMgr;

	/** @var string|null */
	private $version;

	/** @var array List of shared database already attached to this connection */
	private $sessionAttachedDbs = [];

	/** @var string[] See https://www.sqlite.org/lang_transaction.html */
	private const VALID_TRX_MODES = [ '', 'DEFERRED', 'IMMEDIATE', 'EXCLUSIVE' ];

	/** @var string[][] */
	private const VALID_PRAGMAS = [
		// Optimizations or requirements regarding fsync() usage
		'synchronous' => [ 'EXTRA', 'FULL', 'NORMAL', 'OFF' ],
		// Optimizations for TEMPORARY tables
		'temp_store' => [ 'FILE', 'MEMORY' ],
		// Optimizations for disk use and page cache
		'mmap_size' => 'integer',
		// How many DB pages to keep in memory
		'cache_size' => 'integer',
	];

	/** @var SQLPlatform */
	protected $platform;

	/**
	 * Additional params include:
	 *   - dbDirectory : directory containing the DB and the lock file directory
	 *   - dbFilePath  : use this to force the path of the DB file
	 *   - trxMode     : one of (deferred, immediate, exclusive)
	 * @param array $params
	 */
	public function __construct( array $params ) {
		if ( isset( $params['dbFilePath'] ) ) {
			$this->dbPath = $params['dbFilePath'];
			if ( !isset( $params['dbname'] ) || $params['dbname'] === '' ) {
				$params['dbname'] = self::generateDatabaseName( $this->dbPath );
			}
		} elseif ( isset( $params['dbDirectory'] ) ) {
			$this->dbDir = $params['dbDirectory'];
		}

		parent::__construct( $params );

		$this->trxMode = strtoupper( $params['trxMode'] ?? '' );

		$this->lockMgr = $this->makeLockManager();
		$this->platform = new SqlitePlatform(
			$this,
			$this->logger,
			$this->currentDomain,
			$this->errorLogger
		);
		$this->replicationReporter = new ReplicationReporter(
			$params['topologyRole'],
			$this->logger,
			$params['srvCache']
		);
	}

	public static function getAttributes() {
		return [
			self::ATTR_DB_IS_FILE => true,
			self::ATTR_DB_LEVEL_LOCKING => true
		];
	}

	/**
	 * @param string $filename
	 * @param array $p Options map; supports:
	 *   - flags       : (same as __construct counterpart)
	 *   - trxMode     : (same as __construct counterpart)
	 *   - dbDirectory : (same as __construct counterpart)
	 * @return DatabaseSqlite
	 * @since 1.25
	 */
	public static function newStandaloneInstance( $filename, array $p = [] ) {
		$p['dbFilePath'] = $filename;
		$p['schema'] = null;
		$p['tablePrefix'] = '';
		/** @var DatabaseSqlite $db */
		$db = ( new DatabaseFactory() )->create( 'sqlite', $p );
		'@phan-var DatabaseSqlite $db';

		return $db;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return 'sqlite';
	}

	protected function open( $server, $user, $password, $db, $schema, $tablePrefix ) {
		$this->close( __METHOD__ );

		// Note that for SQLite, $server, $user, and $pass are ignored

		if ( $schema !== null ) {
			throw $this->newExceptionAfterConnectError( "Got schema '$schema'; not supported." );
		}

		if ( $this->dbPath !== null ) {
			$path = $this->dbPath;
		} elseif ( $this->dbDir !== null ) {
			$path = self::generateFileName( $this->dbDir, $db );
		} else {
			throw $this->newExceptionAfterConnectError( "DB path or directory required" );
		}

		// Check if the database file already exists but is non-readable
		if ( !self::isProcessMemoryPath( $path ) && is_file( $path ) && !is_readable( $path ) ) {
			throw $this->newExceptionAfterConnectError( 'SQLite database file is not readable' );
		} elseif ( !in_array( $this->trxMode, self::VALID_TRX_MODES, true ) ) {
			throw $this->newExceptionAfterConnectError( "Got mode '{$this->trxMode}' for BEGIN" );
		}

		$attributes = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
			// Starting with PHP 8.1, The SQLite PDO returns proper types instead
			// of strings or null for everything. We cast every non-null value to
			// string to restore the old behavior.
			PDO::ATTR_STRINGIFY_FETCHES => true
		];
		if ( $this->getFlag( self::DBO_PERSISTENT ) ) {
			// Persistent connections can avoid some schema index reading overhead.
			// On the other hand, they can cause horrible contention with DBO_TRX.
			if ( $this->getFlag( self::DBO_TRX ) || $this->getFlag( self::DBO_DEFAULT ) ) {
				$this->logger->warning(
					__METHOD__ . ": ignoring DBO_PERSISTENT due to DBO_TRX or DBO_DEFAULT",
					$this->getLogContext()
				);
			} else {
				$attributes[PDO::ATTR_PERSISTENT] = true;
			}
		}

		try {
			// Open the database file, creating it if it does not yet exist
			$this->conn = new PDO( "sqlite:$path", null, null, $attributes );
		} catch ( PDOException $e ) {
			throw $this->newExceptionAfterConnectError( $e->getMessage() );
		}

		$this->currentDomain = new DatabaseDomain( $db, null, $tablePrefix );
		$this->platform->setPrefix( $tablePrefix );

		try {
			// Enforce LIKE to be case sensitive, just like MySQL
			$query = new Query(
				'PRAGMA case_sensitive_like = 1',
				self::QUERY_CHANGE_TRX | self::QUERY_NO_RETRY,
				'PRAGMA'
			);
			$this->query( $query, __METHOD__ );
			// Set any connection-level custom PRAGMA options
			$pragmas = array_intersect_key( $this->connectionVariables, self::VALID_PRAGMAS );
			$pragmas += $this->getDefaultPragmas();
			foreach ( $pragmas as $name => $value ) {
				$allowed = self::VALID_PRAGMAS[$name];
				if (
					( is_array( $allowed ) && in_array( $value, $allowed, true ) ) ||
					( is_string( $allowed ) && gettype( $value ) === $allowed )
				) {
					$query = new Query(
						"PRAGMA $name = $value",
						self::QUERY_CHANGE_TRX | self::QUERY_NO_RETRY,
						'PRAGMA',
						null,
						"PRAGMA $name = '?'"
					);
					$this->query( $query, __METHOD__ );
				}
			}
			$this->attachDatabasesFromTableAliases();
		} catch ( RuntimeException $e ) {
			throw $this->newExceptionAfterConnectError( $e->getMessage() );
		}
	}

	/**
	 * @return array Map of (name => value) for default values to set via PRAGMA
	 */
	private function getDefaultPragmas() {
		$variables = [];

		if ( !$this->cliMode ) {
			$variables['temp_store'] = 'MEMORY';
		}

		return $variables;
	}

	/**
	 * @return string|null SQLite DB file path
	 * @throws DBUnexpectedError
	 * @since 1.25
	 */
	public function getDbFilePath() {
		return $this->dbPath ?? self::generateFileName( $this->dbDir, $this->getDBname() );
	}

	/**
	 * @return string|null Lock file directory
	 */
	public function getLockFileDirectory() {
		if ( $this->dbPath !== null && !self::isProcessMemoryPath( $this->dbPath ) ) {
			return dirname( $this->dbPath ) . '/locks';
		} elseif ( $this->dbDir !== null && !self::isProcessMemoryPath( $this->dbDir ) ) {
			return $this->dbDir . '/locks';
		}

		return null;
	}

	/**
	 * Initialize/reset the LockManager instance
	 *
	 * @return LockManager
	 */
	private function makeLockManager(): LockManager {
		$lockDirectory = $this->getLockFileDirectory();
		if ( $lockDirectory !== null ) {
			return new FSLockManager( [
				'domain' => $this->getDomainID(),
				'lockDirectory' => $lockDirectory,
			] );
		} else {
			return new NullLockManager( [ 'domain' => $this->getDomainID() ] );
		}
	}

	/**
	 * Does not actually close the connection, just destroys the reference for GC to do its work
	 * @return bool
	 */
	protected function closeConnection() {
		$this->conn = null;
		// Release all locks, via FSLockManager::__destruct, as the base class expects
		$this->lockMgr = null;

		return true;
	}

	/**
	 * Generates a database file name. Explicitly public for installer.
	 * @param string $dir Directory where database resides
	 * @param string|bool $dbName Database name (or false from Database::factory, validated here)
	 * @return string
	 * @throws DBUnexpectedError
	 */
	public static function generateFileName( $dir, $dbName ) {
		if ( $dir == '' ) {
			throw new DBUnexpectedError( null, __CLASS__ . ": no DB directory specified" );
		} elseif ( self::isProcessMemoryPath( $dir ) ) {
			throw new DBUnexpectedError(
				null,
				__CLASS__ . ": cannot use process memory directory '$dir'"
			);
		} elseif ( !strlen( $dbName ) ) {
			throw new DBUnexpectedError( null, __CLASS__ . ": no DB name specified" );
		}

		return "$dir/$dbName.sqlite";
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function generateDatabaseName( $path ) {
		if ( preg_match( '/^(:memory:$|file::memory:)/', $path ) ) {
			// E.g. "file::memory:?cache=shared" => ":memory":
			return ':memory:';
		} elseif ( preg_match( '/^file::([^?]+)\?mode=memory(&|$)/', $path, $m ) ) {
			// E.g. "file:memdb1?mode=memory" => ":memdb1:"
			return ":{$m[1]}:";
		} else {
			// E.g. "/home/.../some_db.sqlite3" => "some_db"
			return preg_replace( '/\.sqlite\d?$/', '', basename( $path ) );
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private static function isProcessMemoryPath( $path ) {
		return preg_match( '/^(:memory:$|file:(:memory:|[^?]+\?mode=memory(&|$)))/', $path );
	}

	/**
	 * Returns version of currently supported SQLite fulltext search module or false if none present.
	 * @return string|false
	 */
	public static function getFulltextSearchModule() {
		static $cachedResult = null;
		if ( $cachedResult !== null ) {
			return $cachedResult;
		}
		$cachedResult = false;
		$table = 'dummy_search_test';

		$db = self::newStandaloneInstance( ':memory:' );
		if ( $db->query(
			"CREATE VIRTUAL TABLE $table USING FTS3(dummy_field)",
			__METHOD__,
			IDatabase::QUERY_SILENCE_ERRORS
		) ) {
			$cachedResult = 'FTS3';
		}
		$db->close( __METHOD__ );

		return $cachedResult;
	}

	/**
	 * Attaches external database to the connection handle
	 *
	 * @see https://sqlite.org/lang_attach.html
	 *
	 * @param string $name Database name to be used in queries like
	 *   SELECT foo FROM dbname.table
	 * @param bool|string $file Database file name. If omitted, will be generated
	 *   using $name and configured data directory
	 * @param string $fname Calling function name
	 * @return IResultWrapper
	 */
	public function attachDatabase( $name, $file = false, $fname = __METHOD__ ) {
		$file = is_string( $file ) ? $file : self::generateFileName( $this->dbDir, $name );
		$encFile = $this->addQuotes( $file );
		$query = new Query(
			"ATTACH DATABASE $encFile AS $name",
			self::QUERY_CHANGE_TRX,
			'ATTACH'
		);
		return $this->query( $query, $fname );
	}

	protected function doSingleStatementQuery( string $sql ): QueryStatus {
		$res = $this->getBindingHandle()->query( $sql );
		// Note that rowCount() returns 0 for SELECT for SQLite
		return new QueryStatus(
			$res instanceof PDOStatement ? new SqliteResultWrapper( $res ) : $res,
			$res ? $res->rowCount() : 0,
			$this->lastError(),
			$this->lastErrno()
		);
	}

	protected function doSelectDomain( DatabaseDomain $domain ) {
		if ( $domain->getSchema() !== null ) {
			throw new DBExpectedError(
				$this,
				__CLASS__ . ": domain '{$domain->getId()}' has a schema component"
			);
		}

		$database = $domain->getDatabase();
		// A null database means "don't care" so leave it as is and update the table prefix
		if ( $database === null ) {
			$this->currentDomain = new DatabaseDomain(
				$this->currentDomain->getDatabase(),
				null,
				$domain->getTablePrefix()
			);
			$this->platform->setPrefix( $domain->getTablePrefix() );

			return true;
		}

		if ( $database !== $this->getDBname() ) {
			throw new DBExpectedError(
				$this,
				__CLASS__ . ": cannot change database (got '$database')"
			);
		}

		// Update that domain fields on success (no exception thrown)
		$this->currentDomain = $domain;
		$this->platform->setPrefix( $domain->getTablePrefix() );

		return true;
	}

	protected function lastInsertId() {
		// PDO::lastInsertId yields a string :(
		return (int)$this->getBindingHandle()->lastInsertId();
	}

	/**
	 * @return string
	 */
	public function lastError() {
		if ( is_object( $this->conn ) ) {
			$e = $this->conn->errorInfo();

			return $e[2] ?? $this->lastConnectError;
		}

		return 'No database connection';
	}

	/**
	 * @return int
	 */
	public function lastErrno() {
		if ( is_object( $this->conn ) ) {
			$info = $this->conn->errorInfo();

			if ( isset( $info[1] ) ) {
				return $info[1];
			}
		}

		return 0;
	}

	public function tableExists( $table, $fname = __METHOD__ ) {
		$tableRaw = $this->tableName( $table, 'raw' );
		if ( isset( $this->sessionTempTables[$tableRaw] ) ) {
			return true; // already known to exist
		}

		$encTable = $this->addQuotes( $tableRaw );
		$query = new Query(
			"SELECT 1 FROM sqlite_master WHERE type='table' AND name=$encTable",
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'SELECT'
		);
		$res = $this->query( $query, __METHOD__ );

		return (bool)$res->numRows();
	}

	/**
	 * Returns information about an index
	 * Returns false if the index does not exist
	 * - if errors are explicitly ignored, returns NULL on failure
	 *
	 * @param string $table
	 * @param string $index
	 * @param string $fname
	 * @return array|false
	 */
	public function indexInfo( $table, $index, $fname = __METHOD__ ) {
		$query = new Query(
			'PRAGMA index_info(' . $this->addQuotes( $this->platform->indexName( $index ) ) . ')',
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'PRAGMA'
		);
		$res = $this->query( $query, $fname );
		if ( !$res || $res->numRows() == 0 ) {
			return false;
		}
		$info = [];
		foreach ( $res as $row ) {
			$info[] = $row->name;
		}

		return $info;
	}

	/**
	 * @param string $table
	 * @param string $index
	 * @param string $fname
	 * @return bool|null
	 */
	public function indexUnique( $table, $index, $fname = __METHOD__ ) {
		$row = $this->selectRow( 'sqlite_master', '*',
			[
				'type' => 'index',
				'name' => $this->platform->indexName( $index ),
			], $fname );
		if ( !$row || !isset( $row->sql ) ) {
			return null;
		}

		// $row->sql will be of the form CREATE [UNIQUE] INDEX ...
		$indexPos = strpos( $row->sql, 'INDEX' );
		if ( $indexPos === false ) {
			return null;
		}
		$firstPart = substr( $row->sql, 0, $indexPos );
		$options = explode( ' ', $firstPart );

		return in_array( 'UNIQUE', $options );
	}

	public function replace( $table, $uniqueKeys, $rows, $fname = __METHOD__ ) {
		$this->platform->normalizeUpsertParams( $uniqueKeys, $rows );
		if ( !$rows ) {
			return;
		}
		$encTable = $this->tableName( $table );
		[ $sqlColumns, $sqlTuples ] = $this->platform->makeInsertLists( $rows );
		// https://sqlite.org/lang_insert.html
		// Note that any auto-increment columns on conflicting rows will be reassigned
		// due to combined DELETE+INSERT semantics. This will be reflected in insertId().
		$query = new Query(
			"REPLACE INTO $encTable ($sqlColumns) VALUES $sqlTuples",
			self::QUERY_CHANGE_ROWS,
			'REPLACE',
			$table
		);
		$this->query( $query, $fname );
	}

	/**
	 * Returns the size of a text field, or -1 for "unlimited"
	 * In SQLite this is SQLITE_MAX_LENGTH, by default 1 GB. No way to query it though.
	 *
	 * @param string $table
	 * @param string $field
	 * @return int
	 */
	public function textFieldSize( $table, $field ) {
		return -1;
	}

	/**
	 * @return bool
	 */
	public function wasDeadlock() {
		return $this->lastErrno() == 5; // SQLITE_BUSY
	}

	/**
	 * @return bool
	 */
	public function wasReadOnlyError() {
		return $this->lastErrno() == 8; // SQLITE_READONLY;
	}

	protected function isConnectionError( $errno ) {
		return $errno == 17; // SQLITE_SCHEMA;
	}

	protected function isKnownStatementRollbackError( $errno ) {
		// ON CONFLICT ROLLBACK clauses make it so that SQLITE_CONSTRAINT error is
		// ambiguous with regard to whether it implies a ROLLBACK or an ABORT happened.
		// https://sqlite.org/lang_createtable.html#uniqueconst
		// https://sqlite.org/lang_conflict.html
		return false;
	}

	public function serverIsReadOnly() {
		$this->assertHasConnectionHandle();

		$path = $this->getDbFilePath();

		return ( !self::isProcessMemoryPath( $path ) && !is_writable( $path ) );
	}

	/**
	 * @return string Wikitext of a link to the server software's web site
	 */
	public function getSoftwareLink() {
		return "[{{int:version-db-sqlite-url}} SQLite]";
	}

	/**
	 * @return string Version information from the database
	 */
	public function getServerVersion() {
		if ( $this->version === null ) {
			$this->version = $this->getBindingHandle()->getAttribute( PDO::ATTR_SERVER_VERSION );
		}

		return $this->version;
	}

	/**
	 * Get information about a given field
	 * Returns false if the field does not exist.
	 *
	 * @param string $table
	 * @param string $field
	 * @return SQLiteField|false False on failure
	 */
	public function fieldInfo( $table, $field ) {
		$tableRaw = $this->tableName( $table, 'raw' );
		$query = new Query(
			'PRAGMA table_info(' . $this->addQuotes( $tableRaw ) . ')',
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'PRAGMA'
		);
		$res = $this->query( $query, __METHOD__ );
		foreach ( $res as $row ) {
			if ( $row->name == $field ) {
				return new SQLiteField( $row, $tableRaw );
			}
		}

		return false;
	}

	protected function doBegin( $fname = '' ) {
		if ( $this->trxMode != '' ) {
			$sql = "BEGIN {$this->trxMode}";
		} else {
			$sql = 'BEGIN';
		}
		$query = new Query( $sql, self::QUERY_CHANGE_TRX, 'BEGIN' );
		$this->query( $query, $fname );
	}

	/**
	 * @param string $s
	 * @return string
	 */
	public function strencode( $s ) {
		return substr( $this->addQuotes( $s ), 1, -1 );
	}

	/**
	 * @param string $b
	 * @return Blob
	 */
	public function encodeBlob( $b ) {
		return new Blob( $b );
	}

	/**
	 * @param Blob|string $b
	 * @return string
	 */
	public function decodeBlob( $b ) {
		if ( $b instanceof Blob ) {
			$b = $b->fetch();
		}
		if ( $b === null ) {
			// An empty blob is decoded as null in PHP before PHP 8.1.
			// It was probably fixed as a side-effect of caa710037e663fd78f67533b29611183090068b2
			$b = '';
		}

		return $b;
	}

	/**
	 * @param string|int|float|null|bool|Blob $s
	 * @return string
	 */
	public function addQuotes( $s ) {
		if ( $s instanceof Blob ) {
			return "x'" . bin2hex( $s->fetch() ) . "'";
		} elseif ( is_bool( $s ) ) {
			return (string)(int)$s;
		} elseif ( is_int( $s ) ) {
			return (string)$s;
		} elseif ( strpos( (string)$s, "\0" ) !== false ) {
			// SQLite doesn't support \0 in strings, so use the hex representation as a workaround.
			// This is a known limitation of SQLite's mprintf function which PDO
			// should work around, but doesn't. I have reported this to php.net as bug #63419:
			// https://bugs.php.net/bug.php?id=63419
			// There was already a similar report for SQLite3::escapeString, bug #62361:
			// https://bugs.php.net/bug.php?id=62361
			// There is an additional bug regarding sorting this data after insert
			// on older versions of sqlite shipped with ubuntu 12.04
			// https://phabricator.wikimedia.org/T74367
			$this->logger->debug(
				__FUNCTION__ .
				': Quoting value containing null byte. ' .
				'For consistency all binary data should have been ' .
				'first processed with self::encodeBlob()'
			);
			return "x'" . bin2hex( (string)$s ) . "'";
		} else {
			return $this->getBindingHandle()->quote( (string)$s );
		}
	}

	public function doLockIsFree( string $lockName, string $method ) {
		// Only locks by this thread will be checked
		return true;
	}

	public function doLock( string $lockName, string $method, int $timeout ) {
		$status = $this->lockMgr->lock( [ $lockName ], LockManager::LOCK_EX, $timeout );
		if (
			$this->lockMgr instanceof FSLockManager &&
			$status->hasMessage( 'lockmanager-fail-openlock' )
		) {
			throw new DBError( $this, "Cannot create directory \"{$this->getLockFileDirectory()}\"" );
		}

		return $status->isOK() ? microtime( true ) : null;
	}

	public function doUnlock( string $lockName, string $method ) {
		return $this->lockMgr->unlock( [ $lockName ], LockManager::LOCK_EX )->isGood();
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @param bool $temporary
	 * @param string $fname
	 * @return bool|IResultWrapper
	 * @throws RuntimeException
	 */
	public function duplicateTableStructure(
		$oldName, $newName, $temporary = false, $fname = __METHOD__
	) {
		$query = new Query(
			"SELECT sql FROM sqlite_master WHERE tbl_name=" .
			$this->addQuotes( $oldName ) . " AND type='table'",
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'SELECT'
		);
		$res = $this->query( $query, $fname );
		$obj = $res->fetchObject();
		if ( !$obj ) {
			throw new RuntimeException( "Couldn't retrieve structure for table $oldName" );
		}
		$sqlCreateTable = $obj->sql;
		$sqlCreateTable = preg_replace(
			'/(?<=\W)"?' .
				preg_quote( trim( $this->platform->addIdentifierQuotes( $oldName ), '"' ), '/' ) .
				'"?(?=\W)/',
			$this->platform->addIdentifierQuotes( $newName ),
			$sqlCreateTable,
			1
		);
		$flags = self::QUERY_CHANGE_SCHEMA | self::QUERY_PSEUDO_PERMANENT;
		if ( $temporary ) {
			if ( preg_match( '/^\\s*CREATE\\s+VIRTUAL\\s+TABLE\b/i', $sqlCreateTable ) ) {
				$this->logger->debug(
					"Table $oldName is virtual, can't create a temporary duplicate." );
			} else {
				$sqlCreateTable = str_replace(
					'CREATE TABLE',
					'CREATE TEMPORARY TABLE',
					$sqlCreateTable
				);
				$flags |= self::QUERY_CREATE_TEMP;
			}
		}

		$query = new Query(
			$sqlCreateTable,
			$flags,
			$temporary ? 'CREATE TEMPORARY' : 'CREATE',
			// Use a dot to avoid double-prefixing in Database::getTempTableWrites()
			'.' . $newName
		);
		$res = $this->query( $query, $fname );

		$query = new Query(
			'PRAGMA INDEX_LIST(' . $this->addQuotes( $oldName ) . ')',
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'PRAGMA'
		);
		// Take over indexes
		$indexList = $this->query( $query, $fname );
		foreach ( $indexList as $index ) {
			if ( strpos( $index->name, 'sqlite_autoindex' ) === 0 ) {
				continue;
			}

			if ( $index->unique ) {
				$sqlIndex = 'CREATE UNIQUE INDEX';
			} else {
				$sqlIndex = 'CREATE INDEX';
			}
			// Try to come up with a new index name, given indexes have database scope in SQLite
			$indexName = $newName . '_' . $index->name;
			$sqlIndex .= ' ' . $this->platform->addIdentifierQuotes( $indexName ) .
				' ON ' . $this->platform->addIdentifierQuotes( $newName );

			$query = new Query(
				'PRAGMA INDEX_INFO(' . $this->addQuotes( $index->name ) . ')',
				self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
				'PRAGMA'
			);
			$indexInfo = $this->query( $query, $fname );
			$fields = [];
			foreach ( $indexInfo as $indexInfoRow ) {
				$fields[$indexInfoRow->seqno] = $this->addQuotes( $indexInfoRow->name );
			}

			$sqlIndex .= '(' . implode( ',', $fields ) . ')';

			$query = new Query(
				$sqlIndex,
				self::QUERY_CHANGE_SCHEMA | self::QUERY_PSEUDO_PERMANENT,
				'CREATE',
				$newName
			);
			$this->query( $query, __METHOD__ );
		}

		return $res;
	}

	/**
	 * List all tables on the database
	 *
	 * @param string|null $prefix Only show tables with this prefix, e.g. mw_
	 * @param string $fname Calling function name
	 *
	 * @return array
	 */
	public function listTables( $prefix = null, $fname = __METHOD__ ) {
		$query = new Query(
			"SELECT name FROM sqlite_master WHERE type = 'table'",
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'SELECT'
		);
		$result = $this->query( $query, $fname );

		$endArray = [];

		foreach ( $result as $table ) {
			$vars = get_object_vars( $table );
			$table = array_pop( $vars );

			if ( !$prefix || strpos( $table, $prefix ) === 0 ) {
				if ( strpos( $table, 'sqlite_' ) !== 0 ) {
					$endArray[] = $table;
				}
			}
		}

		return $endArray;
	}

	public function truncateTable( $table, $fname = __METHOD__ ) {
		$this->startAtomic( $fname );
		// Use "truncate" optimization; https://www.sqlite.org/lang_delete.html
		$query = new Query(
			"DELETE FROM " . $this->tableName( $table ),
			self::QUERY_CHANGE_SCHEMA,
			'DELETE',
			$table
		);
		$this->query( $query, $fname );

		$encMasterTable = $this->platform->addIdentifierQuotes( 'sqlite_sequence' );
		$encSequenceName = $this->addQuotes( $this->tableName( $table, 'raw' ) );
		$query = new Query(
			"DELETE FROM $encMasterTable WHERE name = $encSequenceName",
			self::QUERY_CHANGE_SCHEMA,
			'DELETE',
			'sqlite_sequence'
		);
		$this->query( $query, $fname );

		$this->endAtomic( $fname );
	}

	public function setTableAliases( array $aliases ) {
		parent::setTableAliases( $aliases );
		if ( $this->isOpen() ) {
			$this->attachDatabasesFromTableAliases();
		}
	}

	/**
	 * Issue ATTATCH statements for all unattached foreign DBs in table aliases
	 */
	private function attachDatabasesFromTableAliases() {
		foreach ( $this->platform->getTableAliases() as $params ) {
			if (
				$params['dbname'] !== $this->getDBname() &&
				!isset( $this->sessionAttachedDbs[$params['dbname']] )
			) {
				$this->attachDatabase( $params['dbname'], false, __METHOD__ );
				$this->sessionAttachedDbs[$params['dbname']] = true;
			}
		}
	}

	public function databasesAreIndependent() {
		return true;
	}

	protected function doHandleSessionLossPreconnect() {
		$this->sessionAttachedDbs = [];
		// Release all locks, via FSLockManager::__destruct, as the base class expects;
		$this->lockMgr = null;
		// Create a new lock manager instance
		$this->lockMgr = $this->makeLockManager();
	}

	protected function doFlushSession( $fname ) {
		// Release all locks, via FSLockManager::__destruct, as the base class expects
		$this->lockMgr = null;
		// Create a new lock manager instance
		$this->lockMgr = $this->makeLockManager();
	}

	/**
	 * @return PDO
	 */
	protected function getBindingHandle() {
		return parent::getBindingHandle();
	}

	protected function getInsertIdColumnForUpsert( $table ) {
		$tableRaw = $this->tableName( $table, 'raw' );
		$query = new Query(
			'PRAGMA table_info(' . $this->addQuotes( $tableRaw ) . ')',
			self::QUERY_IGNORE_DBO_TRX | self::QUERY_CHANGE_NONE,
			'PRAGMA'
		);
		$res = $this->query( $query, __METHOD__ );
		foreach ( $res as $row ) {
			if ( $row->pk && strtolower( $row->type ) === 'integer' ) {
				return $row->name;
			}
		}

		return null;
	}
}
