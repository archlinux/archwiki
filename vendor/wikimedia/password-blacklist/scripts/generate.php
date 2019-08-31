<?php

use Pleo\BloomFilter\BloomFilter;

if ( PHP_SAPI !== 'cli' ) {
	die( "Run me from the command line please.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$filter = BloomFilter::init( 100000, 0.000001 );

$inputFileName = __DIR__ . '/data/10_million_password_list_top_100000.txt';
$outputFileName = dirname( __DIR__ ) . '/src/' .
	( PHP_INT_SIZE === 8 ? 'blacklist-x64.json' : 'blacklist-x86.json' );

if ( !file_exists( $inputFileName ) ) {
	echo "{$inputFileName} doesn't exist\n";
	return 1;
}

$file = fopen( $inputFileName, 'r' );

if ( !$file ) {
	echo "Cannot open {$inputFileName}\n";
	return 1;
}

while ( !feof( $file ) ) {
	$line = trim( fgets( $file ) );
	if ( !$line ) {
		continue;
	}
	$filter->add( $line );
}
fclose( $file );

file_put_contents(
	$outputFileName,
	json_encode( $filter )
);

echo "Serialised file {$outputFileName} created\n";
