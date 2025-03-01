/*
 * HTMLForm enhancements:
 * Animate the SelectOrOther fields, to only show the text field when 'other' is selected.
 */

/**
 * jQuery plugin to fade or snap to visible state.
 *
 * @param {boolean} [instantToggle=false]
 * @return {jQuery}
 */
$.fn.goIn = function ( instantToggle ) {
	if ( instantToggle === true ) {
		return this.show();
	}
	return this.stop( true, true ).fadeIn();
};

/**
 * jQuery plugin to fade or snap to hiding state.
 *
 * @param {boolean} [instantToggle=false]
 * @return {jQuery}
 */
$.fn.goOut = function ( instantToggle ) {
	if ( instantToggle === true ) {
		return this.hide();
	}
	return this.stop( true, true ).fadeOut();
};

mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
	/**
	 * @ignore
	 * @param {boolean|jQuery.Event} instant
	 */
	function handleSelectOrOther( instant ) {
		const $select = $( this ).find( 'select' );
		let $other = $( this ).find( 'input' );
		$other = $other.add( $other.siblings( 'br' ) );
		if ( $select.val() === 'other' ) {
			$other.goIn( instant );
		} else {
			$other.goOut( instant );
		}
	}

	// Exclude OOUI widgets, since they're infused and SelectWithInputWidget
	// is responsible for this logic.
	$root
		.on( 'change', '.mw-htmlform-select-or-other:not(.oo-ui-widget)', handleSelectOrOther )
		.find( '.mw-htmlform-select-or-other:not(.oo-ui-widget)' )
		.each( function () {
			handleSelectOrOther.call( this, true );
		} );
} );
