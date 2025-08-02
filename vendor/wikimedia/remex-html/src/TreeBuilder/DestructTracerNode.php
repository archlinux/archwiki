<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

class DestructTracerNode {
	/** @var callable */
	private $callback;
	/** @var string */
	private $tag;

	/**
	 * @param callable $callback
	 * @param string $tag
	 */
	public function __construct( $callback, $tag ) {
		$this->callback = $callback;
		$this->tag = $tag;
	}

	public function __destruct() {
		( $this->callback )( "[Destruct] {$this->tag}" );
	}
}
