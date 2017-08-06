<?php
/**
 * Classify texts using ngrams. See help below for options.
 */
require_once __DIR__.'/TextCat.php';

$options = getopt( 'a:b:B:c:d:f:j:l:m:p:u:w:h' );

if ( isset( $options['h'] ) ) {
	$help = <<<HELP
{$argv[0]} [-d Dir] [-c Lang] [-a Int] [-u Float] [-l Text]
           [-f Int] [-j Int] [-m Int] [-p Float] [-w String]
           [-b Float -B Lang]

    -a NUM  The program returns the best-scoring language together
            with all languages which are <N times worse (set by option -u).
            If the number of languages to be printed is larger than the value
            of this option then no language is returned, but instead a
            message that the input is of an unknown language is printed.
            Default: 10.
    -b NUM  Boost to apply to languages specified by -B. Typical value:
            0.05 to 0.15. Default: 0
    -B LANG,LANG
            Comma-separated list of languages to boost by amount specified
            by -b. Default: none
    -c LANG,LANG,...
            Lists the candidate languages. Only languages listed will be
            considered for detection.
    -d DIR,DIR,...
            Indicates in which directory the language models are
            located (files ending in .lm). Multiple directories can be
            separated by a comma, and will be used in order.  Default: ./LM .
    -f NUM  Before sorting is performed the Ngrams which occur this number
            of times or less are removed. This can be used to speed up
            the program for longer inputs. For short inputs you should use
            the default or -f 0. Default: 0.
    -j NUM  Only attempt classification if the input string is at least this
            many characters. Default: 0.
    -l TEXT Indicates that input is given as an argument on the command line,
            e.g. {$argv[0]} -l "this is english text"
            If this option is not given, the input is stdin.
    -m NUM  Indicates the topmost number of ngrams that should be used.
            Default: 3000
    -p NUM  Indicates the proportion of the maximum (worst) score possible
            allowed for a result to be returned. 1.0 indicates that a string
            made up entirely of n-grams not present in a model can still be
            classified by that model. Default: 1.0
    -u NUM  Determines how much worse result must be in order not to be
            mentioned as an alternative. Typical value: 1.05 or 1.1.
            Default: 1.05.
    -w STRING
            Regex for non-word characters. Default: '0-9\s\(\)'

HELP;
	echo $help;
	exit( 0 );
}

if ( !empty( $options['d'] ) ) {
	$dirs = explode( ",", $options['d'] );
} else {
	$dirs = array( __DIR__."/LM" );
}

$cat = new TextCat( $dirs );

if ( isset( $options['a'] ) ) {
	$cat->setMaxReturnedLanguages( intval( $options['a'] ) );
}
if ( isset( $options['b'] ) ) {
	$cat->setLangBoostScore( floatval( $options['b'] ) );
}
if ( !empty( $options['B'] ) ) {
	$cat->setBoostedLangs( explode( ",", $options['B'] ) );
}
if ( !empty( $options['f'] ) ) {
	$cat->setMinFreq( intval( $options['f'] ) );
}
if ( isset( $options['j'] ) ) {
	$cat->setMinInputLength( intval( $options['j'] ) );
}
if ( !empty( $options['m'] ) ) {
	$cat->setMaxNgrams( intval( $options['m'] ) );
}
if ( !empty( $options['p'] ) ) {
	$cat->setMaxProportion( floatval( $options['p'] ) );
}
if ( !empty( $options['u'] ) ) {
	$cat->setResultsRatio( floatval( $options['u'] ) );
}
if ( isset( $options['w'] ) ) {
	$cat->setWordSeparator( $options['w'] );
}

$input = isset( $options['l'] ) ? $options['l'] : file_get_contents( "php://stdin" );

if ( !empty( $options['c'] ) ) {
	$result = $cat->classify( $input, explode( ",", $options['c'] ) );
} else {
	$result = $cat->classify( $input );
}

if ( empty( $result ) ) {
	echo $cat->getResultStatus() . "\n";
	exit( 1 );
}

echo join( " OR ", array_keys( $result ) ) . "\n";
exit( 0 );
