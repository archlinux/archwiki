/*!
 * VisualEditor UserInterface GutterSidebarEditCheckDialog class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * GutterSidebarEditCheckDialog constructor.
 *
 * The edit check dialog which is created when the user is on mobile. It adds a narrow gutter wide enough to show an
 * icon. When clicked, we create (or reuse) a ve.ui.FixedEditCheckDialog instance to show the check details.
 *
 * @class
 * @extends ve.ui.SidebarDialog
 * @constructor
 * @param {Object} config Configuration options
 */
ve.ui.GutterSidebarEditCheckDialog = function VeUiGutterSidebarEditCheckDialog( config ) {
	// Parent constructor
	ve.ui.GutterSidebarEditCheckDialog.super.call( this, config );

	this.$element.addClass( 've-ui-gutterSidebarEditCheckDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.GutterSidebarEditCheckDialog, ve.ui.SidebarDialog );

/* Static properties */

ve.ui.GutterSidebarEditCheckDialog.static.name = 'gutterSidebarEditCheckDialog';

ve.ui.GutterSidebarEditCheckDialog.static.size = 'gutter';

// The gutter should never steal the focus, as it's intended to be a discreet notification
ve.ui.GutterSidebarEditCheckDialog.static.activeSurface = true;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.GutterSidebarEditCheckDialog.super.prototype.initialize.call( this );
};

/**
 * @inheritdoc
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.getSetupProcess = function ( data ) {
	const process = this.constructor.super.prototype.getSetupProcess.call( this, data );
	return process.first( () => {
		this.controller = data.controller;
		if ( !Object.prototype.hasOwnProperty.call( data, 'inBeforeSave' ) ) {
			throw new Error( 'inBeforeSave argument required' );
		}
		this.inBeforeSave = data.inBeforeSave;
		this.surface = data.controller.surface;
		this.surface.getTarget().$element.addClass( 've-ui-editCheck-gutter-active' );

		this.controller.on( 'actionsUpdated', this.onActionsUpdated, null, this );
		this.controller.on( 'position', this.onPosition, null, this );

		this.renderActions( data.actions || this.controller.getActions() );
	}, this );
};

/**
 * @inheritdoc
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.getTeardownProcess = function ( data ) {
	// Parent method
	const process = ve.ui.GutterSidebarEditCheckDialog.super.prototype.getTeardownProcess.call( this, data );
	return process.first( () => {
		this.controller.disconnect( this );

		this.widgets.forEach( ( widget ) => widget.teardown() );
		this.widgets = [];

		this.surface = null;
		this.controller = null;
	}, this );
};

/**
 * @inheritdoc
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onActionsUpdated = function ( listener, actions ) {
	if ( ( this.inBeforeSave && listener !== 'onBeforeSave' ) || ( !this.inBeforeSave && listener === 'onBeforeSave' ) ) {
		return;
	}
	this.renderActions( actions );
};

/**
 * @inheritdoc
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onPosition = function () {
	this.renderActions( this.controller.getActions() );
};

/**
 * Render the edit check actions as gutter icons, grouping overlapping actions.
 *
 * @param {mw.editcheck.EditCheckAction[]} actions List of actions to render
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.renderActions = function ( actions ) {
	if ( actions.length === 0 ) {
		this.close( 'complete' );
		return;
	}

	const surfaceView = this.surface.getView();
	const sections = [];

	// First join overlapping actions into "sections"
	actions.forEach( ( action ) => {
		const rects = action.getHighlightSelections().map( ( selection ) => {
			const selectionView = ve.ce.Selection.static.newFromModel( selection, surfaceView );
			return selectionView.getSelectionBoundingRect();
		} ).filter( ( rect ) => rect );
		const boundingRect = ve.getBoundingRect( rects );
		if ( !boundingRect ) {
			return;
		}

		// Look for any other section that the new one overlaps with
		// TODO: join when two other sections are joined by the new one?
		const prev = sections.find( ( p ) => !( p.rect.bottom < boundingRect.top || boundingRect.bottom < p.rect.top ) );
		if ( prev ) {
			// overlap, so merge
			prev.actions.push( action );
			// top, bottom, left, right, width, height
			prev.rect.top = Math.min( prev.rect.top, boundingRect.top );
			prev.rect.bottom = Math.max( prev.rect.bottom, boundingRect.bottom );
			prev.rect.height = prev.rect.bottom - prev.rect.top;
			return;
		}
		sections.push( { actions: [ action ], rect: boundingRect } );
	} );

	// Now try to reuse old widgets if possible, to avoid icons flickering
	const oldWidgets = this.widgets || [];
	let shown = false;
	this.widgets = [];
	sections.forEach( ( section ) => {
		let widget;
		const index = oldWidgets.findIndex(
			( owidget ) => owidget.actions.length === section.actions.length &&
				owidget.actions.every( ( oact ) => section.actions.includes( oact ) )
		);
		let actionToShow;
		if ( index !== -1 ) {
			widget = oldWidgets.splice( index, 1 )[ 0 ];
		} else {
			widget = new mw.editcheck.EditCheckGutterSectionWidget( {
				actions: section.actions,
				controller: this.controller
			} );
			if ( !shown ) {
				actionToShow = section.actions.find( ( action ) => action.check.constructor.static.takesFocus );
			}
			this.$body.append( widget.$element );
		}
		widget.setPosition( section.rect );
		this.widgets.push( widget );
		if ( actionToShow ) {
			widget.showDialogWithAction( actionToShow );
			shown = true;
		}
	} );

	oldWidgets.forEach( ( widget ) => widget.teardown() );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.GutterSidebarEditCheckDialog );
