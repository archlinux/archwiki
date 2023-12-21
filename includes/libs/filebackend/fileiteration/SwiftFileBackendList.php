<?php
/**
 * OpenStack Swift based file backend.
 *
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
 * @ingroup FileBackend
 * @author Russ Nelson
 */

/**
 * SwiftFileBackend helper class to page through listings.
 * Swift also has a listing limit of 10,000 objects for performance.
 * Do not use this class from places outside SwiftFileBackend.
 *
 * @ingroup FileBackend
 */
abstract class SwiftFileBackendList implements Iterator {
	/** @var string[]|array[] Current page of entries; path list or (path,stat map) list */
	protected $iterableBuffer = [];

	/** @var string|null Continuation marker; the next page starts *after* this path */
	protected $continueAfter = null;

	/** @var int */
	protected $pos = 0;

	/** @var array */
	protected $params = [];

	/** @var SwiftFileBackend */
	protected $backend;

	/** @var string Container name */
	protected $container;

	/** @var string Storage directory */
	protected $dir;

	/** @var int */
	protected $suffixStart;

	private const PAGE_SIZE = 9000; // file listing buffer size

	/**
	 * @param SwiftFileBackend $backend
	 * @param string $fullCont Resolved container name
	 * @param string $dir Resolved directory relative to container
	 * @param array $params
	 * @note This defers I/O by not buffering the first page (useful for AppendIterator use)
	 * @note Do not call current()/valid() without calling rewind() first
	 */
	public function __construct( SwiftFileBackend $backend, $fullCont, $dir, array $params ) {
		$this->backend = $backend;
		$this->container = $fullCont;
		$this->dir = $dir;
		if ( substr( $this->dir, -1 ) === '/' ) {
			$this->dir = substr( $this->dir, 0, -1 ); // remove trailing slash
		}
		if ( $this->dir == '' ) { // whole container
			$this->suffixStart = 0;
		} else { // dir within container
			$this->suffixStart = strlen( $this->dir ) + 1; // size of "path/to/dir/"
		}
		$this->params = $params;
	}

	/**
	 * @see Iterator::key()
	 * @return int
	 */
	public function key(): int {
		return $this->pos;
	}

	/**
	 * @inheritDoc
	 */
	public function next(): void {
		++$this->pos;
		if ( $this->iterableBuffer === null ) {
			// Last page of entries failed to load
			return;
		}
		// Advance to the next entry in the page
		next( $this->iterableBuffer );
		// Check if there are no entries left in this page and
		// advance to the next page if this page was not empty.
		if ( !$this->valid() && count( $this->iterableBuffer ) ) {
			$this->iterableBuffer = $this->pageFromList(
				$this->container,
				$this->dir,
				$this->continueAfter,
				self::PAGE_SIZE,
				$this->params
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rewind(): void {
		$this->pos = 0;
		$this->continueAfter = null;
		$this->iterableBuffer = $this->pageFromList(
			$this->container,
			$this->dir,
			// @phan-suppress-next-line PhanTypeMismatchArgumentPropertyReferenceReal
			$this->continueAfter,
			self::PAGE_SIZE,
			$this->params
		);
	}

	/**
	 * @see Iterator::valid()
	 * @return bool
	 */
	public function valid(): bool {
		if ( $this->iterableBuffer === null ) {
			// Last page of entries failed to load
			return false;
		}
		// Note that entries (paths/tuples) are never boolean
		return ( current( $this->iterableBuffer ) !== false );
	}

	/**
	 * Get the next page of entries
	 *
	 * @param string $container Resolved container name
	 * @param string $dir Resolved path relative to container
	 * @param string &$after @phan-output-reference Continuation marker
	 * @param int $limit
	 * @param array $params
	 * @return Traversable|array
	 */
	abstract protected function pageFromList( $container, $dir, &$after, $limit, array $params );
}
