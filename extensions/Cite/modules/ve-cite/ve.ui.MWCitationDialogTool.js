'use strict';

/*!
 * VisualEditor MediaWiki UserInterface citation dialog tool class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWReferenceDialogTool = require( './ve.ui.MWReferenceDialogTool.js' );

/**
 * Interface for tools that work with the {@link ve.ui.MWCitationDialog}
 *
 * @abstract
 * @constructor
 * @extends ve.ui.MWReferenceDialogTool
 * @param {OO.ui.Toolbar} toolbar
 * @param {Object} [config] Configuration options
 */
ve.ui.MWCitationDialogTool = function VeUiMWCitationDialogTool( toolbar, config ) {
	// Parent method
	ve.ui.MWCitationDialogTool.super.call( this, toolbar, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationDialogTool, MWReferenceDialogTool );

/* Static Properties */
/**
 * Used to prefix the static name of commands, tools and context items from citation tools.
 *
 * @property {string}
 */
ve.ui.MWCitationDialogTool.static.namePrefix = 'cite-';

ve.ui.MWCitationDialogTool.static.group = 'cite';

/**
 * Only display tool for single-template transclusions of these templates.
 *
 * @property {string|string[]|null}
 * @static
 * @inheritable
 */
ve.ui.MWCitationDialogTool.static.template = null;

/* Static Methods */

/**
 * @override
 */
ve.ui.MWCitationDialogTool.static.isCompatibleWith = function ( model ) {
	const compatible = ve.ui.MWCitationDialogTool.super.static.isCompatibleWith.call( this, model );
	if ( !compatible || !this.template ) {
		return false;
	}

	return !!ve.ui.MWCitationDialog.static.getTransclusionNodeWithTemplate(
		model.getInternalItem(),
		this.template
	);
};

/**
 * Create a citation dialog tool from a MediaWiki:Cite-tool-definition.json entry
 *
 * @param {Object} toolDefinition
 * @param {string} toolDefinition.icon
 * @param {string} toolDefinition.name
 * @param {string|string[]} toolDefinition.template
 * @param {string} toolDefinition.title
 * @return {ve.ui.MWCitationDialogTool}
 */
ve.ui.MWCitationDialogTool.static.newFromCitationToolsDefinition = function ( toolDefinition ) {
	const name = this.namePrefix + toolDefinition.name;
	const tool = function GeneratedMWCitationDialogTool() {
		ve.ui.MWCitationDialogTool.apply( this, arguments );
	};
	OO.inheritClass( tool, ve.ui.MWCitationDialogTool );
	tool.static.name = name;
	tool.static.icon = toolDefinition.icon;
	if ( mw.config.get( 'wgCiteVisualEditorOtherGroup' ) ) {
		tool.static.title = mw.msg( 'cite-ve-othergroup-item', toolDefinition.title );
	} else {
		tool.static.title = toolDefinition.title;
	}
	tool.static.commandName = name;
	tool.static.template = toolDefinition.template;
	tool.static.associatedWindows = [ name ];

	return tool;
};

module.exports = ve.ui.MWCitationDialogTool;
