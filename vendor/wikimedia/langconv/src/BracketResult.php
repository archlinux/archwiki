<?php

namespace Wikimedia\LangConv;

/**
 * A simple tuple type for the results of ReplacementMachine::countBrackets().
 */
class BracketResult {
	/**
	 * The number of codepoints we wouldn't have to escape.
	 * @var int
	 */
	public $safe;
	/**
	 * The number of codepoints we'd have to specially escape.
	 * @var int
	 */
	public $unsafe;
	/**
	 * The total number of codepoints (sum of `safe` and `unsafe`).
	 * @var int
	 */
	public $length;

	/**
	 * Create a new BracketResult.
	 * @param int $safe
	 * @param int $unsafe
	 * @param int $length
	 */
	public function __construct( int $safe, int $unsafe, int $length ) {
		$this->safe = $safe;
		$this->unsafe = $unsafe;
		$this->length = $length;
	}
}
