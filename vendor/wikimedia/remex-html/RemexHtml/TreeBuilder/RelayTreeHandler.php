<?php

namespace RemexHtml\TreeBuilder;

use RemexHtml\Tokenizer\Attributes;

/**
 * A TreeHandler which simply passes all events through to another handler.
 *
 * Applications can subclass this in order to modify only a few event types
 * as they pass through.
 *
 * @since 2.1.0
 */
class RelayTreeHandler implements TreeHandler {
	/** @var TreeHandler */
	protected $nextHandler;

	/**
	 * Construct a RelayTreeHandler which will call $nextHandler on all events
	 *
	 * @param TreeHandler $nextHandler
	 */
	public function __construct( TreeHandler $nextHandler ) {
		$this->nextHandler = $nextHandler;
	}

	function startDocument( $fragmentNamespace, $fragmentName ) {
		$this->nextHandler->startDocument( $fragmentNamespace, $fragmentName );
	}

	function endDocument( $pos ) {
		$this->nextHandler->endDocument( $pos );
	}

	function characters( $preposition, $ref, $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->nextHandler->characters( $preposition, $ref, $text, $start, $length,
			$sourceStart, $sourceLength );
	}

	function insertElement( $preposition, $ref, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		$this->nextHandler->insertElement( $preposition, $ref, $element, $void,
		$sourceStart, $sourceLength );
	}

	function endTag( Element $element, $sourceStart, $sourceLength ) {
		$this->nextHandler->endTag( $element, $sourceStart, $sourceLength );
	}

	function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->nextHandler->doctype( $name, $public, $system, $quirks,
			$sourceStart, $sourceLength );
	}

	function comment( $preposition, $ref, $text, $sourceStart, $sourceLength ) {
		$this->nextHandler->comment( $preposition, $ref, $text, $sourceStart, $sourceLength );
	}

	function error( $text, $pos ) {
		$this->nextHandler->error( $text, $pos );
	}

	function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$this->nextHandler->mergeAttributes( $element, $attrs, $sourceStart );
	}

	function removeNode( Element $element, $sourceStart ) {
		$this->nextHandler->removeNode( $element, $sourceStart );
	}

	function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$this->nextHandler->reparentChildren( $element, $newParent, $sourceStart );
	}
}
