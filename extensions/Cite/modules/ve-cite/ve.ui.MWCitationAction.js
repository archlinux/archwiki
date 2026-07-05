'use strict';

/*!
 * VisualEditor UserInterface MWCitationAction class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Opens the {@link ve.ui.MWCitationDialog} in VisualEditor.
 *
 * @constructor
 * @extends ve.ui.Action
 * @param {ve.ui.Surface} surface Surface to act on
 */
ve.ui.MWCitationAction = function VeUiMWCitationAction() {
	// Parent constructor
	ve.ui.MWCitationAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationAction, ve.ui.Action );

/* Static Properties */

ve.ui.MWCitationAction.static.name = 'mwcite';

ve.ui.MWCitationAction.static.methods = [ 'open' ];

/* Methods */

/**
 * Opens the {@link ve.ui.MWCitationDialog} and forwards the toolDefinition
 * as part of the windowData.
 *
 * @param {Object} toolDefinition
 * @param {string} toolDefinition.name
 * @param {string|string[]} toolDefinition.template
 * @param {string} toolDefinition.title
 * @return {boolean} Action was executed
 */
ve.ui.MWCitationAction.prototype.open = function ( toolDefinition ) {
	const windowData = Object.assign( {
		inDialog: this.surface.getInDialog()
	}, toolDefinition );

	this.surface.execute(
		'window',
		'open',
		ve.ui.MWCitationDialog.static.name,
		windowData
	);
	return true;
};

module.exports = ve.ui.MWCitationAction;
