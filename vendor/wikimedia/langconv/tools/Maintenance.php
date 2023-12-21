<?php
/**
 * Find and include Maintenance.php from mediawiki-core.
 *
 * XXX Note that langconv is included from mediawiki-core, via Parsoid,
 * so we should hack the autoloader appropriately to ensure that
 * our classes are preferentially loaded locally, not via the copy in
 * mediawiki-core.
 */

if ( strval( getenv( 'MW_INSTALL_PATH' ) ) !== '' ) {
	require_once __DIR__ . '/../vendor/autoload.php'; // this should take precedence
	require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
	error_log( 'MW_INSTALL_PATH environment variable must be defined.' );
}
