'use strict';

/*!
 * VisualEditor UserInterface MediaWiki references list dialog tool class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWReferencesListNode = require( './ve.dm.MWReferencesListNode.js' );

/**
 * MediaWiki UserInterface references list tool.
 *
 * @constructor
 * @extends ve.ui.FragmentWindowTool
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWReferencesListDialogTool = function VeUiMWReferencesListDialogTool() {
	ve.ui.MWReferencesListDialogTool.super.apply( this, arguments );
};

OO.inheritClass( ve.ui.MWReferencesListDialogTool, ve.ui.FragmentWindowTool );

ve.ui.MWReferencesListDialogTool.static.name = 'referencesList';

ve.ui.MWReferencesListDialogTool.static.group = 'object';

ve.ui.MWReferencesListDialogTool.static.icon = 'references';

ve.ui.MWReferencesListDialogTool.static.title =
	OO.ui.deferMsg( 'cite-ve-dialogbutton-referenceslist-tooltip' );

ve.ui.MWReferencesListDialogTool.static.modelClasses = [ MWReferencesListNode ];

ve.ui.MWReferencesListDialogTool.static.commandName = 'referencesList';

module.exports = ve.ui.MWReferencesListDialogTool;
