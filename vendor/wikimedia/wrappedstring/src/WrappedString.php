<?php
declare( strict_types = 1 );

/**
 * Copyright 2015 Timo Tijhof
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
 */

namespace Wikimedia;

use DomainException;

class WrappedString {
	protected string $value;
	protected readonly ?string $prefix;
	protected readonly ?string $suffix;

	public function __construct( string $value, ?string $prefix = null, ?string $suffix = null ) {
		if ( $prefix !== null && !str_starts_with( $value, $prefix ) ) {
			throw new DomainException( 'Prefix must match value' );
		}
		if ( $suffix !== null && !str_ends_with( $value, $suffix ) ) {
			throw new DomainException( 'Suffix must match value' );
		}

		$this->value = $value;
		$this->prefix = $prefix;
		$this->suffix = $suffix;
	}

	/**
	 * @param string $value Value of a WrappedString with the same prefix and suffix
	 * @return WrappedString Newly wrapped string
	 */
	protected function extend( string $value ): self {
		$wrap = clone $this;
		$suffixLen = strlen( $this->suffix ?? '' );
		// Remove the suffix (temporarily), to open the string for merging.
		if ( $suffixLen ) {
			$wrap->value = substr( $this->value, 0, -$suffixLen );
		}
		// Append the next value without a prefix, thus ending with the suffix again.
		$prefixLen = strlen( $this->prefix ?? '' );
		$wrap->value .= substr( $value, $prefixLen );
		return $wrap;
	}

	/**
	 * Merge consecutive WrappedString objects with the same prefix and suffix.
	 *
	 * Does not modify the array or the WrappedString objects.
	 *
	 * NOTE: This is an internal method. Use join() or WrappedStringList instead.
	 *
	 * @param array<string|WrappedString|WrappedStringList> $wraps
	 * @return array<string|WrappedString|WrappedStringList> Compacted list to be treated as strings
	 */
	public static function compact( array $wraps ): array {
		$consolidated = [];
		if ( $wraps === [] ) {
			// Return early so that we don't have to deal with $prev being
			// set or not set, and avoid the risk of adding $prev's initial null
			// value to the list as extra value (T196496).
			return $consolidated;
		}
		$first = true;
		$prev = null;
		foreach ( $wraps as $wrap ) {
			if ( $first ) {
				$first = false;
				$prev = $wrap;
				continue;
			}
			if ( $prev instanceof WrappedString
				&& $wrap instanceof WrappedString
				&& $prev->prefix !== null
				&& $prev->prefix === $wrap->prefix
				&& $prev->suffix !== null
				&& $prev->suffix === $wrap->suffix
			) {
				$prev = $prev->extend( $wrap->value );
			} else {
				$consolidated[] = $prev;
				$prev = $wrap;
			}
		}
		// Add last one
		$consolidated[] = $prev;

		return $consolidated;
	}

	/**
	 * Join several wrapped strings with a separator between each.
	 *
	 * This method is compatible with native PHP implode(). The actual join
	 * operation is deferred to WrappedStringList::__toString(). This allows
	 * callers to collect multiple lists and compact them together.
	 *
	 * @param string $sep
	 * @param (string|WrappedString|WrappedStringList)[] $wraps
	 */
	public static function join( string $sep, array $wraps ): WrappedStringList {
		return new WrappedStringList( $sep, $wraps );
	}

	public function __toString(): string {
		return $this->value;
	}
}
