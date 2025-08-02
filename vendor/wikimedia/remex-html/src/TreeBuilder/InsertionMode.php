<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\PropGuard;
use Wikimedia\RemexHtml\Tokenizer\Attributes;

abstract class InsertionMode {
	use PropGuard;

	protected TreeBuilder $builder;
	protected Dispatcher $dispatcher;

	public function __construct( TreeBuilder $builder, Dispatcher $dispatcher ) {
		$this->builder = $builder;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * A valid DOCTYPE token was found.
	 *
	 * @param string $name The doctype name, usually "html"
	 * @param string $public The PUBLIC identifier
	 * @param string $system The SYSTEM identifier
	 * @param bool $quirks What the spec calls the "force-quirks flag"
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->builder->error( "unexpected doctype", $sourceStart );
	}

	/**
	 * @param string $text The text of the comment
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 */
	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->builder->comment( null, $text, $sourceStart, $sourceLength );
	}

	/**
	 * A parse error
	 *
	 * @param string $text An error message explaining in English what the
	 *   author did wrong, and what the parser intends to do about the
	 *   situation.
	 * @param int $pos The input position at which the error occurred
	 */
	public function error( $text, $pos ) {
		$this->builder->error( $text, $pos );
	}

	/**
	 * @param bool $isStartOfToken
	 * @param string $mask Match mask
	 * @param string $text The text to insert is a substring of this string,
	 *   with the start and length of the substring given by $start and
	 *   $length. We do it this way to avoid unnecessary copying.
	 * @param int $start The start of the substring
	 * @param int $length The length of the substring
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 * @return array
	 */
	protected function splitInitialMatch( $isStartOfToken, $mask, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$matchLength = strspn( $text, $mask, $start, $length );
		if ( $isStartOfToken && $matchLength ) {
			// Do some extra work to figure out a plausible start position if
			// the text node started with <![CDATA[
			// FIXME: make this optional?
			$sourceText = $this->builder->tokenizer->getPreprocessedText();
			$isCdata = substr_compare( $sourceText, '<![CDATA[', $sourceStart, $sourceLength ) === 0;
			$cdataLength = $isCdata ? strlen( '<![CDATA[' ) : 0;
		} else {
			$cdataLength = 0;
		}

		return [
			[
				$start,
				$matchLength,
				$sourceStart,
				$matchLength + $cdataLength,
			], [
				$start + $matchLength,
				$length - $matchLength,
				$sourceStart + $matchLength + $cdataLength,
				$sourceLength - $matchLength - $cdataLength
			]
		];
	}

	/**
	 * @param bool $inBody
	 * @param string $text The text to insert is a substring of this string,
	 *   with the start and length of the substring given by $start and
	 *   $length. We do it this way to avoid unnecessary copying.
	 * @param int $start The start of the substring
	 * @param int $length The length of the substring
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 */
	protected function handleFramesetWhitespace( $inBody, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$isStartOfToken = true;
		$builder = $this->builder;

		do {
			[ $part1, $part2 ] = $this->splitInitialMatch(
				$isStartOfToken, "\t\n\f\r ", $text, $start, $length, $sourceStart, $sourceLength );
			$isStartOfToken = false;

			[ $start, $length, $sourceStart, $sourceLength ] = $part1;
			if ( $length ) {
				if ( $inBody ) {
					$this->dispatcher->inBody->characters( $text, $start, $length,
						$sourceStart, $sourceLength );
				} else {
					$builder->insertCharacters( $text, $start, $length, $sourceStart, $sourceLength );
				}
			}

			[ $start, $length, $sourceStart, $sourceLength ] = $part2;
			if ( $length ) {
				$builder->error( "unexpected non-whitespace character", $sourceStart );
				$start++;
				$length--;
				$sourceStart++;
				$sourceLength--;
			}
		} while ( $length > 0 );
	}

	/**
	 * @param callable $callback
	 * @param string $text The text to insert is a substring of this string,
	 *   with the start and length of the substring given by $start and
	 *   $length. We do it this way to avoid unnecessary copying.
	 * @param int $start The start of the substring
	 * @param int $length The length of the substring
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 */
	protected function stripNulls( $callback, $text, $start, $length, $sourceStart, $sourceLength ) {
		$errorOffset = $sourceStart - $start;
		while ( $length > 0 ) {
			$validLength = strcspn( $text, "\0", $start, $length );
			if ( $validLength ) {
				$callback( $text, $start, $validLength, $sourceStart, $sourceLength );
				$start += $validLength;
				$length -= $validLength;
			}
			if ( $length <= 0 ) {
				break;
			}
			$this->error( 'unexpected null character', $start + $errorOffset );
			$start++;
			$length--;
		}
	}

	/**
	 * Insert characters.
	 *
	 * @param string $text The text to insert is a substring of this string,
	 *   with the start and length of the substring given by $start and
	 *   $length. We do it this way to avoid unnecessary copying.
	 * @param int $start The start of the substring
	 * @param int $length The length of the substring
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The length of the input which is consumed
	 */
	abstract public function characters( $text, $start, $length, $sourceStart, $sourceLength );

	/**
	 * @param string $name The tag name being ended
	 * @param Attributes $attrs
	 * @param bool $selfClose True if this is a self closing tag
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The input position
	 */
	abstract public function startTag( $name, Attributes $attrs, $selfClose,
		$sourceStart, $sourceLength );

	/**
	 * @param string $name The tag name being ended
	 * @param int $sourceStart The input position
	 * @param int $sourceLength The input position
	 */
	abstract public function endTag( $name, $sourceStart, $sourceLength );

	/**
	 * Called when parsing stops.
	 *
	 * @param int $pos The input string length, i.e. the past-the-end position.
	 */
	abstract public function endDocument( $pos );
}
