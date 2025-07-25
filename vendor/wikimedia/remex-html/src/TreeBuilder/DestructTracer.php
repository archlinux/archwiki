<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\Tokenizer\Attributes;

/**
 * A debugging helper which calls a callback function with a descriptive message
 * each time an Element node is destroyed.
 */
class DestructTracer implements TreeHandler {
	/** @var callable */
	private $callback;

	/**
	 * @param callable $callback
	 */
	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	/** @inheritDoc */
	public function startDocument( $fragmentNamespace, $fragmentName ) {
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
	}

	/** @inheritDoc */
	public function characters( $preposition, $ref, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
	}

	/** @inheritDoc */
	public function insertElement( $preposition, $ref, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		$element->userData = new DestructTracerNode( $this->callback, $element->getDebugTag() );
	}

	/** @inheritDoc */
	public function endTag( Element $element, $sourceStart, $sourceLength ) {
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
	}

	/** @inheritDoc */
	public function comment( $preposition, $ref, $text, $sourceStart, $sourceLength ) {
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
	}

	/** @inheritDoc */
	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
	}

	/** @inheritDoc */
	public function removeNode( Element $element, $sourceStart ) {
	}

	/** @inheritDoc */
	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$newParent->userData = new DestructTracerNode( $this->callback, $newParent->getDebugTag() );
	}
}
