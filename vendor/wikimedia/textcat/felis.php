<?php
/**
 * Generate ngrams data from text files.
 * Run: php felis.php INPUTDIR OUTPUTDIR
 * INPUTDIR should contain text files e.g. english.txt
 * OUTPUTDIR would contain ngrams files e.g. english.lm
 */

// Language model generation failing?
// up your memory limit or set $minFreq >0 in TextCat.php
// ini_set('memory_limit', '2000000000');

require_once __DIR__.'/TextCat.php';
// TODO: add option to control model ngram count
$maxNgrams = 4000;

if ( $argc != 3 ) {
	die( "Use $argv[0] INPUTDIR OUTPUTDIR\n" );
}
if ( !file_exists( $argv[2] ) ) {
	mkdir( $argv[2], 0755, true );
}
$cat = new TextCat( $argv[2] );

foreach ( new DirectoryIterator( $argv[1] ) as $file ) {
	if ( !$file->isFile() ) {
		continue;
	}
	$ngrams = $cat->createLM( file_get_contents( $file->getPathname() ), $maxNgrams );
	$cat->writeLanguageFile( $ngrams, $argv[2] . "/" . $file->getBasename( ".txt" ) . ".lm" );
}
exit( 0 );
