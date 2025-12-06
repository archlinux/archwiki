/**
 * SidebarEditCheckDialog constructor.
 *
 * This dialog is shown to the side of a desktop editing session while the user is editing.
 *
 * @class
 * @extends ve.ui.SidebarDialog
 * @mixes ve.ui.EditCheckDialog
 * @param {Object} config
 */
ve.ui.SidebarEditCheckDialog = function VeUiSidebarEditCheckDialog( config ) {
	// Parent constructor
	ve.ui.SidebarEditCheckDialog.super.call( this, config );

	// Mixin constructor
	ve.ui.EditCheckDialog.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.SidebarEditCheckDialog, ve.ui.SidebarDialog );

OO.mixinClass( ve.ui.SidebarEditCheckDialog, ve.ui.EditCheckDialog );

/* Static properties */

ve.ui.SidebarEditCheckDialog.static.name = 'sidebarEditCheckDialog';

ve.ui.SidebarEditCheckDialog.static.size = 'medium';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.SidebarEditCheckDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.SidebarEditCheckDialog.super.prototype.initialize.call( this );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.initialize.call( this );
};

/**
 * @inheritdoc
 */
ve.ui.SidebarEditCheckDialog.prototype.getSetupProcess = function ( data ) {
	// Parent method
	const process = ve.ui.SidebarEditCheckDialog.super.prototype.getSetupProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getSetupProcess.call( this, data, process ).next( () => {
		this.controller.on( 'position', this.onPosition, null, this );
	} );
};

/**
 * @inheritdoc
 */
ve.ui.SidebarEditCheckDialog.prototype.getTeardownProcess = function ( data ) {
	// Parent method
	const process = ve.ui.SidebarEditCheckDialog.super.prototype.getTeardownProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getTeardownProcess.call( this, data, process ).next( () => {
		this.controller.off( 'position', this.onPosition, this );
	} );
};

/**
 * Handle position events from the controller
 */
ve.ui.SidebarEditCheckDialog.prototype.onPosition = function () {
	if ( this.inBeforeSave ) {
		return;
	}
	const surfaceView = this.surface.getView();
	const surfaceTop = surfaceView.$element.offset().top + 10;
	this.currentActions.forEach( ( action ) => {
		const widget = action.widget;
		if ( widget ) {
			let top = Infinity;
			action.getHighlightSelections().forEach( ( selection ) => {
				const selectionView = ve.ce.Selection.static.newFromModel( selection, surfaceView );
				const rect = selectionView.getSelectionBoundingRect();
				if ( !rect ) {
					return;
				}
				top = Math.min( top, rect.top );
			} );
			widget.$element.css( 'margin-top', '' );
			widget.$element.css(
				'margin-top',
				Math.max( 0, top + surfaceTop - widget.$element.offset().top )
			);
		}
	} );
};

/**
 * @inheritdoc ve.ui.EditCheckDialog
 */
ve.ui.SidebarEditCheckDialog.prototype.onToggleCollapse = function () {
	// mixin
	ve.ui.EditCheckDialog.prototype.onToggleCollapse.apply( this, arguments );

	this.onPosition();
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.SidebarEditCheckDialog );
