<?php
/*
 * Renameuser file for b/c... this sucks
 */
require_once( __DIR__ . '/Renameuser.php' );

$wgExtensionFunctions[] = function() {
	wfWarn( 'The deprecated entrypoint of SpecialRenameuser.php is being used. It will be removed in a future release. Use Renameuser.php instead' );
};
