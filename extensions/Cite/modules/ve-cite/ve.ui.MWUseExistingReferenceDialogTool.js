'use strict';

/*!
 * VisualEditor UserInterface MediaWiki use existing reference dialog tool class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * MediaWiki UserInterface use existing reference tool.
 *
 * @constructor
 * @extends ve.ui.WindowTool
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWUseExistingReferenceDialogTool = function VeUiMWUseExistingReferenceDialogTool() {
	ve.ui.MWUseExistingReferenceDialogTool.super.apply( this, arguments );
};

OO.inheritClass( ve.ui.MWUseExistingReferenceDialogTool, ve.ui.WindowTool );

ve.ui.MWUseExistingReferenceDialogTool.static.name = 'reference/existing';

ve.ui.MWUseExistingReferenceDialogTool.static.group = 'object';

ve.ui.MWUseExistingReferenceDialogTool.static.icon = 'referenceExisting';

// eslint-disable-next-line mediawiki/msg-doc
ve.ui.MWUseExistingReferenceDialogTool.static.title = OO.ui.deferMsg(
	...mw.config.get( 'wgCiteVisualEditorOtherGroup' ) ?
		[ 'cite-ve-othergroup-item', ve.msg( 'cite-ve-dialog-reference-useexisting-tool' ) ] :
		[ 'cite-ve-dialog-reference-useexisting-tool' ]
);

ve.ui.MWUseExistingReferenceDialogTool.static.commandName = 'reference/existing';

ve.ui.MWUseExistingReferenceDialogTool.static.autoAddToGroup = false;

ve.ui.MWUseExistingReferenceDialogTool.static.autoAddToCatchall = false;

module.exports = ve.ui.MWUseExistingReferenceDialogTool;
