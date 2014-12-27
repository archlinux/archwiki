/*
 * JavaScript for WikiEditor Preview module
 */
jQuery( document ).ready( function ( $ ) {
	// Add preview module
	$( 'textarea#wpTextbox1' ).wikiEditor( 'addModule', 'preview' );
} );
