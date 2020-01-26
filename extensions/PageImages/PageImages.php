<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PageImages' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PageImages'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for PageImages extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the PageImages extension requires MediaWiki 1.29+' );
}
