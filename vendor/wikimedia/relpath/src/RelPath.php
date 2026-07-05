<?php
declare( strict_types = 1 );

/**
 * Copyright (c) 2015 Ori Livneh <ori@wikimedia.org>
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @file
 * @author Ori Livneh <ori@wikimedia.org>
 */

namespace Wikimedia;

use function array_slice;
use function count;

/**
 * Utilities for computing a relative filepath between two paths.
 */
class RelPath {

	/**
	 * @var bool True if the operating system is Windows.
	 */
	private static $isWindows = \DIRECTORY_SEPARATOR === '\\';

	/**
	 * Split a path into path components.
	 *
	 * @param string $path File path.
	 * @return string[] Array of path components.
	 */
	private static function splitPath( string $path ): array {
		if ( self::$isWindows ) {
			$path = str_replace( '\\', '/', $path );
		}
		$parts = explode( '/', $path );
		$stack = [];

		foreach ( $parts as $part ) {
			if ( $part === '..' ) {
				if ( $stack ) {
					array_pop( $stack );
				}
			} elseif ( $part !== '' && $part !== '.' ) {
				$stack[] = $part;
			}
		}

		return $stack;
	}

	/**
	 * Determines if a path is absolute.
	 *
	 * @param string $path File path.
	 * @return bool True if the path is absolute, false otherwise.
	 */
	private static function isAbsolutePath( string $path ): bool {
		if ( str_starts_with( $path, '/' ) ) {
			return true;
		}

		if ( self::$isWindows ) {
			// Match drive letter + colon + slash (e.g. C:\ or C:/)
			return preg_match( '~^[a-zA-Z]:[\\\\/]~', $path ) === 1;
		}

		return false;
	}

	/**
	 * Return a relative filepath to $path, either from the current directory or from
	 * an optional start directory. Both paths must be absolute.
	 *
	 * @param string $path File path.
	 * @param string|null $start Start directory. Optional; if not specified, the current
	 *  working directory will be used.
	 * @return string|false Relative path, or false if input was invalid.
	 */
	public static function getRelativePath( string $path, ?string $start = null ): string|false {
		if ( $start === null ) {
			// @codeCoverageIgnoreStart
			$start = getcwd();
		}
		// @codeCoverageIgnoreEnd

		if ( !self::isAbsolutePath( $path ) || !self::isAbsolutePath( $start ) ) {
			return false;
		}

		// On Windows, paths must share the same drive or both be root-relative.
		// They cannot cross drives (C: vs D:) or mix anchoring (C:\ vs \).
		if ( self::$isWindows ) {
			$path = str_replace( '\\', '/', $path );
			$start = str_replace( '\\', '/', $start );
			if ( str_starts_with( $path, '/' ) ) {
				if ( !str_starts_with( $start, '/' ) ) {
					return false;
				}
			} elseif ( strncasecmp( $path, $start, 2 ) !== 0 ) {
				// Paths are on different drives.
				return false;
			}
		}

		$pathParts = self::splitPath( $path );
		$countPathParts = count( $pathParts );

		$startParts = self::splitPath( $start );
		$countStartParts = count( $startParts );

		$commonLength = min( $countPathParts, $countStartParts );
		for ( $i = 0; $i < $commonLength; $i++ ) {
			$p1 = $startParts[$i];
			$p2 = $pathParts[$i];
			$match = self::$isWindows
				? mb_strtolower( $p1 ) === mb_strtolower( $p2 )
				: $p1 === $p2;
			if ( !$match ) {
				break;
			}
		}

		$relList = ( $countStartParts > $i )
			? array_fill( 0, $countStartParts - $i, '..' )
			: [];

		$relList = [ ...$relList, ...array_slice( $pathParts, $i ) ];

		return implode( '/', $relList ) ?: '.';
	}

	/**
	 * Join two path components.
	 *
	 * This can be used to expand a path relative to a given base path.
	 * The given path may also be absolute, in which case it is returned
	 * directly.
	 *
	 * @code
	 *     RelPath::joinPath('/srv/foo', 'bar');        # '/srv/foo/bar'
	 *     RelPath::joinPath('/srv/foo', './bar');      # '/srv/foo/bar'
	 *     RelPath::joinPath('/srv//foo', '../baz');    # '/srv/baz'
	 *     RelPath::joinPath('/srv/foo', '/var/quux/'); # '/var/quux/'
	 * @endcode
	 *
	 * This function is similar to `os.path.join()` in Python,
	 * and `path.join()` in Node.js.
	 *
	 * @param string $base Base path.
	 * @param string $path File $path to join to $base path.
	 * @return string|false Combined path, or false if input was invalid.
	 */
	public static function joinPath( string $base, string $path ): string|false {
		if ( self::isAbsolutePath( $path ) ) {
			return $path;
		}

		if ( !self::isAbsolutePath( $base ) ) {
			// $base is relative.
			return false;
		}

		$pathStr = $base . '/' . $path;
		$stack = self::splitPath( $pathStr );

		// Since $base is absolute (checked above), the result must be absolute.
		$result = implode( '/', $stack );
		if ( self::$isWindows && isset( $stack[0] ) && preg_match( '/^[a-zA-Z]:/', $stack[0] ) ) {
			// On Windows, if the path starts with a drive letter, don't prepend a slash.
			// If it's just the drive letter (e.g., "C:"), ensure it ends with a slash.
			return $result === $stack[0] ? $result . '/' : $result;
		}
		return '/' . $result;
	}

}
