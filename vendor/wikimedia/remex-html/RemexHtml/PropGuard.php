<?php

namespace RemexHtml;

/**
 * This is a statically configurable mechanism for preventing the setting of
 * undeclared properties on objects. The point of it is to detect programmer
 * errors.
 */
class PropGuard {
	public static $armed = true;

	public static function set( $obj, $name, $value ) {
		if ( self::$armed ) {
			throw new \Exception( "Property \"$name\" on object of class " . get_class( $obj ) .
				" is undeclared" );
		} else {
			$obj->$name = $value;
		}
	}
}
