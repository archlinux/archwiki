<?php

if ( function_exists( 'wfLoadExtension' ) ) {

	/** @defgroup Title blacklist source types
	 *  @deprecated Use values directly instead.
	 */
	define( 'TBLSRC_MSG', 'message' ); ///< For internal usage
	define( 'TBLSRC_LOCALPAGE', 'localpage' ); ///< Local wiki page
	define( 'TBLSRC_URL', 'url' ); ///< Load blacklist from URL
	define( 'TBLSRC_FILE', 'file' ); ///< Load from file

	wfLoadExtension( 'TitleBlacklist' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['TitleBlacklist'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for TitleBlacklist extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the TitleBlacklist extension requires MediaWiki 1.25+' );
}
