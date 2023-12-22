<?php

namespace Wikimedia\LangConv\Construct;

/**
 * A state in a mutable FST.
 */
class State {
	/** @var int */
	public $id;
	/** @var array<?Edge> */
	public $edges = [];
	/** @var bool */
	public $isFinal = false;

	/**
	 * Create a new state.
	 * @param int $id The index of this state in the parent MutableFST.
	 */
	public function __construct( int $id ) {
		$this->id = $id;
	}

	/**
	 * Add an edge from this state to another.
	 * @param string $upper The token on the upper side of the edge
	 * @param string $lower The token on the lower side of the edge
	 * @param State $to The destination of the edge
	 */
	public function addEdge( string $upper, string $lower, State $to ): void {
		$this->edges[] = new Edge( $this, count( $this->edges ), $upper, $lower, $to );
	}

	/**
	 * Write the edges of this state to the given $handle as an AT&T format
	 * file.
	 * @param resource $handle
	 */
	public function writeATT( $handle ): void {
		foreach ( $this->edges as $e ) {
			$line = [
				strval( $e->from->id ),
				strval( $e->to->id ),
				$e->upper, $e->lower
			];
			fwrite( $handle, implode( "\t", $line ) . "\n" );
		}
	}
}
