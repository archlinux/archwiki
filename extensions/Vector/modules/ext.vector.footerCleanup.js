/**
 * Footer cleanup for Vector
 */
( function ( $ ) {
	// Wait for onload to remove edit help and "|" after cancel link.
	$( window ).load( function () {
		// Only if advanced editor is found.
		if ( 'wikiEditor' in $ ) {
			$( '.editButtons' ).find( '.editHelp' ).remove();
			// Remove the "|" from after the cancelLink.
			var $cancelLink = $( '#mw-editform-cancel' );
			$cancelLink.parent().empty().append( $cancelLink );
			// Adjustment for proper right side alignment with WikiEditor.
			$( '.editOptions, #editpage-specialchars' ).css( 'margin-right', '-2px' );
		}
	} );
	// Waiting until dom ready as the module is loaded in the head.
	$( document ).ready( function () {
		// Make "Templates used" a collapsible list.
		$( '.templatesUsed' ).footerCollapsibleList( {
			name: 'templates-used-list',
			title: mw.msg( 'vector-footercleanup-templates' )
		} );

		// Make "Hidden categories" a collapsible list.
		$( '.hiddencats' ).footerCollapsibleList( {
			name: 'hidden-categories-list',
			title: mw.msg( 'vector-footercleanup-categories' )
		} );
	} );
} ( jQuery ) );
