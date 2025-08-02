<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\Tokenizer\Attributes;

/**
 * The "after after frameset" insertion mode.
 */
class AfterAfterFrameset extends InsertionMode {
	/** @inheritDoc */
	public function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->handleFramesetWhitespace( true, $text, $start, $length, $sourceStart, $sourceLength );
	}

	/** @inheritDoc */
	public function startTag( $name, Attributes $attrs, $selfClose, $sourceStart, $sourceLength ) {
		$builder = $this->builder;
		$dispatcher = $this->dispatcher;

		switch ( $name ) {
			case 'html':
				$dispatcher->inBody->startTag( $name, $attrs, $selfClose,
					$sourceStart, $sourceLength );
				break;

			case 'noframes':
				$dispatcher->inHead->startTag( $name, $attrs, $selfClose,
					$sourceStart, $sourceLength );
				break;

			default:
				$builder->error( "unexpected start tag after after frameset", $sourceStart );
		}
	}

	/** @inheritDoc */
	public function endTag( $name, $sourceStart, $sourceLength ) {
		$this->builder->error( "unexpected end tag after after frameset", $sourceStart );
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
		$this->builder->stopParsing( $pos );
	}

	/** @inheritDoc */
	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->builder->comment( [ TreeBuilder::ROOT, null ], $text, $sourceStart, $sourceLength );
	}
}
