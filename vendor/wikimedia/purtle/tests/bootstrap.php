<?php

/**
 * PHPUnit test bootstrap file for the Purtle component.
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */

if ( PHP_SAPI !== 'cli' ) {
	die( 'Not an entry point' );
}

error_reporting( E_ALL | E_STRICT );
ini_set( 'display_errors', 1 );

if ( !is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	die( 'You need to install this package with Composer before you can run the tests' );
}

$autoLoader = require __DIR__ . '/../vendor/autoload.php';

$autoLoader->addPsr4( 'Wikimedia\\Purtle\\Tests\\', __DIR__ . '/phpunit/' );

unset( $autoLoader );
