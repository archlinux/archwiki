/*
 * Edit warning for Vector
 */
( function ( mw, $ ) {
	$(document).ready( function () {
		// Check if EditWarning is enabled and if we need it
		if ( $( '#wpTextbox1' ).length === 0 ) {
			return true;
		}
		// Get the original values of some form elements
		$( '#wpTextbox1, #wpSummary' ).each( function () {
			$(this).data( 'origtext', $(this).val() );
		});
		// Attach our own handler for onbeforeunload which respects the current one
		var otherOnBeforeUnload = window.onbeforeunload;
		function ourOnBeforeUnload() {
			var fallbackResult, retval;

			// Check if someone already set an onbeforeunload hook
			if ( otherOnBeforeUnload ) {
				// Get the result of their onbeforeunload hook
				fallbackResult = otherOnBeforeUnload();
			}

			// Check if their onbeforeunload hook returned something
			if ( fallbackResult !== undefined ) {
				// Exit here, returning their message
				retval = fallbackResult;
			} else {
				// Check if the current values of some form elements are the same as
				// the original values
				if (
					mw.config.get( 'wgAction' ) == 'submit' ||
						$( '#wpTextbox1' ).data( 'origtext' ) != $( '#wpTextbox1' ).val() ||
						$( '#wpSummary' ).data( 'origtext' ) != $( '#wpSummary' ).val()
				) {
					// Return our message
					retval = mw.msg( 'vector-editwarning-warning' );
				}
			}

			// Unset the onbeforeunload handler so we don't break page caching in Firefox
			window.onbeforeunload = null;
			if ( retval !== undefined ) {
				// ...but if the user chooses not to leave the page, we need to rebind it
				setTimeout( function () {
					window.onbeforeunload = ourOnBeforeUnload;
				}, 1 );
				return retval;
			}
		}

		// Do the first binding now
		window.onbeforeunload = ourOnBeforeUnload;

		function pageShowHandler() {
			// Re-add onbeforeunload handler on pageshow.
			window.onbeforeunload = ourOnBeforeUnload;
		}

		$( window ).on( 'pageshow', pageShowHandler );

		// Add form submission handler
		$( '#editform' ).submit( function () {
			// Restore whatever previous onbeforeload hook existed
			window.onbeforeunload = otherOnBeforeUnload;
		});
	});

}( mediaWiki, jQuery ) );
