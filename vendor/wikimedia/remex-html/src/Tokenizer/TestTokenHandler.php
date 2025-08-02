<?php

namespace Wikimedia\RemexHtml\Tokenizer;

/**
 * A TokenHandler which collects events from the Tokenizer and generates an
 * array compatible with the html5lib tokenizer tests.
 */
class TestTokenHandler implements TokenHandler {
	/** @var array<string|array> */
	private $tokens = [];

	/** @return array<string|array> */
	public function getTokens() {
		return $this->tokens;
	}

	/** @inheritDoc */
	public function startDocument( Tokenizer $tokenizer, $fns, $fn ) {
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
		$this->tokens[] = 'ParseError';
	}

	/** @inheritDoc */
	public function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->tokens[] = [ 'Character', substr( $text, $start, $length ) ];
	}

	/** @inheritDoc */
	public function startTag( $name, Attributes $attrs, $selfClose, $sourceStart, $sourceLength ) {
		$attrArray = $attrs->getValues();
		if ( $selfClose ) {
			$this->tokens[] = [ 'StartTag', $name, $attrArray, $selfClose ];
		} else {
			$this->tokens[] = [ 'StartTag', $name, $attrArray ];
		}
	}

	/** @inheritDoc */
	public function endTag( $name, $sourceStart, $sourceLength ) {
		$this->tokens[] = [ 'EndTag', $name ];
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->tokens[] = [ 'DOCTYPE', $name, $public, $system, !$quirks ];
	}

	/** @inheritDoc */
	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->tokens[] = [ 'Comment', $text ];
	}
}
