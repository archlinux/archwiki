<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Objects;

/**
 * Represent a CSS component value, eg. `html`, `div`, `10px`, `20px` are all
 * component values in the rule `html div { margin: 10px 20px; }`.
 */
abstract class ComponentValue implements CSSObject {

	/** @var int Line in the input where this component value starts */
	protected $line = -1;

	/** @var int Position in the input where this component value starts */
	protected $pos = -1;

	/**
	 * Get the position of this ComponentValue in the input stream
	 * @return array [ $line, $pos ]
	 */
	public function getPosition() {
		return [ $this->line, $this->pos ];
	}

	/** @inheritDoc */
	public function toComponentValueArray() {
		return [ $this ];
	}
}
