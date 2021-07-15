<?php

namespace Wikimedia\RequestTimeout;

/**
 * An exception thrown when CriticalSectionProvider::exit() is called with a
 * name not matching the one used in the prior CriticalSectionProvider::enter()
 * call.
 */
class CriticalSectionMismatchException extends \LogicException {
	/**
	 * @param string $actual The name used in exit()
	 * @param string $expected The name used in enter(), or "[none]" if there
	 *   was no enter() call.
	 */
	public function __construct( $actual, $expected ) {
		parent::__construct( "Critical section name mismatch: expected $expected, got $actual" );
	}
}
