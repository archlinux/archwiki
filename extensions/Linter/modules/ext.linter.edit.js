$( () => {
	const location = mw.config.get( 'wgLinterErrorLocation' );

	function highlightPosition( $textbox ) {
		$textbox.trigger( 'focus' ).textSelection( 'setSelection', { start: location[ 0 ], end: location[ 1 ] } );
		$textbox.textSelection( 'scrollToCaretPosition' );
	}

	if ( location ) {
		if ( mw.user.options.get( 'usebetatoolbar' ) > 0 ) {
			// 2010 wikitext editor
			mw.hook( 'wikiEditor.toolbarReady' ).add( ( $textbox ) => {
				if ( $textbox.attr( 'id' ) === 'wpTextbox1' ) {
					highlightPosition( $textbox );
				}
			} );
		} else {
			// 2003 wikitext editor
			// eslint-disable-next-line no-jquery/no-global-selector
			highlightPosition( $( '#wpTextbox1' ) );
		}

		mw.hook( 've.wikitextInteractive' ).add( () => {
			if ( mw.libs.ve.tempWikitextEditor ) {
				highlightPosition( mw.libs.ve.tempWikitextEditor.$element );
			} else {
				// VE dummy textbox
				// eslint-disable-next-line no-jquery/no-global-selector
				highlightPosition( $( '#wpTextbox1' ) );
			}
		} );
	}
} );
