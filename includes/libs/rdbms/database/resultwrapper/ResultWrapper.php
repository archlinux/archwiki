<?php

namespace Wikimedia\Rdbms;

use OutOfBoundsException;
use stdClass;

/**
 * Result wrapper for grabbing data queried from an IDatabase object
 *
 * Only IDatabase-related classes should construct these. Other code may
 * use the FakeResultWrapper class for convenience or compatibility shims.
 *
 * Note that using the Iterator methods in combination with the non-Iterator
 * IDatabase result iteration functions may cause rows to be skipped or repeated.
 *
 * By default, this will use the iteration methods of the IDatabase handle if provided.
 * Subclasses can override methods to make it solely work on the result resource instead.
 *
 * @ingroup Database
 * @stable to override
 */
abstract class ResultWrapper implements IResultWrapper {
	/**
	 * @var int The offset of the row that would be returned by the next call
	 *   to fetchObject().
	 */
	protected $nextPos = 0;

	/**
	 * @var int The offset of the current row that would be returned by current()
	 *   and may have been previously returned by fetchObject().
	 */
	protected $currentPos = 0;

	/**
	 * @var stdClass|array|bool|null The row at $this->currentPos, or null if it has
	 *   not yet been retrieved, or false if the current row was past the end.
	 */
	protected $currentRow;

	/**
	 * @var string[]|null Cache of field names
	 */
	private $fieldNames;

	/**
	 * Get the underlying RDBMS driver-specific result resource
	 *
	 * The result resource field should not be accessed from non-Database related classes.
	 * It is database class specific and is stored here to associate iterators with queries.
	 *
	 * @since 1.34
	 * @deprecated since 1.37
	 *
	 * @param self|mixed $res
	 * @return mixed
	 */
	public static function unwrap( $res ) {
		wfDeprecated( __METHOD__, '1.37' );
		if ( $res instanceof MysqliResultWrapper ) {
			return $res->getInternalResult();
		} elseif ( $res instanceof SqliteResultWrapper ) {
			return $res->getInternalResult();
		} elseif ( $res instanceof PostgresResultWrapper ) {
			return $res->getInternalResult();
		} elseif ( $res instanceof IResultWrapper ) {
			throw new \InvalidArgumentException( __METHOD__ . ': $res does not support unwrap()' );
		} else {
			return $res;
		}
	}

	/**
	 * Get the number of rows in the result set
	 *
	 * @since 1.37
	 * @return int
	 */
	abstract protected function doNumRows();

	/**
	 * Get the next row as a stdClass object, or false if iteration has
	 * proceeded past the end. The offset within the result set is in
	 * $this->currentPos.
	 *
	 * @since 1.37
	 * @return stdClass|bool
	 */
	abstract protected function doFetchObject();

	/**
	 * Get the next row as an array containing the data duplicated, once with
	 * string keys and once with numeric keys, per the PDO::FETCH_BOTH
	 * convention. Or false if iteration has proceeded past the end.
	 *
	 * @return array|bool
	 */
	abstract protected function doFetchRow();

	/**
	 * Modify the current cursor position to the row with the specified offset.
	 * If $pos is out of bounds, the behaviour is undefined.
	 *
	 * @param int $pos
	 */
	abstract protected function doSeek( $pos );

	/**
	 * Free underlying data. It is not necessary to do anything.
	 */
	abstract protected function doFree();

	/**
	 * Get the field names in the result set.
	 *
	 * @return string[]
	 */
	abstract protected function doGetFieldNames();

	public function numRows() {
		return $this->doNumRows();
	}

	public function count() {
		return $this->doNumRows();
	}

	public function fetchObject() {
		$this->currentPos = $this->nextPos++;
		$this->currentRow = $this->doFetchObject();
		return $this->currentRow;
	}

	public function fetchRow() {
		$this->currentPos = $this->nextPos++;
		$this->currentRow = $this->doFetchRow();
		return $this->currentRow;
	}

	public function seek( $pos ) {
		$numRows = $this->numRows();
		// Allow seeking to zero if there are no results
		$max = $numRows ? $numRows - 1 : 0;
		if ( $pos < 0 || $pos > $max ) {
			throw new OutOfBoundsException( __METHOD__ . ': invalid position' );
		}
		if ( $numRows ) {
			$this->doSeek( $pos );
		}
		$this->nextPos = $pos;
		$this->currentPos = $pos;
		$this->currentRow = null;
	}

	public function free() {
		$this->doFree();
		$this->currentRow = false;
	}

	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->seek( 0 );
	}

	public function current() {
		if ( $this->currentRow === null ) {
			$this->currentRow = $this->fetchObject();
		}

		return $this->currentRow;
	}

	public function key() {
		return $this->currentPos;
	}

	public function next() {
		return $this->fetchObject();
	}

	#[\ReturnTypeWillChange]
	public function valid() {
		return $this->currentPos >= 0
			&& $this->currentPos < $this->numRows();
	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = $this->doGetFieldNames();
		}
		return $this->fieldNames;
	}
}

/**
 * @deprecated since 1.29
 */
class_alias( ResultWrapper::class, 'ResultWrapper' );
