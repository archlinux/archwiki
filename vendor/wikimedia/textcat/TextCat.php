<?php

/**
 * TextCat language classifier
 * See http://odur.let.rug.nl/~vannoord/TextCat/
 */
class TextCat {

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
	 * @param string $dir
	 */
	public function __construct( $dir = null ) {
		if ( empty( $dir ) ) {
			$dir = __DIR__."/LM";
		}
		$this->dir = $dir;
		foreach ( new DirectoryIterator( $dir ) as $file ) {
			if ( !$file->isFile() ) {
				continue;
			}
			if ( $file->getExtension() == "lm" ) {
				$this->langFiles[$file->getBasename( ".lm" )] = $file->getPathname();
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
		$inputgrams = array_keys( $this->createLM( $text, $this->maxNgrams ) );
		if ( $candidates ) {
			// flip for more efficient lookups
			$candidates = array_flip( $candidates );
		}
		$results = array();
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
			$results[$language] = $p;
		}
		asort( $results );
		return $results;
	}
}

