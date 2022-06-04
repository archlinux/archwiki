mw.hook( 'wikiEditor.toolbarReady' ).add( function ( $textarea ) {
	var RealtimePreview = require( './RealtimePreview.js' );
	var realtimePreview = new RealtimePreview();
	$textarea.wikiEditor( 'addToToolbar', {
		section: 'secondary',
		group: 'default',
		tools: {
			realtimepreview: {
				type: 'element',
				element: function ( context ) {
					return realtimePreview.getToolbarButton( context ).$element;
				}
			}
		}
	} );
} );
