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
namespace Wikimedia\Rdbms\Platform;

use Wikimedia\Rdbms\DBLanguageError;

/**
 * @since 1.39
 * @see ISQLPlatform
 */
class MySQLPlatform extends SQLPlatform {
	/**
	 * MySQL uses `backticks` for identifier quoting instead of the sql standard "double quotes".
	 *
	 * @param string $s
	 * @return string
	 */
	public function addIdentifierQuotes( $s ) {
		// Characters in the range \u0001-\uFFFF are valid in a quoted identifier
		// Remove NUL bytes and escape backticks by doubling
		return '`' . str_replace( [ "\0", '`' ], [ '', '``' ], $s ) . '`';
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isQuotedIdentifier( $name ) {
		return strlen( $name ) && $name[0] == '`' && substr( $name, -1, 1 ) == '`';
	}

	public function buildStringCast( $field ) {
		return "CAST( $field AS BINARY )";
	}

	/**
	 * @param string $field Field or column to cast
	 * @return string
	 */
	public function buildIntegerCast( $field ) {
		return 'CAST( ' . $field . ' AS SIGNED )';
	}

	protected function normalizeJoinType( string $joinType ) {
		switch ( strtoupper( $joinType ) ) {
			case 'STRAIGHT_JOIN':
			case 'STRAIGHT JOIN':
				return 'STRAIGHT_JOIN';

			default:
				return parent::normalizeJoinType( $joinType );
		}
	}

	/**
	 * @param string $index
	 * @return string
	 */
	public function useIndexClause( $index ) {
		return "FORCE INDEX (" . $this->indexName( $index ) . ")";
	}

	/**
	 * @param string $index
	 * @return string
	 */
	public function ignoreIndexClause( $index ) {
		return "IGNORE INDEX (" . $this->indexName( $index ) . ")";
	}

	public function deleteJoinSqlText( $delTable, $joinTable, $delVar, $joinVar, $conds ) {
		if ( !$conds ) {
			throw new DBLanguageError( __METHOD__ . ' called with empty $conds' );
		}

		$delTable = $this->tableName( $delTable );
		$joinTable = $this->tableName( $joinTable );
		$sql = "DELETE $delTable FROM $delTable, $joinTable WHERE $delVar=$joinVar ";

		if ( $conds != '*' ) {
			$sql .= ' AND ' . $this->makeList( $conds, self::LIST_AND );
		}

		return $sql;
	}

	public function isTransactableQuery( $sql ) {
		return parent::isTransactableQuery( $sql ) &&
			!preg_match( '/^SELECT\s+(GET|RELEASE|IS_FREE)_LOCK\(/', $sql );
	}

	public function buildExcludedValue( $column ) {
		/* @see DatabaseMysqlBase::doUpsert() */
		// Within "INSERT INTO ON DUPLICATE KEY UPDATE" statements:
		//   - MySQL>= 8.0.20 supports and prefers "VALUES ... AS".
		//   - MariaDB >= 10.3.3 supports and prefers VALUE().
		//   - Both support the old VALUES() function
		// https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
		// https://mariadb.com/kb/en/insert-on-duplicate-key-update/
		return "VALUES($column)";
	}

	public function lockSQLText( $lockName, $timeout ) {
		$encName = $this->quoter->addQuotes( $this->makeLockName( $lockName ) );
		// Unlike NOW(), SYSDATE() gets the time at invocation rather than query start.
		// The precision argument is silently ignored for MySQL < 5.6 and MariaDB < 5.3.
		// https://dev.mysql.com/doc/refman/5.6/en/date-and-time-functions.html#function_sysdate
		// https://dev.mysql.com/doc/refman/5.6/en/fractional-seconds.html
		return "SELECT IF(GET_LOCK($encName,$timeout),UNIX_TIMESTAMP(SYSDATE(6)),NULL) AS acquired";
	}

	public function lockIsFreeSQLText( $lockName ) {
		$encName = $this->quoter->addQuotes( $this->makeLockName( $lockName ) );
		return "SELECT IS_FREE_LOCK($encName) AS unlocked";
	}

	public function unlockSQLText( $lockName ) {
		$encName = $this->quoter->addQuotes( $this->makeLockName( $lockName ) );
		return "SELECT RELEASE_LOCK($encName) AS released";
	}

	public function makeLockName( $lockName ) {
		// https://dev.mysql.com/doc/refman/5.7/en/locking-functions.html#function_get-lock
		// MySQL 5.7+ enforces a 64 char length limit.
		return ( strlen( $lockName ) > 64 ) ? sha1( $lockName ) : $lockName;
	}
}
