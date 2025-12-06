/*!
 * VisualEditor UserInterface MWExtensionDialog class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for editing generic MediaWiki extensions.
 *
 * @class
 * @abstract
 * @extends ve.ui.NodeDialog
 * @mixes ve.ui.MWExtensionWindow
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWExtensionDialog = function VeUiMWExtensionDialog() {
	// Parent constructor
	ve.ui.MWExtensionDialog.super.apply( this, arguments );

	// Mixin constructors
	ve.ui.MWExtensionWindow.call( this );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWExtensionDialog, ve.ui.NodeDialog );

OO.mixinClass( ve.ui.MWExtensionDialog, ve.ui.MWExtensionWindow );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWExtensionDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWExtensionDialog.super.prototype.initialize.call( this );

	// Mixin method
	ve.ui.MWExtensionWindow.prototype.initialize.call( this );

	// Initialization
	this.$element.addClass( 've-ui-mwExtensionDialog' );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionDialog.prototype.getSetupProcess = function ( data = {} ) {
	// Parent process
	const process = ve.ui.MWExtensionDialog.super.prototype.getSetupProcess.call( this, data );
	// Mixin process
	return ve.ui.MWExtensionWindow.prototype.getSetupProcess.call( this, data, process );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionDialog.prototype.getReadyProcess = function ( data = {} ) {
	// Parent process
	const process = ve.ui.MWExtensionDialog.super.prototype.getReadyProcess.call( this, data );
	// Mixin process
	return ve.ui.MWExtensionWindow.prototype.getReadyProcess.call( this, data, process );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionDialog.prototype.getTeardownProcess = function ( data = {} ) {
	// Parent process
	const process = ve.ui.MWExtensionDialog.super.prototype.getTeardownProcess.call( this, data );
	// Mixin process
	return ve.ui.MWExtensionWindow.prototype.getTeardownProcess.call( this, data, process );
};

/**
 * @inheritdoc
 */
ve.ui.MWExtensionDialog.prototype.getActionProcess = function ( action ) {
	if ( action === '' ) {
		if ( this.hasMeaningfulEdits() ) {
			// eslint-disable-next-line arrow-body-style
			return new OO.ui.Process( () => {
				return this.confirmAbandon().then( ( confirm ) => {
					if ( confirm ) {
						/* We may need to rethink this if something in the
						   dependency chain adds to the current behaviour */
						this.close();
					}
				} );
			} );
		}
	}

	// Parent process
	const process = ve.ui.MWExtensionDialog.super.prototype.getActionProcess.call( this, action );
	// Mixin process
	return ve.ui.MWExtensionWindow.prototype.getActionProcess.call( this, action, process ).next( () => {
		if ( action === 'done' ) {
			this.close( { action: 'done' } );
		}
	} );
};

/**
 * Show a confirmation prompt before closing the dialog.
 * Displays a default prompt of `mw-widgets-abandonedit`.
 *
 * @param {jQuery|string|Function} [prompt] Prompt, defaults to visualeditor-dialog-extension-abandonedit
 * @return {jQuery.Promise} Close promise
 */
ve.ui.MWExtensionDialog.prototype.confirmAbandon = function ( prompt ) {
	if ( prompt === undefined ) {
		prompt = ve.msg( 'visualeditor-dialog-extension-abandonedit' );
	}
	return OO.ui.confirm( prompt, {
		actions: [
			{
				action: 'reject',
				label: ve.msg( 'mw-widgets-abandonedit-keep' ),
				flags: 'safe'
			},
			{
				action: 'accept',
				label: ve.msg( 'mw-widgets-abandonedit-discard' ),
				flags: 'destructive'
			}
		]
	} );
};
