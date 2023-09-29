<?php

use Wikimedia\Rdbms\IDatabase;

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
 * @ingroup Maintenance
 */

/**
 * Allows iterating a large number of rows in batches transparently.
 * By default when iterated over returns the full query result as an
 * array of rows.  Can be wrapped in RecursiveIteratorIterator to
 * collapse those arrays into a single stream of rows queried in batches.
 *
 * @newable
 */
class BatchRowIterator implements RecursiveIterator {

	/**
	 * @var IDatabase The database to read from
	 */
	protected $db;

	/**
	 * @var string|array The name or names of the table to read from
	 */
	protected $table;

	/**
	 * @var array The name of the primary key(s)
	 */
	protected $primaryKey;

	/**
	 * @var int The number of rows to fetch per iteration
	 */
	protected $batchSize;

	/**
	 * @var array Array of strings containing SQL conditions to add to the query
	 */
	protected $conditions = [];

	/**
	 * @var array
	 */
	protected $joinConditions = [];

	/**
	 * @var array List of column names to select from the table suitable for use
	 *  with IDatabase::select()
	 */
	protected $fetchColumns;

	/**
	 * @var string SQL Order by condition generated from $this->primaryKey
	 */
	protected $orderBy;

	/**
	 * @var array The current iterator value
	 */
	private $current = [];

	/**
	 * @var int 0-indexed number of pages fetched since self::reset()
	 */
	private $key = -1;

	/**
	 * @var array Additional query options
	 */
	protected $options = [];

	/**
	 * @var string|null For debugging which method is using this class.
	 */
	protected $caller;

	/**
	 * @stable to call
	 *
	 * @param IDatabase $db The database to read from
	 * @param string|array $table The name or names of the table to read from
	 * @param string|array $primaryKey The name or names of the primary key columns
	 * @param int $batchSize The number of rows to fetch per iteration
	 * @throws InvalidArgumentException
	 */
	public function __construct( IDatabase $db, $table, $primaryKey, $batchSize ) {
		if ( $batchSize < 1 ) {
			throw new InvalidArgumentException( 'Batch size must be at least 1 row.' );
		}
		$this->db = $db;
		$this->table = $table;
		$this->primaryKey = (array)$primaryKey;
		$this->fetchColumns = $this->primaryKey;
		$this->orderBy = implode( ' ASC,', $this->primaryKey ) . ' ASC';
		$this->batchSize = $batchSize;
	}

	/**
	 * @param array $conditions Query conditions suitable for use with
	 *  IDatabase::select
	 */
	public function addConditions( array $conditions ) {
		$this->conditions = array_merge( $this->conditions, $conditions );
	}

	/**
	 * @param array $options Query options suitable for use with
	 *  IDatabase::select
	 */
	public function addOptions( array $options ) {
		$this->options = array_merge( $this->options, $options );
	}

	/**
	 * @param array $conditions Query join conditions suitable for use
	 *  with IDatabase::select
	 */
	public function addJoinConditions( array $conditions ) {
		$this->joinConditions = array_merge( $this->joinConditions, $conditions );
	}

	/**
	 * @param array $columns List of column names to select from the
	 *  table suitable for use with IDatabase::select()
	 */
	public function setFetchColumns( array $columns ) {
		// If it's not the all column selector merge in the primary keys we need
		if ( count( $columns ) === 1 && reset( $columns ) === '*' ) {
			$this->fetchColumns = $columns;
		} else {
			$this->fetchColumns = array_unique( array_merge(
				$this->primaryKey,
				$columns
			) );
		}
	}

	/**
	 * Use ->setCaller( __METHOD__ ) to indicate which code is using this
	 * class. Only used in debugging output.
	 * @since 1.36
	 *
	 * @param string $caller
	 * @return self
	 */
	public function setCaller( $caller ) {
		$this->caller = $caller;

		return $this;
	}

	/**
	 * Extracts the primary key(s) from a database row.
	 *
	 * @param stdClass $row An individual database row from this iterator
	 * @return array Map of primary key column to value within the row
	 */
	public function extractPrimaryKeys( $row ) {
		$pk = [];
		foreach ( $this->primaryKey as $alias => $column ) {
			$name = is_numeric( $alias ) ? $column : $alias;
			$pk[$name] = $row->{$name};
		}
		return $pk;
	}

	/**
	 * @return array The most recently fetched set of rows from the database
	 */
	public function current(): array {
		return $this->current;
	}

	/**
	 * @return int 0-indexed count of the page number fetched
	 */
	public function key(): int {
		return $this->key;
	}

	/**
	 * Reset the iterator to the beginning of the table.
	 */
	public function rewind(): void {
		$this->key = -1; // self::next() will turn this into 0
		$this->current = [];
		$this->next();
	}

	/**
	 * @return bool True when the iterator is in a valid state
	 */
	public function valid(): bool {
		return (bool)$this->current;
	}

	/**
	 * @return bool True when this result set has rows
	 */
	public function hasChildren(): bool {
		return $this->current && count( $this->current );
	}

	/**
	 * @return null|RecursiveIterator
	 */
	public function getChildren(): ?RecursiveIterator {
		return new NotRecursiveIterator( new ArrayIterator( $this->current ) );
	}

	/**
	 * Fetch the next set of rows from the database.
	 */
	public function next(): void {
		$caller = __METHOD__;
		if ( (string)$this->caller !== '' ) {
			$caller .= " (for {$this->caller})";
		}

		$res = $this->db->select(
			$this->table,
			$this->fetchColumns,
			$this->buildConditions(),
			$caller,
			[
				'LIMIT' => $this->batchSize,
				'ORDER BY' => $this->orderBy,
			] + $this->options,
			$this->joinConditions
		);

		// The iterator is converted to an array because in addition to
		// returning it in self::current() we need to use the end value
		// in self::buildConditions()
		$this->current = iterator_to_array( $res );
		$this->key++;
	}

	/**
	 * Uses the primary key list and the maximal result row from the
	 * previous iteration to build an SQL condition sufficient for
	 * selecting the next page of results.
	 *
	 * @return array The SQL conditions necessary to select the next set
	 *  of rows in the batched query
	 */
	protected function buildConditions() {
		if ( !$this->current ) {
			return $this->conditions;
		}

		$maxRow = end( $this->current );
		$maximumValues = [];
		foreach ( $this->primaryKey as $alias => $column ) {
			$name = is_numeric( $alias ) ? $column : $alias;
			$maximumValues[$column] = $maxRow->$name;
		}

		$conditions = $this->conditions;
		$conditions[] = $this->db->buildComparison( '>', $maximumValues );

		return $conditions;
	}
}
