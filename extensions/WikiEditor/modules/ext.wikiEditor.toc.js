/*
 * JavaScript for WikiEditor Table of Contents
 */
jQuery( document ).ready( function ( $ ) {
	// Add table of contents module
	$( '#wpTextbox1' ).wikiEditor( 'addModule', 'toc' );
} );
