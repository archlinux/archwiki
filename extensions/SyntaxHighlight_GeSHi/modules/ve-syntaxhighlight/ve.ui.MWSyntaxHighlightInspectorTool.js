/*!
 * VisualEditor UserInterface MWSyntaxHighlightInspectorTool class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/*global ve, OO */

/**
 * MediaWiki UserInterface syntax highlight tool.
 *
 * @class
 * @extends ve.ui.InspectorTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWSyntaxHighlightInspectorTool = function VeUiMWSyntaxHighlightInspectorTool( toolGroup, config ) {
	ve.ui.InspectorTool.call( this, toolGroup, config );
};
OO.inheritClass( ve.ui.MWSyntaxHighlightInspectorTool, ve.ui.InspectorTool );
ve.ui.MWSyntaxHighlightInspectorTool.static.name = 'syntaxhighlight';
ve.ui.MWSyntaxHighlightInspectorTool.static.group = 'object';
ve.ui.MWSyntaxHighlightInspectorTool.static.icon = 'alienextension';
ve.ui.MWSyntaxHighlightInspectorTool.static.title = OO.ui.deferMsg(
	'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-title' );
ve.ui.MWSyntaxHighlightInspectorTool.static.modelClasses = [ ve.dm.MWBlockSyntaxHighlightNode, ve.dm.MWInlineSyntaxHighlightNode ];
ve.ui.MWSyntaxHighlightInspectorTool.static.commandName = 'syntaxhighlight';
ve.ui.toolFactory.register( ve.ui.MWSyntaxHighlightInspectorTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'syntaxhighlight', 'window', 'open',
		{ args: [ 'syntaxhighlight' ], supportedSelections: [ 'linear' ] }
	)
);
