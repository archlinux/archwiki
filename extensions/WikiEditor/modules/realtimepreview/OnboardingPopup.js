var localStorage = require( 'mediawiki.storage' ).local;

/**
 * The OnboardingPopup is the pulsating dot and the popup widget.
 *
 * @constructor
 * @class
 */
function OnboardingPopup() {
	OnboardingPopup.super.call( this, {
		classes: [ 'ext-WikiEditor-realtimepreview-onboarding' ]
	} );

	this.localStorageName = 'WikiEditor-RealtimePreview-onboarding-dismissed';
	if ( localStorage.get( this.localStorageName ) ) {
		return;
	}

	// Okay button.
	var okayButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'wikieditor-realtimepreview-onboarding-button' ),
		flags: [ 'progressive', 'primary' ]
	} );
	okayButton.connect( this, { click: 'onPopupButtonClick' } );

	// Pulsating dot.
	var $pulsatingDot = $( '<a>' ).addClass( 'ext-WikiEditor-realtimepreview-onboarding-dot mw-pulsating-dot' );

	// Popup.
	var $popupContent = $( '<div>' ).append(
		$( '<div>' ).addClass( 'ext-WikiEditor-image-realtimepreview-onboarding' ),
		$( '<h3>' ).text( mw.msg( 'wikieditor-realtimepreview-onboarding-title' ) ),
		$( '<p>' ).text( mw.msg( 'wikieditor-realtimepreview-onboarding-body' ) ),
		$( '<div>' ).addClass( 'ext-WikiEditor-realtimepreview-onboarding-button' )
			.append( okayButton.$element )
	);
	var popup = new OO.ui.PopupWidget( {
		classes: [ 'ext-WikiEditor-realtimepreview-onboarding-popup' ],
		$floatableContainer: $pulsatingDot,
		$content: $popupContent,
		padded: true,
		width: 300,
		align: 'backwards'
	} );
	this.popup = popup;

	// Toggle the popup when the dot is clicked.
	$pulsatingDot.on( 'click', function () {
		popup.toggle();
	} );
	// Close the popup when clicking anywhere outside it or the dot.
	$( 'html' ).on( 'click', function ( event ) {
		var $parents = $( event.target ).closest( '.ext-WikiEditor-realtimepreview-onboarding-popup, .ext-WikiEditor-realtimepreview-onboarding-dot' );
		if ( $parents.length === 0 && popup.isVisible() ) {
			popup.toggle( false );
		}
	} );

	// Add the dot and popup to this widget.
	this.$element.append( $pulsatingDot, popup.$element );
}

OO.inheritClass( OnboardingPopup, OO.ui.Widget );

/**
 * @param {Function} callback
 */
OnboardingPopup.prototype.setNextCloseAction = function ( callback ) {
	// Only register a next-action if the onboarding popup is not currently shown.
	// For example, if someone clicks the options button, gets the onboarding popup,
	// but then clicks the toolbar button, we don't want to register another next-action.
	if ( this.popup.isVisible() ) {
		return;
	}
	this.nextCloseAction = callback;
};

/**
 * When clicking the 'okay, got it' button, hide and remove the popup
 * and record the fact that it shouldn't ever open again.
 */
OnboardingPopup.prototype.onPopupButtonClick = function () {
	// First run any close-action that's been registered.
	if ( this.nextCloseAction instanceof Function ) {
		this.nextCloseAction.call();
	}
	// Hide the popup now and forever.
	this.$element.remove();
	this.popup.$element.remove();
	this.popup = false;
	localStorage.set( this.localStorageName, true );
};

/**
 * When the Realtime Preview button is clicked, show the onboarding popup (if it's not been dismissed).
 */
OnboardingPopup.prototype.onPreviewButtonClick = function () {
	if ( !this.popup ) {
		return;
	}
	this.popup.toggle( true );
};

module.exports = OnboardingPopup;
