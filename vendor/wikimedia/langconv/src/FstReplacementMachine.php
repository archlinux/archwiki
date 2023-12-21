<?php

namespace Wikimedia\LangConv;

use DOMDocument;
use DOMDocumentFragment;
use Wikimedia\Assert\Assert;

class FstReplacementMachine extends ReplacementMachine {

	private $baseLanguage;
	private $codes = [];
	private $machines = [];

	/**
	 * ReplacementMachine constructor.
	 * @param string $baseLanguage
	 * @param string[] $codes
	 */
	public function __construct( $baseLanguage, $codes ) {
		parent::__construct();
		$this->baseLanguage = $baseLanguage;
		foreach ( $codes as $code ) {
			// Set key *and* value of `codes` to allow use as set
			$this->codes[ $code ] = $code;
			$bracketMachines = [];
			foreach ( $codes as $code2 ) {
				if ( !$this->isValidCodePair( $code, $code2 ) ) {
					continue;
				}
				$dstCode = $code === $code2 ? 'noop' : $code2;
				$bracketMachines[$code2] = $this->loadFST( "brack-$code-$dstCode", true );
			}
			$this->machines[$code] = [
				'convert' => $this->loadFST( "trans-$code" ),
				'bracket' => $bracketMachines,
			];
		}
	}

	/**
	 * Return the set of language codes supported.  Both key and value are
	 * set in order to facilitate inclusion testing.
	 *
	 * @return array<string,string>
	 */
	public function getCodes() {
		return $this->codes;
	}

	/**
	 * Load a conversion machine from a pFST file with filename $filename from the fst directory.
	 * @param string $filename filename, omitting the .pfst file extension
	 * @param bool $justBrackets whether to return only the bracket locations
	 * @return FST
	 */
	public function loadFST( string $filename, bool $justBrackets = false ): FST {
		return FST::compile( __dir__ . "/../fst/$filename.pfst", $justBrackets );
	}

	/**
	 * Quantify a guess about the "native" language of string `s`.
	 * We will be converting *to* `destCode`, and our guess is that when we round trip we'll want
	 * to convert back to `invertCode` (so `invertCode` is our guess about the actual language of
	 * `s`).
	 * If we were to make this encoding, the returned value `unsafe` is the number of codepoints
	 * we'd have to specially-escape, `safe` is the number of codepoints we wouldn't have to
	 * escape, and `len` is the total number of codepoints in `s`.  Generally lower values of
	 * `nonsafe` indicate a better guess for `invertCode`.
	 * @param string $s
	 * @param string $destCode
	 * @param string $invertCode
	 * @return BracketResult Statistics about the given guess.
	 */
	public function countBrackets( string $s, $destCode, $invertCode ) {
		Assert::precondition( $this->isValidCodePair( $destCode, $invertCode ),
			"Invalid code pair: $destCode/$invertCode" );
		$m = $this->machines[$destCode]['bracket'][$invertCode];
		// call array_values on the result of unpack() to transform from a 1- to 0-indexed array
		$brackets = $m->run( $s, 0, strlen( $s ), true );
		$safe = 0;
		$unsafe = 0;
		for ( $i = 1; $i < count( $brackets ); $i++ ) {
			$safe += ( $brackets[$i] - $brackets[$i - 1] );
			if ( ++$i < count( $brackets ) ) {
				$unsafe += ( $brackets[$i] - $brackets[$i - 1] );
			}
		}
		// Note that this is counting codepoints, not UTF-8 code units.
		return new BracketResult(
			$safe, $unsafe, $brackets[count( $brackets ) - 1]
		);
	}

	/**
	 * Convert a string of text.
	 * @param DOMDocument $document
	 * @param string $s text to convert
	 * @param string $destCode destination language code
	 * @param string $invertCode
	 * @return DOMDocumentFragment DocumentFragment containing converted text
	 */
	public function convert( $document, $s, $destCode, $invertCode ) {
		$machine = $this->machines[$destCode];
		$convertM = $machine['convert'];
		$bracketM = $machine['bracket'][$invertCode];
		$result = $document->createDocumentFragment();

		$brackets = $bracketM->run( $s );

		for ( $i = 1, $len = count( $brackets ); $i < $len; $i++ ) {
			// A safe string
			$safe = $convertM->run( $s, $brackets[$i - 1], $brackets[$i] );
			if ( strlen( $safe ) > 0 ) {
				$result->appendChild( $document->createTextNode( $safe ) );
			}
			if ( ++$i < count( $brackets ) ) {
				// An unsafe string
				$orig = substr( $s, $brackets[$i - 1], $brackets[$i] - $brackets[$i - 1] );
				$unsafe = $convertM->run( $s, $brackets[$i - 1], $brackets[$i] );
				$span = $document->createElement( 'span' );
				$span->textContent = $unsafe;
				$span->setAttribute( 'typeof', 'mw:LanguageVariant' );
				// If this is an anomalous piece of text in a paragraph otherwise written in
				// destCode, then it's possible invertCode === destCode. In this case try to pick a
				// more appropriate invertCode !== destCode.
				$ic = $invertCode;
				if ( $ic === $destCode ) {
					$cs = array_values( array_filter( $this->codes, static function ( $code ) use ( $destCode ) {
						return $code !== $destCode;
					} ) );
					$cs = array_map( function ( $code ) use ( $orig ) {
						return [
							'code' => $code,
							'stats' => $this->countBrackets( $orig, $code, $code ),
						];
					}, $cs );
					uasort( $cs, static function ( $a, $b ) {
						return $a['stats']->unsafe - $b['stats']->unsafe;
					} );
					if ( count( $cs ) === 0 ) {
						$ic = '-';
					} else {
						$ic = $cs[0]['code'];
						$span->setAttribute( 'data-mw-variant-lang', $ic );
					}
				}
				$span->setAttribute( 'data-mw-variant', $this->jsonEncode( [
					'twoway' => [
						[ 'l' => $ic, 't' => $orig ],
						[ 'l' => $destCode, 't' => $unsafe ],
					],
					'rt' => true /* Synthetic markup used for round-tripping */
				] ) );
				if ( strlen( $unsafe ) > 0 ) {
					$result->appendChild( $span );
				}
			}
		}
		return $result;
	}
}
