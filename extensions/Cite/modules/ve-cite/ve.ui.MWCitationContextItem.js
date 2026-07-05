'use strict';

/*!
 * VisualEditor MWCitationContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWCitationDialogTool = require( './ve.ui.MWCitationDialogTool.js' );
const MWReferenceContextItem = require( './ve.ui.MWReferenceContextItem.js' );

/**
 * Context item for a MWCitation.
 *
 * @constructor
 * @extends ve.ui.MWReferenceContextItem
 * @param {ve.ui.LinearContext} context Context the item is in
 * @param {ve.dm.MWReferenceNode} model Model the item is related to
 * @param {Object} [config]
 */
ve.ui.MWCitationContextItem = function VeUiMWCitationContextItem() {
	// Parent constructor
	ve.ui.MWCitationContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwCitationContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationContextItem, MWReferenceContextItem );

/* Static Properties */

/**
 * Only display item for single-template transclusions of these templates.
 *
 * @property {string|string[]|null}
 * @static
 * @inheritable
 */
ve.ui.MWCitationContextItem.static.template = null;

// If the parent class (ve.ui.MWReferenceContextItem) is extended
// and re-registered (e.g. by Citoid), then the inheritance chain is
// broken, and the generic 'reference' context item would show.  To
// aviod this we can manually specify that that context should never
// show when a more specific context item is shown.
ve.ui.MWCitationContextItem.static.suppresses = [ 'reference' ];

/* Static Methods */

/**
 * @static
 * @localdoc Sharing implementation with MWCitationDialogTool
 */
ve.ui.MWCitationContextItem.static.isCompatibleWith =
	MWCitationDialogTool.static.isCompatibleWith;

/**
 * Create a context item from a MediaWiki:Cite-tool-definition.json entry
 *
 * @param {Object} toolDefinition
 * @param {string} toolDefinition.icon
 * @param {string} toolDefinition.name
 * @param {string|string[]} toolDefinition.template
 * @param {string} toolDefinition.title
 * @return {ve.ui.MWCitationContextItem}
 */
ve.ui.MWCitationContextItem.static.newFromCitationToolsDefinition = function ( toolDefinition ) {
	const name = ve.ui.MWCitationDialogTool.static.namePrefix + toolDefinition.name;
	const contextItem = function GeneratedMWCitationContextItem() {
		ve.ui.MWCitationContextItem.apply( this, arguments );
	};
	OO.inheritClass( contextItem, ve.ui.MWCitationContextItem );
	contextItem.static.name = name;
	contextItem.static.icon = toolDefinition.icon;
	contextItem.static.label = toolDefinition.title;
	contextItem.static.commandName = name;
	contextItem.static.template = toolDefinition.template;

	return contextItem;
};

module.exports = ve.ui.MWCitationContextItem;
