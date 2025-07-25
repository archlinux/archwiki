( function () {
	'use strict';

	function invertSelections() {
		$( '.oo-ui-inputWidget-input[type=checkbox]' ).each( ( _i, checkbox ) => {
			checkbox.checked = !checkbox.checked;
		} );
	}

	/**
	 * Add a visible codepoint (character) limit label to a TextInputWidget.
	 *
	 * Uses jQuery#codePointLimit to enforce the limit.
	 *
	 * @param {OO.ui.TextInputWidget} textInputWidget Text input widget
	 */
	function visibleCodePointLimit( textInputWidget ) {
		const limit = +textInputWidget.$input.attr( 'maxlength' );
		const codePointLength = require( 'mediawiki.String' ).codePointLength;

		function updateCount() {
			const value = textInputWidget.getValue();
			const remaining = limit - codePointLength( value );
			const label = remaining > 99 ? '' : mw.language.convertNumber( remaining );
			textInputWidget.setLabel( label );
		}
		textInputWidget.on( 'change', updateCount );
		// Initialise value
		updateCount();
	}

	$( () => {
		$( '.ext-replacetext-invert' ).on( 'click', invertSelections );

		// Attach handler for check all/none buttons
		const $checkboxes = $( '#powersearch input[id^="mw-search-ns"]' );
		$( '#mw-search-toggleall' ).on( 'click', () => {
			$checkboxes.prop( 'checked', true );
		} );
		$( '#mw-search-togglenone' ).on( 'click', () => {
			$checkboxes.prop( 'checked', false );
		} );

		const $wpSummary = $( '#wpSummary' );
		if ( $wpSummary.length ) {
			// Show a byte-counter to users with how many bytes are left for their edit summary.
			visibleCodePointLimit( OO.ui.infuse( $wpSummary ) );
		}
	} );
}() );
