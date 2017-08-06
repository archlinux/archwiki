<?php

/**
 * TextCat language classifier
 * See http://odur.let.rug.nl/~vannoord/TextCat/
 */
class TextCat {

	const STATUSTOOSHORT = 'Input is too short.';
	const STATUSNOMATCH = 'No match found.';
	const STATUSAMBIGUOUS = 'Cannot determine language.';

	/**
	 * Minimum input length to be considered for
	 * classification
	 * @var string
	 */
	private $resultStatus = '';

	/**
	 * Number of ngrams to be used.
	 * @var int
	 */
	private $maxNgrams = 3000;

	/**
	 * Minimum frequency of ngram to be counted.
	 * ( For language model generation set this
	 *   >0 to decrease CPU and memory reqs. )
	 * @var int
	 */
	private $minFreq = 0;

	/**
	 * Regexp used as word separator
	 * @var string
	 */
	private $wordSeparator = '0-9\s\(\)';

	/**
	 * List of language files
	 * @var string[]
	 */
	private $langFiles = array();

	/**
	 * Minimum input length to be considered for
	 * classification
	 * @var int
	 */
	private $minInputLength = 0;

	/**
	 * Maximum ratio of the score between a given
	 * candidate and the best candidate for the
	 * given candidate to be considered an alternative.
	 * @var float
	 */
	private $resultsRatio = 1.05;

	/**
	 * Maximum number of languages to return, within
	 * the resultsRatio. If there are more, the result
	 * is too ambiguous.
	 * @var int
	 */
	private $maxReturnedLanguages = 10;

	/**
	 * Maximum proportion of maximum score allowed.
	 * Compare score to worst possible score, and if
	 * it is too close, consider it not worth reporting.
	 * @var float
	 */
	private $maxProportion = 1.00;

	/**
	 * Amount to boost scores for languages
	 * specified by $boostedLangs; typical
	 * values are 0 to 0.15;
	 * @var float
	 */
	private $langBoostScore = 0.00;

	/**
	 * List of languages to boost by $langBoostScore
	 * @var string[]
	 */
	private $boostedLangs = array();

	/**
	 * @param
	 */
	public function getResultStatus() {
		return $this->resultStatus;
	}

	/**
	 * @param int $maxNgrams
	 */
	public function setMaxNgrams( $maxNgrams ) {
		$this->maxNgrams = $maxNgrams;
	}

	/**
	 * @param int $minFreq
	 */
	public function setMinFreq( $minFreq ) {
		$this->minFreq = $minFreq;
	}

	/**
	 * @param int $minInputLength
	 */
	public function setMinInputLength( $minInputLength ) {
		$this->minInputLength = $minInputLength;
	}

	/**
	 * @param float $resultsRatio
	 */
	public function setResultsRatio( $resultsRatio ) {
		$this->resultsRatio = $resultsRatio;
	}

	/**
	 * @param int $maxReturnedLanguages
	 */
	public function setMaxReturnedLanguages( $maxReturnedLanguages ) {
		$this->maxReturnedLanguages = $maxReturnedLanguages;
	}

	/**
	 * @param float $maxProportion
	 */
	public function setMaxProportion( $maxProportion ) {
		$this->maxProportion = $maxProportion;
	}

	/**
	 * @param float $langBoostScore
	 */
	public function setLangBoostScore( $langBoostScore ) {
		$this->langBoostScore = $langBoostScore;
	}

	/**
	 * @param float $langBoostScore
	 */
	public function setBoostedLangs( $boostedLangs = array() ) {
		// flip for more efficient lookups
		$this->boostedLangs = array_flip( $boostedLangs );
	}

	/**
	 * @param string $wordSeparator
	 */
	public function setWordSeparator( $wordSeparator ) {
		$this->wordSeparator = $wordSeparator;
	}

	/**
	 * @param string|array $dirs
	 */
	public function __construct( $dirs = array() ) {
		if ( empty( $dirs ) ) {
			$dirs = array( __DIR__."/LM" );
		}
		if ( !is_array( $dirs ) ) {
			$dirs = array( $dirs );
		}
		foreach ( $dirs as $dir ) {
			foreach ( new DirectoryIterator( $dir ) as $file ) {
				if ( !$file->isFile() ) {
					continue;
				}
				if ( $file->getExtension() == "lm" &&
				     !isset( $this->langFiles[$file->getBasename( ".lm" )] ) ) {
					$this->langFiles[$file->getBasename( ".lm" )] = $file->getPathname();
				}
			}
		}
	}

	/**
	 * Create ngrams list for text.
	 * @param string $text
	 * @param int $maxNgrams How many ngrams to use.
	 * @return int[]
	 */
	public function createLM( $text, $maxNgrams ) {
		$ngram = array();
		foreach ( preg_split( "/[{$this->wordSeparator}]+/u", $text ) as $word ) {
			if ( empty( $word ) ) {
				continue;
			}
			$word = "_".$word."_";
			$len = mb_strlen( $word, "UTF-8" );
			for ( $i=0;$i<$len;$i++ ) {
				$rlen = $len - $i;
				if ( $rlen > 4 ) {
					@$ngram[mb_substr( $word, $i, 5, "UTF-8" )]++;
				}
				if ( $rlen > 3 ) {
					@$ngram[mb_substr( $word, $i, 4, "UTF-8" )]++;
				}
				if ( $rlen > 2 ) {
					@$ngram[mb_substr( $word, $i, 3, "UTF-8" )]++;
				}
				if ( $rlen > 1 ) {
					@$ngram[mb_substr( $word, $i, 2, "UTF-8" )]++;
				}
				@$ngram[mb_substr( $word, $i, 1, "UTF-8" )]++;
			}
		}
		if ( $this->minFreq ) {
			$min = $this->minFreq;
			$ngram = array_filter( $ngram, function ( $v ) use( $min ) { return $v > $min;

	  } );
		}
		uksort( $ngram, function( $k1, $k2 ) use( $ngram ) {
				if ( $ngram[$k1] == $ngram[$k2] ) {
					return strcmp( $k1, $k2 );
				}
				return $ngram[$k2] - $ngram[$k1];
		} );
		if ( count( $ngram ) > $maxNgrams ) {
			array_splice( $ngram, $maxNgrams );
		}
		return $ngram;
	}

	/**
	 * Load data from language file.
	 * @param string $langFile
	 * @return int[] Language file data
	 */
	public function loadLanguageFile( $langFile ) {
		include $langFile;
		array_splice( $ranks, $this->maxNgrams );
		return $ranks;
	}

	/**
	 * Write ngrams to file in PHP format
	 * @param int[] $ngrams
	 * @param string $outfile Output filename
	 */
	public function writeLanguageFile( $ngrams, $outfile ) {
		$out = fopen( $outfile, "w" );
		// write original array as "$ngrams"
		fwrite( $out, '<?php $ngrams = ' . var_export( $ngrams, true ) . ";\n" );
		// write reduced array as "$ranks"
		$rank = 1;
		$ranks = array_map( function ( $x ) use( &$rank ) { return $rank++;

	 }, $ngrams );
		fwrite( $out, '$ranks = ' . var_export( $ranks, true ) . ";\n" );
		fclose( $out );
	}

	/**
	 * Classify text.
	 * @param string $text
	 * @param string[] $candidates List of candidate languages.
	 * @return int[] Array with keys of language names and values of score.
	 * 				 Sorted by ascending score, with first result being the best.
	 */
	public function classify( $text, $candidates = null ) {
		$results = array();
		$this->resultStatus = '';

		// strip non-word characters before checking for min length, don't assess empty strings
		$wordLength = mb_strlen( preg_replace( "/[{$this->wordSeparator}]+/", "", $text ) );
		if ( $wordLength < $this->minInputLength || $wordLength == 0 ) {
			$this->resultStatus = self::STATUSTOOSHORT;
			return $results;
		}

		$inputgrams = array_keys( $this->createLM( $text, $this->maxNgrams ) );
		if ( $candidates ) {
			// flip for more efficient lookups
			$candidates = array_flip( $candidates );
		}
		foreach ( $this->langFiles as $language => $langFile ) {
			if ( $candidates && !isset( $candidates[$language] ) ) {
				continue;
			}
			$ngrams = $this->loadLanguageFile( $langFile );
			$p = 0;
			foreach ( $inputgrams as $i => $ingram ) {
				if ( !empty( $ngrams[$ingram] ) ) {
					$p += abs( $ngrams[$ingram] - $i );
				} else {
					$p += $this->maxNgrams;
				}
			}
			if ( isset( $this->boostedLangs[$language] ) ) {
				$p = round( $p * ( 1 - $this->langBoostScore ) );
			}
			$results[$language] = $p;
		}

		asort( $results );

		// ignore any item that scores higher than best * resultsRatio
		$max = reset( $results ) * $this->resultsRatio;
		$results = array_filter( $results, function ( $res ) use ( $max ) { return $res <= $max;
		} );

		// if more than maxReturnedLanguages remain, the result is too ambiguous, so bail
		if ( count( $results ) > $this->maxReturnedLanguages ) {
			$this->resultStatus = self::STATUSAMBIGUOUS;
			return array();
		}

		// filter max proportion of max score after ambiguity check; reuse $max variable
		$max = count( $inputgrams ) * $this->maxNgrams * $this->maxProportion;
		$results = array_filter( $results, function ( $res ) use ( $max ) { return $res <= $max;
		} );

		if ( count( $results ) == 0 ) {
			$this->resultStatus = self::STATUSNOMATCH;
			return $results;
		}

		return $results;
	}
}

