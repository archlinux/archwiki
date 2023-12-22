<?php

namespace Wikimedia\LangConv\Construct;

/**
 * An edge between states in a mutable FST.
 */
class Edge {
	/** @var State */
	public $from;
	/** @var int */
	public $id;
	/** @var string */
	public $upper;
	/** @var string */
	public $lower;
	/** @var State */
	public $to;

	/**
	 * Create a new Edge.
	 * @param State $from State this edge is coming from (and stored in)
	 * @param int $id Index of this edge in from State's edges array.
	 * @param string $upper Token on the upper side of this edge
	 * @param string $lower Token on the lower side of this edge
	 * @param State $to Destination state
	 */
	public function __construct( State $from, int $id, string $upper, string $lower, State $to ) {
		$this->from = $from;
		$this->id = $id;
		$this->upper = $upper;
		$this->lower = $lower;
		$this->to = $to;
	}
}
