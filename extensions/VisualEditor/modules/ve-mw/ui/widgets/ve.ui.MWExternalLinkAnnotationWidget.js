/*!
 * VisualEditor UserInterface MWExternalLinkAnnotationWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWExternalLinkAnnotationWidget object.
 *
 * @class
 * @extends ve.ui.LinkAnnotationWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWExternalLinkAnnotationWidget = function VeUiMWExternalLinkAnnotationWidget() {
	// Parent constructor
	ve.ui.MWExternalLinkAnnotationWidget.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWExternalLinkAnnotationWidget, ve.ui.LinkAnnotationWidget );

/* Static Methods */

/**
 * @inheritdoc
 */
ve.ui.MWExternalLinkAnnotationWidget.static.getAnnotationFromText = function ( value ) {
	const href = value.trim();

	// Keep annotation in sync with value
	if ( href === '' ) {
		return null;
	} else {
		return new ve.dm.MWExternalLinkAnnotation( {
			type: 'link/mwExternal',
			attributes: { href }
		} );
	}
};

/**
 * Create an external link input widget.
 *
 * @param {Object} [config] Configuration options
 * @return {OO.ui.TextInputWidget} Text input widget
 */
ve.ui.MWExternalLinkAnnotationWidget.static.createExternalLinkInputWidget = function ( config ) {
	const inputWidget = new OO.ui.TextInputWidget( ve.extendObject( {}, config, {
		icon: 'linkExternal',
		type: 'url',
		validate: ( text ) => !!ve.init.platform.getExternalLinkUrlProtocolsRegExp().exec( text.trim() )
	} ) );

	inputWidget.$input.attr( 'aria-label', mw.msg( 'visualeditor-linkinspector-button-link-external' ) );
	return inputWidget;
};

/* Methods */

/**
 * Create a text input widget to be used by the annotation widget
 *
 * @param {Object} [config] Configuration options
 * @return {OO.ui.TextInputWidget} Text input widget
 */
ve.ui.MWExternalLinkAnnotationWidget.prototype.createInputWidget = function ( config ) {
	return this.constructor.static.createExternalLinkInputWidget( config );
};

/**
 * Get the validity of current value
 *
 * @see OO.ui.TextInputWidget#getValidity
 *
 * @return {jQuery.Promise} A promise that resolves if the value is valid,
 *  rejects if not. If it's rejected, it'll resolve with an error code.
 */
ve.ui.MWExternalLinkAnnotationWidget.prototype.getValidity = function () {
	const url = this.input.getValue().trim();
	return this.input.getValidity().then(
		// input validity check covers whether it's a valid external link, now check whether it's blocked:
		() => {
			if ( mw.config.get( 'wgVisualEditorConfig' ).editCheckReliabilityAvailable ) {
				return ( new mw.Api().get( {
					action: 'editcheckreferenceurl',
					url,
					formatversion: 2
				} ) ).then( ( reliablityResults ) => {
					if ( reliablityResults && reliablityResults.editcheckreferenceurl[ url ] === 'blocked' ) {
						return ve.createDeferred().reject( 'invalid-blocked' );
					}
				} );
			}
		},
		// invalid link, so provide a reason
		() => ve.createDeferred().reject( 'invalid-external' )
	);
};
