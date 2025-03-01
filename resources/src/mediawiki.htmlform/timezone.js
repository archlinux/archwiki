/*
 * HTMLForm enhancements:
 * Enable the "Fill in from browser" option for the timezone selector
 */
function minutesToHours( min ) {
	const tzHour = Math.floor( Math.abs( min ) / 60 ),
		tzMin = Math.abs( min ) % 60,
		tzString = ( ( min >= 0 ) ? '' : '-' ) + ( ( tzHour < 10 ) ? '0' : '' ) + tzHour +
			':' + ( ( tzMin < 10 ) ? '0' : '' ) + tzMin;
	return tzString;
}

mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
	mw.loader.using( 'mediawiki.widgets.SelectWithInputWidget', () => {
		$root.find( '.mw-htmlform-timezone-field' ).each( function () {
			// This is identical to OO.ui.infuse( ... ), but it makes the class name of the result known.
			const timezoneWidget = mw.widgets.SelectWithInputWidget.static.infuse( $( this ) );

			function maybeGuessTimezone() {
				if ( timezoneWidget.dropdowninput.getValue() !== 'guess' ) {
					return;
				}
				// If available, get the named time zone from the browser.
				// (We also support older browsers where this API is not available.)
				let timeZone;
				try {
					// This may return undefined
					timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
				} catch ( err ) {
					timeZone = null;
				}

				// Get the time offset
				const minuteDiff = -( new Date().getTimezoneOffset() );

				let newValue;
				if ( timeZone ) {
					// Try to save both time zone and offset
					newValue = 'ZoneInfo|' + minuteDiff + '|' + timeZone;
					timezoneWidget.dropdowninput.setValue( newValue );
				}
				if ( !timeZone || timezoneWidget.dropdowninput.getValue() !== newValue ) {
					// No time zone, or it's unknown to MediaWiki. Save only offset
					timezoneWidget.dropdowninput.setValue( 'other' );
					timezoneWidget.textinput.setValue( minutesToHours( minuteDiff ) );
				}
			}

			timezoneWidget.dropdowninput.on( 'change', maybeGuessTimezone );
			maybeGuessTimezone();
		} );
	} );
} );
