( function () {

	mw.hook( 'wikipage.content' ).add( function () {
		var errors = mw.config.get( 'ScribuntoErrors' ),
			regex = /^mw-scribunto-error-(\d+)/,
			popup;

		if ( !errors ) {
			mw.log( 'mw.scribunto.errors: ScribuntoErrors does not exist in mw.config' );
			errors = [];
		}

		$( '.scribunto-error' ).each( function ( index, span ) {
			var errorId,
				matches = regex.exec( span.id );
			if ( matches === null ) {
				mw.log( 'mw.scribunto.errors: regex mismatch!' );
				return;
			}
			errorId = parseInt( matches[ 1 ], 10 );
			var $span = $( span );
			$span.on( 'click', function () {
				var error = errors[ errorId ];
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

}() );
