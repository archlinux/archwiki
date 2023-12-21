<?php

namespace Wikimedia\LangConv\Construct;

use Wikimedia\Assert\Assert;

/**
 * GENerate a REPLacement string FST.
 *
 * Create an FST from a replacement string array (aka, as would be provided
 * to `str_tr` or `ReplacementArray` in mediawiki core).
 */
class GenReplFst {
	// This can be anything, as long as it is longer than 1 character
	// (so it doesn't conflict w/ any of the single-character keys)
	private const END_OF_STRING = '*END*';
	// correlation of tree nodes to state machine states
	private const STATE = '*STATE*';
	// UTF-8 decode state: 0=first byte, 1/2/3=bytes remaining in char
	private const UTF8STATE = '*UTF8STATE*';
	// Character index of this tree node
	private const INDEX = '*INDEX*';

	/** @var array<int|string,int|string|array> */
	private $prefixTree = [];
	/** @var MutableFST */
	private $fst;
	/** @var array */
	private $alphabet;
	/**
	 * Prefix to use on flag diacritics (so they are unique to this FST).
	 * @var string
	 */
	private $fdPrefix;
	/**
	 * How many flag diacritic features are needed.  At most one less than
	 * the longest string, but can be shorter.
	 * @var int
	 */
	private $maxLookahead;
	/**
	 * Cache of shift machines.
	 * @var array
	 */
	private $shiftCache = [];

	/**
	 * Add each letter in the given word to our alphabet.
	 * @param array &$alphabet
	 * @param string $word
	 */
	private static function addAlphabet( array &$alphabet, string $word ): void {
		for ( $i = 0; $i < strlen( $word ); $i++ ) {
			$alphabet[ord( $word[$i] )] = true;
		}
	}

	/**
	 * Add the given string (the substring of $from starting from $index) to
	 * the prefix tree $tree, along with the conversion output $to.
	 * @param array<int|string,string|array> &$tree
	 * @param string $from
	 * @param int $index
	 * @param int $utf8state 0=first byte, 1/2/3 = bytes remaining in char
	 * @param string $to
	 * @param bool $suppressUtf8Checks Whether to suppress the checks that the
	 *   $from string is complete utf8.
	 */
	private static function addEntry(
		array &$tree, string $from, int $index, int $utf8state, string $to,
		bool $suppressUtf8Checks = false
	): void {
		$c = ord( $from[$index] );
		if ( !isset( $tree[$c] ) ) {
			$tree[$c] = [];
		}
		if ( !isset( $tree[self::UTF8STATE] ) ) {
			$tree[self::UTF8STATE] = $utf8state;
		}
		Assert::invariant(
			isset( $tree[self::UTF8STATE] ) && $tree[self::UTF8STATE] === $utf8state,
			"Should never happen"
		);
		if ( !isset( $tree[self::INDEX] ) ) {
			$tree[self::INDEX] = $index;
		}
		Assert::invariant(
			isset( $tree[self::INDEX] ) && $tree[self::INDEX] === $index,
			"Should never happen"
		);
		$nextUtf8State = self::nextUtf8State( $utf8state, $c );
		$nextIndex = $index + 1;
		if ( $nextIndex < strlen( $from ) ) {
			self::addEntry( $tree[$c], $from, $nextIndex, $nextUtf8State, $to );
		} else {
			if ( !$suppressUtf8Checks ) {
				Assert::invariant( $nextUtf8State === 0, "Bad UTF-8 in input" );
			}
			$tree[$c][self::UTF8STATE] = $nextUtf8State;
			$tree[$c][self::INDEX] = $nextIndex;
			$tree[$c][self::END_OF_STRING] = $to;
		}
	}

	/**
	 * Return the next UTF-8 state, given the current state and the
	 * current character.
	 * @param int $utf8state 0=first byte, 1/2/3 = bytes remaining in char
	 * @param int $c The current character
	 * @return int The next UTF-8 state
	 */
	private static function nextUtf8State( int $utf8state, int $c ): int {
		if ( $utf8state === 0 ) {
			if ( $c <= 0x7F ) {
				return 0;
			} elseif ( $c <= 0xDF ) {
				Assert::invariant( $c >= 0xC0, "Bad UTF-8 in input" );
				return 1;
			} elseif ( $c <= 0xEF ) {
				Assert::invariant( $c >= 0xE0, "Bad UTF-8 in input" );
				return 2;
			} elseif ( $c <= 0xF7 ) {
				Assert::invariant( $c >= 0xF0, "Bad UTF-8 in input" );
				return 3;
			}
		} else {
			return $utf8state - 1;
		}
		// @phan-suppress-next-line PhanImpossibleCondition
		Assert::invariant( false, "Bad UTF-8 in input" );
	}

	/**
	 * Return the subset of the given alphabet appropriate for the current
	 * UTF-8 state.
	 * @param int $utf8state 0=first byte, 1/2/3 = bytes remaining in char
	 * @return \Generator<int>
	 */
	private function utf8alphabet( int $utf8state ) {
		foreach ( $this->alphabet as $c ) {
			if ( $c >= 0x80 && $c <= 0xBF ) {
				// UTF-8 continuation character
				if ( $utf8state !== 0 ) {
					yield $c;
				}
			} else {
				if ( $utf8state === 0 ) {
					yield $c;
				}
			}
		}
	}

	/**
	 * Split a given string into the first utf8 char, and "everything else".
	 * @param string $s
	 * @return string[]
	 */
	private static function splitFirstUtf8Char( string $s ) {
		$utf8state = 0;
		$i = 0;
		while ( $i < strlen( $s ) ) {
			$c = ord( $s[$i] );
			$utf8state = self::nextUtf8State( $utf8state, $c );
			$i += 1;
			if ( $utf8state === 0 ) {
				break;
			}
		}
		return [ substr( $s, 0, $i ), substr( $s, $i ) ];
	}

	/**
	 * Return the name of a flag diacritic for matching character at a
	 * specified offset.
	 * @param string $type Flag diacritic operator: P/R/D/C/U
	 * @param int $offset Identifies the feature
	 * @param int|null $value The value to set/test (optional)
	 * @return string The symbol name for the given flag diacritic operation
	 */
	private function flagDiacritic(
		string $type, int $offset, ?int $value = null
	): string {
		$str = "@$type." . $this->fdPrefix . "char$offset";
		if ( $value !== null ) {
			$str .= ".";
			if ( $value >= 0 ) {
				$str .= self::byteToHex( $value );
			} else {
				$str .= "UNK";
			}
		}
		return $str . "@";
	}

	/**
	 * Add a flag diacritic edge between $from and $to.  By convention the
	 * flag diacritic is on both the upper and lower slots of the edge.
	 * @param State $from The source of the new edge
	 * @param State $to The destination of the new edge
	 * @param string $type The flag diacritic operator
	 * @param int $offset The flag diacritic feature
	 * @param int|null $value The flag diacritic value (optional)
	 */
	private function addFdEdge(
		State $from, State $to, string $type, int $offset, ?int $value = null
	): void {
		$fdName = $this->flagDiacritic( $type, $offset, $value );
		$from->addEdge( $fdName, $fdName, $to );
	}

	/**
	 * Add two flag diacritic edge between $from and $to.  By convention the
	 * flag diacritics are on both the upper and lower slots of the edge.
	 * @param State $from The source of the new edges
	 * @param State $to The destination of the new edges
	 * @param string $type1 The first flag diacritic operator
	 * @param int $offset1 The first flag diacritic feature
	 * @param int|null $value1 The first flag diacritic value (optional)
	 * @param string $type2 The second flag diacritic operator
	 * @param int $offset2 The second flag diacritic feature
	 * @param int|null $value2 The second flag diacritic value (optional)
	 */
	private function addFdEdgePair(
		State $from, State $to,
		string $type1, int $offset1, ?int $value1,
		string $type2, int $offset2, ?int $value2
	): void {
		$n = $this->fst->newState();
		$this->addFdEdge( $from, $n, $type1, $offset1, $value1 );
		$this->addFdEdge( $n, $to, $type2, $offset2, $value2 );
	}

	/**
	 * Add edges from state $from corresponding to the prefix tree $tree,
	 * given the $lastMatch and the characters seen since then, $seen.
	 * @param State $from
	 * @param array &$tree
	 * @param ?string $lastMatch (null if we haven't seen a match)
	 * @param string $seen characters seen since last match
	 */
	private function buildState( State $from, array &$tree, ?string $lastMatch, string $seen ): void {
		$tree[self::STATE] = $from;
		$index = $tree[self::INDEX];
		$utf8state = $tree[self::UTF8STATE];
		if ( $index < $this->maxLookahead ) {
			$noBufState = $this->fst->newState();
			$this->addFdEdge( $from, $noBufState, 'D', $index );
		} else {
			$noBufState = $from;
		}
		$noMatchState = $this->fst->newState();

		if ( isset( $tree[self::END_OF_STRING] ) ) {
			$lastMatch = $tree[self::END_OF_STRING];
			$seen = '';
		}
		foreach ( $this->utf8alphabet( $utf8state ) as $c ) {
			if ( isset( $tree[$c] ) ) {
				$nextSeen = $seen . chr( $c );
				$n = $this->fst->newState();
				$noBufState->addEdge( self::byteToHex( $c ), MutableFST::EPSILON, $n );
				if ( $index < $this->maxLookahead ) {
					$this->addFdEdge( $from, $n, 'R', $index, $c );
				}
				$nn = $this->fst->newState();
				$this->addFdEdge( $n, $nn, 'P', strlen( $seen ), $c );
				$this->buildState( $nn, $tree[$c], $lastMatch, $nextSeen );
			} else {
				Assert::invariant( $index !== 0,
								 "fake single-char matches should always exist" );

				$n = $this->fst->newState();
				$noBufState->addEdge( self::byteToHex( $c ), MutableFST::EPSILON, $n );
				if ( $index < $this->maxLookahead ) {
					$this->addFdEdge( $from, $n, 'R', $index, $c );
				}
				Assert::invariant(
					strlen( $seen ) < $this->maxLookahead,
					"Max lookahead should always account for seen"
				);
				$this->addFdEdge( $n, $noMatchState, 'P', strlen( $seen ), $c );
			}
		}
		// our first state must echo all continuation characters, since
		// the anythingState transitions there and we don't know what
		// utf8 state IDENTITY will leave us in. (The characters not in
		// our alphabet could consist of 1-/2-/3-/4-byte sequences.)
		if ( $index === 0 ) {
			foreach ( self::utf8alphabet( 1/*continuation chars*/ ) as $c ) {
				$nextSeen = $seen . chr( $c );
				$n = $this->fst->newState();
				$noBufState->addEdge( self::byteToHex( $c ), MutableFST::EPSILON, $n );
				if ( $index < $this->maxLookahead ) {
					$this->addFdEdge( $from, $n, 'R', $index, $c );
				}
				$fakeTree = [];
				$fakeTree[self::UTF8STATE] = 1;
				$fakeTree[self::INDEX] = $index + 1;
				$fakeTree[self::END_OF_STRING] = chr( $c );
				$this->buildState( $n, $fakeTree, $lastMatch, $nextSeen );
			}
		}
		// "anything else"
		$anythingElse = $this->fst->newState();
		$noBufState->addEdge( MutableFST::EPSILON, MutableFST::EPSILON, $anythingElse );
		if ( $index !== 0 ) {
			$this->addFdEdge( $from, $anythingElse, 'R', $index, -1 );
		}
		$this->addFdEdge( $anythingElse, $noMatchState, 'P', strlen( $seen ), -1 );
		// Emit the last match
		if ( $lastMatch !== null ) {
			$noMatchState = $this->emit( $noMatchState, MutableFST::EPSILON, $lastMatch );
		}
		// Shift over queued input
		for ( $i = $index + 1; true; $i++ ) {
			$j = strlen( $seen ) + ( $i - $index );
			$key = $i . ':' . $j;
			if ( isset( $this->shiftCache[$key] ) ) {
				$noMatchState->addEdge( MutableFST::EPSILON, MutableFST::EPSILON, $this->shiftCache[$key] );
				return;
			}
			$this->shiftCache[$key] = $noMatchState;
			if ( $i < $this->maxLookahead ) {
				$n = $this->fst->newState();
				$this->addFdEdge( $noMatchState, $n, 'D', $i );
			} else {
				$n = $noMatchState;
			}
			for ( $k = $j; $k < $i && $k < $this->maxLookahead; $k++ ) {
				$nn = $this->fst->newState();
				$this->addFdEdge( $n, $nn, 'C', $k );
				$n = $nn;
			}
			$n->addEdge( MutableFST::EPSILON, MutableFST::EPSILON, $this->fst->getStartState() );
			if ( !( $i < $this->maxLookahead ) ) {
				break;
			}
			$n = $this->fst->newState();
			$this->addFdEdgePair( $noMatchState, $n, 'R', $i, -1, 'P', $j, -1 );
			foreach ( $this->alphabet as $c ) {
				$this->addFdEdgePair( $noMatchState, $n, 'R', $i, $c, 'P', $j, $c );
			}
			$noMatchState = $n;
		}
	}

	/**
	 * Chain states together from $fromState to emit $emitStr.
	 * @param State $fromState
	 * @param string $fromChar
	 * @param string $emitStr
	 * @return State the resulting state (after the string has been emitted)
	 */
	private function emit( State $fromState, string $fromChar, string $emitStr ): State {
		if ( strlen( $emitStr ) === 0 && $fromChar !== MutableFST::EPSILON ) {
			$n = $this->fst->newState();
			$fromState->addEdge( $fromChar, MutableFST::EPSILON, $n );
			return $n;
		}
		for ( $i = 0; $i < strlen( $emitStr ); $i++ ) {
			$c = ord( $emitStr[$i] );
			$n = $this->fst->newState();
			$fromState->addEdge( $fromChar, self::byteToHex( $c ), $n );
			$fromState = $n;
			$fromChar = MutableFST::EPSILON;
		}
		return $fromState;
	}

	/**
	 * Private helper function: convert a numeric byte to the string
	 * token we use in the FST.
	 * @param int $byte
	 * @return string Token
	 */
	private static function byteToHex( int $byte ): string {
		$s = strtoupper( dechex( $byte ) );
		while ( strlen( $s ) < 2 ) {
			$s = "0$s";
		}
		return $s;
	}

	/**
	 * Private helper function: convert a UTF-8 string byte-by-byte into
	 * an array of tokens.
	 * @param string $s
	 * @return string[]
	 */
	private static function stringToTokens( string $s ): array {
		$toks = [];
		for ( $i = 0; $i < strlen( $s ); $i++ ) {
			$toks[] = self::byteToHex( ord( $s[$i] ) );
		}
		return $toks;
	}

	/**
	 * Private helper function: convert an array of tokens into
	 * a UTF-8 string.
	 * @param string[] $toks
	 * @return string
	 */
	private static function tokensToString( array $toks ): string {
		$s = '';
		foreach ( $toks as $token ) {
			if ( strlen( $token ) === 2 ) {
				$s .= chr( hexdec( $token ) );
			} else {
				// shouldn't happen, but handy for debugging if it does
				$s .= $token;
			}
		}
		return $s;
	}

	/**
	 * For testing: apply the resulting FST to the given input string.
	 * @param string $input
	 * @return string[] The possible outputs.
	 */
	public function applyDown( string $input ): array {
		// convert input to byte tokens
		$result = $this->fst->applyDown( self::stringToTokens( $input ) );
		return array_map( function ( $toks ) {
			return self::tokensToString( $toks );
		}, $result );
	}

	/**
	 * For testing: run the resulting FST "in reverse" against the given
	 * input string.
	 * @param string $input
	 * @return string[] The possible outputs.
	 */
	public function applyUp( string $input ): array {
		// convert input to byte tokens
		$result = $this->fst->applyUp( self::stringToTokens( $input ) );
		return array_map( function ( $toks ) {
			return self::tokensToString( $toks );
		}, $result );
	}

	/**
	 * Convert the given $replacementTable (strtr-style) to an FST.
	 * @param string $name
	 * @param array<string,string> $replacementTable
	 * @param string $fdPrefix Flag diacritic feature prefix, for uniqueness
	 */
	public function __construct(
		string $name, array $replacementTable, string $fdPrefix = ''
	) {
		$this->fdPrefix = $fdPrefix;
		$alphabet = [];
		$longestWord = 0;
		foreach ( $replacementTable as $from => $to ) {
			$longestWord = max( $longestWord, strlen( $from ) );
			self::addAlphabet( $alphabet, $from );
			self::addAlphabet( $alphabet, $to );
			self::addEntry( $this->prefixTree, $from, 0, 0, $to );
		}
		// fake one character matches!
		foreach ( $alphabet as $sym => $value ) {
			if ( $sym < 0x80 || $sym > 0xBF ) { // not continuation chars
				self::addEntry( $this->prefixTree, chr( $sym ), 0, 0, chr( $sym ), true );
			}
		}
		$this->maxLookahead = $longestWord - 1; // XXX could be shorter
		$this->alphabet = array_keys( $alphabet );
		sort( $this->alphabet, SORT_NUMERIC );
		// ok, now we're ready to emit the FST
		$this->fst = new MutableFST( array_map( function ( $n ) {
			return self::byteToHex( $n );
		}, $this->alphabet ) );
		$anythingState = $this->fst->newState();
		$anything2State = $this->fst->newState();
		$anythingState->addEdge(
			MutableFST::UNKNOWN, MutableFST::IDENTITY,
			$anything2State
		);
		$this->addFdEdge( $this->fst->getStartState(), $anythingState, 'R', 0, -1 );
		$this->addFdEdge( $anything2State, $this->fst->getStartState(), 'C', 0 );
		// The anything state could also be the end of the string
		// (which for modelling purposes we can think of as a special
		// "EOF" token not in the alphabet)
		// Important that there are no outgoing edges from the $endState!
		$endState = $this->fst->newState();
		$endState->isFinal = true;
		$anythingState->addEdge(
			MutableFST::EPSILON, MutableFST::EPSILON,
			$endState
		);

		// Create states corresponding to prefix tree nodes
		$this->buildState(
			$this->fst->getStartState(), $this->prefixTree, null, ''
		);
		// ok, done!
		$this->fst->optimize();
	}

	/**
	 * Write the FST to the given file handle in AT&T format.
	 * @param resource $handle
	 */
	public function writeATT( $handle ): void {
		$this->fst->writeATT( $handle );
	}

}
