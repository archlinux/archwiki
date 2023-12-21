<?php

namespace Wikimedia\LangConv;

/**
 * A simple tuple type for the FST backtracking state.
 */
class BacktrackState {
	/**
	 * State at which to resume execution if current execution fails.
	 * @var int
	 */
	public $epsState;
	/**
	 * Number of previous epsilon edges to skip upon resume.
	 * @var int
	 */
	public $epsSkip;
	/**
	 * Position in the output string.
	 * @var int
	 */
	public $outpos;
	/**
	 * Speculative result string.
	 * @var string
	 */
	public $partialResult = '';
	/**
	 * Speculative bracket list.
	 * @var int[]
	 */
	public $partialBrackets = [];
	/**
	 * Position in the input string.
	 * @var int
	 */
	public $idx;

	/**
	 * Create a new BacktrackState.
	 * @param int $epsState
	 * @param int $epsSkip
	 * @param int $outpos
	 * @param int $idx
	 */
	public function __construct( int $epsState, int $epsSkip, int $outpos, int $idx ) {
		$this->epsState = $epsState;
		$this->epsSkip = $epsSkip;
		$this->outpos = $outpos;
		$this->idx = $idx;
	}
}
