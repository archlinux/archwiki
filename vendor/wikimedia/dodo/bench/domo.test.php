<?php

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.NotCamelCaps
// phpcs:disable MediaWiki.Commenting.FunctionComment.WrongStyle
// phpcs:disable MediaWiki.NamingConventions.PrefixedGlobalFunctions

// Run with: 'php ./dodo.test.php'

// Call this at each point of interest, passing a descriptive string
function prof_flag( $str ) {
	global $prof_timing, $prof_names;
	$prof_timing[] = microtime( true );
	$prof_names[] = $str;
}

// Call this when you're done and want to see the results
function prof_print() {
	global $prof_timing, $prof_names;
	$size = count( $prof_timing );
	for ( $i = 0;$i < $size - 1; $i++ ) {
		// echo "<b>{$prof_names[$i]}</b><br>";
		//echo sprintf("&nbsp;&nbsp;&nbsp;%f<br>", $prof_timing[$i+1]-$prof_timing[$i]);

		echo $prof_names[$i] . ": " . strval( $prof_timing[$i + 1] - $prof_timing[$i] ) . "\n";
		// echo sprintf("&nbsp;&nbsp;&nbsp;%f<br>", $prof_timing[$i+1]-$prof_timing[$i]);
	}
	echo "\n\n" . $prof_names[$size - 1];
	// echo "<b>{$prof_names[$size-1]}</b><br>";
}

/******************************************************************************
 * This is just a demo to show basic invocation.
 * It is not intended to provide test coverage of any kind.
 */
require_once '../src/dodo.php';
require_once '../src/html_elements.php';

$dom = new Dodo\Document( 'html' );

$html = $dom->createElement( "html" );
$body = $dom->createElement( "body" );

$p = [];

prof_flag( "create" );

for ( $i = 0; $i < 10000; $i++ ) {
		$p[] = $dom->createElement( "p" );
}

prof_flag( "append" );

for ( $i = 0; $i < 10000 - 1; $i++ ) {
		$p[$i]->__unsafe_appendChild( $p[$i + 1] );
}

prof_flag( "append final" );

$body->appendChild( $p[0] );
$html->appendChild( $body );
$dom->appendChild( $html );

prof_flag( "done" );

echo prof_print();

/* Print the tree */
$result = [];
$dom->_serialize( $result );
echo implode( '', $result );
