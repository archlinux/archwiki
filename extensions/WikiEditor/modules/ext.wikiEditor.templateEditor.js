/*
 * JavaScript for WikiEditor Template Editor
 */
( function ( mw, $ ) {
	$( document ).ready( function () {
		// Disable in template namespace
		if ( mw.config.get( 'wgNamespaceNumber' ) === 10 ) {
			return true;
		}
		// Add template editor module
		$( 'textarea#wpTextbox1' ).wikiEditor( 'addModule', 'templateEditor' );
	} );
}( mediaWiki, jQuery ) );
