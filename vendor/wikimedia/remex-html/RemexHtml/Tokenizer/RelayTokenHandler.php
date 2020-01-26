<?php

namespace RemexHtml\Tokenizer;

/**
 * A TokenHandler which simply passes all tokens through to another token
 * handler, specified in the constructor.
 *
 * Applications can subclass this in order to modify only a few token types
 * as they pass through.
 *
 * @since 2.1.0
 */
class RelayTokenHandler implements TokenHandler {
	/** @var TokenHandler */
	protected $nextHandler;

	/**
	 * Construct a RelayTokenHandler which will call $nextHandler on all events
	 *
	 * @param TokenHandler $nextHandler
	 */
	public function __construct( TokenHandler $nextHandler ) {
		$this->nextHandler = $nextHandler;
	}

	public function startDocument( Tokenizer $tokenizer, $fragmentNamespace, $fragmentName ) {
		$this->nextHandler->startDocument( $tokenizer, $fragmentNamespace, $fragmentName );
	}

	public function endDocument( $pos ) {
		$this->nextHandler->endDocument( $pos );
	}

	public function error( $text, $pos ) {
		$this->nextHandler->error( $text, $pos );
	}

	public function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->nextHandler->characters( $text, $start, $length, $sourceStart, $sourceLength );
	}

	public function startTag( $name, Attributes $attrs, $selfClose, $sourceStart, $sourceLength ) {
		$this->nextHandler->startTag( $name, $attrs, $selfClose, $sourceLength, $sourceLength );
	}

	public function endTag( $name, $sourceStart, $sourceLength ) {
		$this->nextHandler->endTag( $name, $sourceStart, $sourceLength );
	}

	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->nextHandler->doctype( $name, $public, $system, $quirks, $sourceStart,
			$sourceLength );
	}

	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->nextHandler->comment( $text, $sourceStart, $sourceLength );
	}
}
