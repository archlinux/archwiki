<?php

namespace MediaWiki\Extension\Notifications\Iterator;

use Iterator;
use IteratorDecorator;

/**
 * Applies a callback to all values returned from the iterator
 */
class CallbackIterator extends IteratorDecorator {
	/** @var callable */
	protected $callable;

	/**
	 * @param Iterator $iterator
	 * @param callable $callable
	 */
	public function __construct( Iterator $iterator, $callable ) {
		parent::__construct( $iterator );
		$this->callable = $callable;
	}

	/** @return mixed */
	public function current() {
		return ( $this->callable )( $this->iterator->current() );
	}
}

class_alias( CallbackIterator::class, 'EchoCallbackIterator' );
