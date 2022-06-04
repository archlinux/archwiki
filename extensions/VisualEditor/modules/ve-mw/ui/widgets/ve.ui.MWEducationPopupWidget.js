/*!
 * VisualEditor UserInterface MediaWiki EducationPopup class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * UserInterface education popup.
 *
 * Shows a pulsating blue dot which, when you click,
 * reveals a popup with useful information.
 *
 * @class
 *
 * @constructor
 * @extends OO.ui.Widget
 * @param {jQuery} $target Element to attach to
 * @param {Object} config Configuration options
 * @cfg {string} popupTitle
 * @cfg {string|jQuery} popupText
 * @cfg {string} [popupImage] Popup image class
 * @cfg {string} [trackingName]
 */
ve.ui.MWEducationPopupWidget = function VeUiMwEducationPopup( $target, config ) {
	config = config || {};

	// HACK: Do not display on platforms other than desktop
	if ( !( ve.init.mw.DesktopArticleTarget && ve.init.target instanceof ve.init.mw.DesktopArticleTarget ) ) {
		return;
	}

	// Do not display if the user already acknowledged the popups
	if ( !mw.libs.ve.shouldShowEducationPopups() ) {
		return;
	}

	// Parent method
	ve.ui.MWEducationPopupWidget.super.call( this, config );

	// Properties
	this.$target = $target;
	this.popupCloseButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-educationpopup-dismiss' ),
		flags: [ 'progressive', 'primary' ],
		classes: [ 've-ui-educationPopup-dismiss' ]
	} );
	this.trackingName = config.trackingName;
	this.$pulsatingDot = $( '<div>' ).addClass( 'mw-pulsating-dot' );

	var $popupContent = $( '<div>' ).append(
		$( '<h3>' ).text( config.popupTitle ),
		// eslint-disable-next-line no-jquery/no-append-html
		$( '<p>' ).append(
			config.popupText instanceof $ ?
				config.popupText :
				document.createTextNode( config.popupText )
		),
		this.popupCloseButton.$element
	);
	ve.targetLinksToNewWindow( $popupContent[ 0 ] );
	if ( config.popupImage ) {
		$popupContent.prepend(
			// eslint-disable-next-line mediawiki/class-doc
			$( '<div>' ).addClass( 've-ui-educationPopup-image ve-ui-educationPopup-image-' + config.popupImage )
		);
	}

	this.popup = new OO.ui.PopupWidget( {
		$floatableContainer: this.$target,
		$content: $popupContent,
		padded: true,
		width: 300
	} );

	this.onTargetMouseDownHandler = this.onTargetMouseDown.bind( this );

	// Events
	this.$target.on( 'mousedown', this.onTargetMouseDownHandler );
	this.popupCloseButton.connect( this, { click: 'onPopupCloseButtonClick' } );

	// DOME
	this.$element.addClass( 've-ui-educationPopup' ).append( this.$pulsatingDot, this.popup.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEducationPopupWidget, OO.ui.Widget );

/* Methods */

/**
 * Handle mouse down events on the handle
 *
 * @param {jQuery.Event} e
 */
ve.ui.MWEducationPopupWidget.prototype.onTargetMouseDown = function () {
	if ( ve.init.target.openEducationPopup ) {
		ve.init.target.openEducationPopup.popup.toggle( false );
		ve.init.target.openEducationPopup.$pulsatingDot.removeClass( 'oo-ui-element-hidden' );
	}
	ve.init.target.openEducationPopup = this;

	this.$pulsatingDot.addClass( 'oo-ui-element-hidden' );
	this.popup.toggle( true );
	this.popupCloseButton.focus();

	if ( this.trackingName ) {
		ve.track( 'activity.' + this.trackingName + 'EducationPopup', { action: 'show' } );
	}
	return false;
};

/**
 * Click handler for the popup close button
 */
ve.ui.MWEducationPopupWidget.prototype.onPopupCloseButtonClick = function () {
	this.$target.off( 'mousedown', this.onTargetMouseDownHandler );
	this.popup.toggle( false );

	ve.init.target.openEducationPopup = null;
	mw.libs.ve.stopShowingEducationPopups();

	var mouseLeft = { which: OO.ui.MouseButtons.LEFT };
	this.$target
		.trigger( $.Event( 'mousedown', mouseLeft ) )
		.trigger( $.Event( 'mouseup', mouseLeft ) )
		.trigger( $.Event( 'click', mouseLeft ) );

};
