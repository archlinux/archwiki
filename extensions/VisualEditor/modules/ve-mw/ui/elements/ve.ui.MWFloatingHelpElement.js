/*!
 * VisualEditor UserInterface MWFloatingHelpElement class.
 *
 * @copyright 2011-2021 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * @class
 * @extends OO.ui.Element
 *
 * @constructor
 * @param {Object} config
 * @cfg {string} label
 * @cfg {jQuery} $message
 */
ve.ui.MWFloatingHelpElement = function VeUiMWFloatingHelpElement( config ) {
	// Parent constructor
	ve.ui.MWFloatingHelpElement.super.call( this, config );

	this.helpDialog = new ve.ui.MWFloatingHelpDialog( config );
	this.helpButton = new OO.ui.ButtonWidget( {
		icon: 'help',
		label: config.label,
		title: config.title,
		invisibleLabel: true,
		flags: 'progressive',
		rel: 'help',
		classes: [ 've-ui-mwFloatingHelpElement-toggle' ]
	} ).connect(
		this, { click: 'onClick' }
	);

	this.windowManager = new OO.ui.WindowManager();
	this.windowManager.addWindows( [ this.helpDialog ] );
	this.windowManager.$element.addClass( 've-ui-mwFloatingHelpElement-windowManager' );

	if ( OO.ui.isMobile() ) {
		$( document.body ).append( this.windowManager.$element );
	} else {
		this.$element.append( this.windowManager.$element );
	}

	this.$element
		.addClass( 've-ui-mwFloatingHelpElement' )
		.append( this.helpButton.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWFloatingHelpElement, OO.ui.Element );

/* Methods */

ve.ui.MWFloatingHelpElement.prototype.onClick = function () {
	if ( !this.helpButton.hasFlag( 'primary' ) ) {
		var window = this.windowManager.openWindow( this.helpDialog );

		window.opening.then( this.updateButton.bind( this, true ) );
		window.closing.then( this.updateButton.bind( this, false ) );
	} else {
		this.windowManager.closeWindow( this.helpDialog );
	}
};

ve.ui.MWFloatingHelpElement.prototype.updateButton = function ( isOpen ) {
	this.helpButton
		.setIcon( isOpen ? 'expand' : 'help' )
		.setFlags( { primary: isOpen } );
};
