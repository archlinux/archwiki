'use strict';

/*!
 * VisualEditor UserInterface MWReferenceGroupInput class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Creates an ve.ui.MWReferenceGroupInput object.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 *
 * @constructor
 * @param {Object} config
 * @param {string} config.emptyGroupName Label of the placeholder item
 */
ve.ui.MWReferenceGroupInputWidget = function VeUiMWReferenceGroupInputWidget( config ) {
	this.emptyGroupName = config.emptyGroupName;

	// Parent constructor
	ve.ui.MWReferenceGroupInputWidget.super.call(
		this,
		ve.extendObject( { placeholder: config.emptyGroupName }, config )
	);

	this.$element.addClass( 've-ui-mwReferenceGroupInputWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceGroupInputWidget, OO.ui.ComboBoxInputWidget );

/* Methods */

/**
 * Populate the reference group menu
 *
 * @param {string[]} groups Group names
 */
ve.ui.MWReferenceGroupInputWidget.prototype.populateMenu = function ( groups ) {
	const items = [ new OO.ui.MenuOptionWidget( {
		data: '',
		label: this.emptyGroupName
	} ) ];
	groups.forEach( ( groupName ) => {
		const match = groupName.match( /^mwReference\/(.+)/ );
		if ( match ) {
			items.push( new OO.ui.MenuOptionWidget( { data: match[ 1 ], label: match[ 1 ] } ) );
		}
	} );
	this.menu.clearItems().addItems( items ).toggle( false );
};
