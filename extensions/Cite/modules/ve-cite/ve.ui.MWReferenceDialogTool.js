'use strict';

/*!
 * VisualEditor UserInterface MediaWiki reference dialog tool class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWReferenceNode = require( './ve.dm.MWReferenceNode.js' );

/**
 * MediaWiki UserInterface reference tool.
 *
 * @constructor
 * @extends ve.ui.FragmentWindowTool
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWReferenceDialogTool = function VeUiMWReferenceDialogTool() {
	ve.ui.MWReferenceDialogTool.super.apply( this, arguments );
};

OO.inheritClass( ve.ui.MWReferenceDialogTool, ve.ui.FragmentWindowTool );

ve.ui.MWReferenceDialogTool.static.name = 'reference';

ve.ui.MWReferenceDialogTool.static.group = 'object';

ve.ui.MWReferenceDialogTool.static.icon = 'reference';

// eslint-disable-next-line mediawiki/msg-doc
ve.ui.MWReferenceDialogTool.static.title = OO.ui.deferMsg(
	...mw.config.get( 'wgCiteVisualEditorOtherGroup' ) ?
		[ 'cite-ve-othergroup-item', ve.msg( 'cite-ve-dialogbutton-reference-tooltip' ) ] :
		[ 'cite-ve-dialogbutton-reference-tooltip' ]
);

ve.ui.MWReferenceDialogTool.static.modelClasses = [ MWReferenceNode ];

ve.ui.MWReferenceDialogTool.static.commandName = 'reference';

ve.ui.MWReferenceDialogTool.static.autoAddToCatchall = false;

module.exports = ve.ui.MWReferenceDialogTool;
