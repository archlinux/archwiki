( () => {

	mw.hook( 'wikipage.content' ).add( () => {
		const regex = /\bmw-scribunto-error-(\w+)\b/;
		let popup;

		$( '.scribunto-error' ).each( ( index, span ) => {
			let matches = regex.exec( span.className );
			if ( matches === null ) {
				// T375539: backward-compatibility with old cached HTML
				matches = regex.exec( span.id );
			}
			if ( matches === null ) {
				mw.log( 'mw.scribunto.errors: regex mismatch!' );
				return;
			}
			const $span = $( span );
			$span.on( 'click', () => {
				const error = mw.config.get( 'ScribuntoErrors-' + matches[ 1 ] );
				if ( typeof error !== 'string' ) {
					mw.log( 'mw.scribunto.errors: error ' + matches[ 1 ] + ' not found.' );
					return;
				}

				if ( !popup ) {
					popup = new OO.ui.PopupWidget( {
						padded: true,
						head: true,
						label: $( '<div>' )
							.text( mw.msg( 'scribunto-parser-dialog-title' ) )
							.addClass( 'scribunto-error-label' )
					} );
					OO.ui.getDefaultOverlay().append( popup.$element );
				}
				popup.$body.html( error );
				popup.setFloatableContainer( $span );
				popup.toggle( true );
			} );
		} );
	} );

} )();
