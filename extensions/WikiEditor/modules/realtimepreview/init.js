mw.hook( 'wikiEditor.toolbarReady' ).add( function ( $textarea ) {

	// Guard against this module being loaded for non-wikitext pages.
	// This is already done in Hooks.php but Realtime Preview can also be loaded as a gadget so this is necessary.
	if ( mw.config.get( 'wgPageContentModel' ) !== 'wikitext' ) {
		return;
	}

	// Ensure WikiEditor has loaded.
	const context = $textarea.data( 'wikiEditor-context' );
	if ( context === undefined ) {
		return;
	}

	const RealtimePreview = require( './RealtimePreview.js' );
	const realtimePreview = new RealtimePreview( context );

	$textarea.wikiEditor( 'addToToolbar', {
		section: 'secondary',
		group: 'default',
		tools: {
			realtimepreviewReload: {
				type: 'element',
				element: function () {
					return realtimePreview.getToolbarReloadButton();
				}
			},
			realtimepreview: {
				type: 'element',
				element: function () {
					return realtimePreview.getToolbarButton();
				}
			}
		}
	} );
	if ( realtimePreview.getUserPref() && realtimePreview.isScreenWideEnough() ) {
		realtimePreview.setEnabled();
		mw.hook( 'ext.WikiEditor.realtimepreview.inuse' ).fire( this );
	}
} );
