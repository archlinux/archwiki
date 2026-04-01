<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Exception;

use UnexpectedValueException;

class ParseException extends UnexpectedValueException {

	/** @var array Parse errors. Each error is [ string $tag, int $line, int $pos ] */
	public $parseErrors = [];

}
