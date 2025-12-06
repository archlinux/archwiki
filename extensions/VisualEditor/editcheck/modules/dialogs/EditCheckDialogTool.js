/**
 * Adds a robot button to the toolbar to enable/disable edit check.
 * Note: Not currently added to extension.json or init.js, and therefore does not load.
 *
 * @class
 * @extends ve.ui.ToolbarDialogTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.EditCheckDialogTool = function VeUiEditCheckDialogTool() {
	ve.ui.EditCheckDialogTool.super.apply( this, arguments );
};
OO.inheritClass( ve.ui.EditCheckDialogTool, ve.ui.ToolbarDialogTool );
ve.ui.EditCheckDialogTool.static.name = 'editCheckDialog';
ve.ui.EditCheckDialogTool.static.group = 'notices';
ve.ui.EditCheckDialogTool.static.icon = 'robot';
ve.ui.EditCheckDialogTool.static.title = 'Edit check'; // OO.ui.deferMsg( 'visualeditor-dialog-command-help-title' );
ve.ui.EditCheckDialogTool.static.autoAddToCatchall = false;
ve.ui.EditCheckDialogTool.static.commandName = 'editCheckDialogInProcessToggle';

// Demo button for opening edit check sidebar
ve.ui.toolFactory.register( ve.ui.EditCheckDialogTool );
