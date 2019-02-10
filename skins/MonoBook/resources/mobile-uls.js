$( function () {
	var mobileCutoffWidth = 550,
		ULSTrigger = $( '#pt-uls' ),
		ULSMoved = false;

	function moveULS() {
		if ( ULSTrigger.length ) {
			if ( !ULSMoved && $( window ).width() <= mobileCutoffWidth ) {
				ULSTrigger.insertBefore( $( '#pt-preferences' ) );

				ULSMoved = true;
			} else if ( ULSMoved && $( window ).width() > mobileCutoffWidth ) {
				ULSTrigger.prepend( $( '#p-preferences' ) );

				ULSMoved = false;
			}
		}
	}

	$( window ).resize( moveULS );
	moveULS();
} );
