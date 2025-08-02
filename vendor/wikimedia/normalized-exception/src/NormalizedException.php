<?php

namespace Wikimedia\NormalizedException;

use Exception;

/**
 * Basic normalized exception.
 *
 * @newable
 * @stable to extend
 */
class NormalizedException extends Exception implements INormalizedException {

	use NormalizedExceptionTrait {
		NormalizedExceptionTrait::normalizedConstructor as __construct;
	}

}
