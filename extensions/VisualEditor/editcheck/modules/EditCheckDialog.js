/*!
 * VisualEditor UserInterface EditCheckDialog class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Find and replace dialog.
 *
 * @class
 * @extends ve.ui.ToolbarDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.EditCheckDialog = function VeUiEditCheckDialog( config ) {
	// Parent constructor
	ve.ui.EditCheckDialog.super.call( this, config );

	// Pre-initialization
	this.$element.addClass( 've-ui-editCheckDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.EditCheckDialog, ve.ui.ToolbarDialog );

ve.ui.EditCheckDialog.static.name = 'editCheckDialog';

ve.ui.EditCheckDialog.static.position = 'side';

ve.ui.EditCheckDialog.static.size = 'medium';

ve.ui.EditCheckDialog.static.framed = false;

// // Invisible title for accessibility
// ve.ui.EditCheckDialog.static.title =
// OO.ui.deferMsg( 'visualeditor-find-and-replace-title' );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.EditCheckDialog.super.prototype.initialize.call( this );

	this.updateDebounced = ve.debounce( this.update.bind( this ), 100 );
};

ve.ui.EditCheckDialog.prototype.update = function () {
	const surfaceView = this.surface.getView();
	const checks = mw.editcheck.editCheckFactory.createAllByListener( 'onDocumentChange', this.surface.getModel() );
	const $checks = $( '<div>' );
	const selections = [];
	checks.forEach( ( check ) => {
		$checks.append( new OO.ui.MessageWidget( {
			type: 'warning',
			label: check.message,
			framed: false
		} ).$element );
		selections.push( ve.ce.Selection.static.newFromModel( check.highlight.getSelection(), surfaceView ) );
	} );
	surfaceView.drawSelections( 'editCheckWarning', selections );
	this.$body.empty().append( $checks );
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.EditCheckDialog.super.prototype.getSetupProcess.call( this, data )
		.first( () => {
			this.surface = data.surface;
			this.surface.getModel().on( 'undoStackChange', this.updateDebounced );
			this.update();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.EditCheckDialog.super.prototype.getReadyProcess.call( this, data )
		.next( () => {
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.EditCheckDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( () => {
			this.surface.getModel().off( 'undoStackChange', this.updateDebounced );
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.EditCheckDialog );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'editCheckDialog', 'window', 'toggle', { args: [ 'editCheckDialog' ] }
	)
);

/**
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
ve.ui.EditCheckDialogTool.static.commandName = 'editCheckDialog';

// Demo button for opening edit check sidebar
// ve.ui.toolFactory.register( ve.ui.EditCheckDialogTool );
