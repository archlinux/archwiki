/*
 * JavaScript for WikiEditor Preview Dialog
 */
jQuery( document ).ready( function ( $ ) {
	// Add preview module
	$( 'textarea#wpTextbox1' ).wikiEditor( 'addModule', 'previewDialog' );
} );
