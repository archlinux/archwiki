<?php
declare( strict_types=1 );

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

namespace Wikimedia\JsonCodec;

use Stringable;

/**
 * Class hints with modifiers.
 * @template T
 */
class Hint implements Stringable {
	/**
	 * The default class hint behavior: an exact match for class name,
	 * and the serialization for an object will always use curly
	 * braces `{}` but the return value from `::toJsonArray()` will
	 * always be an array.  This requires adding an explicit
	 * `JsonCodec::TYPE_ANNOTATION` element to lists even if proper
	 * type hints are supplied.
	 */
	public const DEFAULT = 0;
	/**
	 * A list of the hinted type.
	 */
	public const LIST = 1;
	/**
	 * A map of the hinted type.  The value is a stdClass object with
	 * string keys and property values of the specified type.
	 */
	public const STDCLASS = 2;
	/**
	 * Prefer to use square brackets to serialize this object, when
	 * possible. Not compatible with `ALLOW_OBJECT`.
	 */
	public const USE_SQUARE = 3;
	/**
	 * Tweak the return type of `JsonCodec::toJsonArray()` to return
	 * a `stdClass` object instead of array where that makes it possible
	 * to generate curly braces instead of adding an extra
	 * `JsonCodec::TYPE_ANNOTATION` value.  Not compatible with `USE_SQUARE`.
	 */
	public const ALLOW_OBJECT = 4;
	/**
	 * The value is an `instanceof` the hinted type, and the
	 * `JsonClassCodec` for the hinted type will be able to
	 * deserialize the object.  This is useful for tagged objects of
	 * various kinds, where a superclass can look at the json data to
	 * determine which of its subclasses to instantiate.  Note that in
	 * this case hints will be taken from the superclass's codec.
	 */
	public const INHERITED = 5;

	/** @var class-string<T>|Hint<T> */
	public $parent;
	public int $modifier;

	/**
	 * Create a new serialization class type hint.
	 * @param class-string<T>|Hint<T> $parent
	 * @param int $modifier A hint modifier
	 */
	public function __construct( $parent, int $modifier = 0 ) {
		$this->parent = $parent;
		$this->modifier = $modifier;
	}

	/**
	 * Helper function to create nested hints.  For example, the
	 * `Foo[][]` type can be created as
	 * `Hint::build(Foo::class, Hint:LIST, Hint::LIST)`.
	 *
	 * Note that, in the grand (?) tradition of C-like types,
	 * modifiers are read right-to-left.  That is, a "stdClass containing
	 * values which are lists of Foo" is written 'backwards' as:
	 * `Hint::build(Foo::class, Hint::LIST, Hint::STDCLASS)`.
	 *
	 * @phan-template T
	 * @param class-string<T> $className
	 * @param int ...$modifiers
	 * @return class-string<T>|Hint<T>
	 */
	public static function build( string $className, int ...$modifiers ) {
		if ( count( $modifiers ) === 0 ) {
			return $className;
		}
		$last = array_pop( $modifiers );
		return new Hint( self::build( $className, ...$modifiers ), $last );
	}

	public function __toString(): string {
		$parent = strval( $this->parent );
		switch ( $this->modifier ) {
			case self::DEFAULT:
				return "DEFAULT($parent)";
			case self::LIST:
				return "LIST($parent)";
			case self::STDCLASS:
				return "STDCLASS($parent)";
			case self::USE_SQUARE:
				return "USE_SQUARE($parent)";
			case self::ALLOW_OBJECT:
				return "ALLOW_OBJECT($parent)";
			case self::INHERITED:
				return "INHERITED($parent)";
			default:
				return "UNKNOWN($parent)";
		}
	}
}
