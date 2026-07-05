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

	this.sections = [];

	this.scrollIntoView = new ve.ui.EditCheckScrollIntoViewWidget();
	this.scrollIntoView.connect( this, {
		showClick: 'onScrollIntoViewShowClick',
		closeClick: 'onScrollIntoViewCloseClick'
	} );

	this.$element.append( this.scrollIntoView.$element );
};

/**
 * Handle click events from scroll-into-view's show button.
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onScrollIntoViewShowClick = function () {
	if ( this.sections.length ) {
		this.showDialogWithAction( this.sections[ 0 ].actions[ 0 ], true, true );
	}
};

/**
 * Handle click events from scroll-into-view's close button.
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onScrollIntoViewCloseClick = function () {
	if ( this.scrollIntoView ) {
		this.scrollIntoView.$element.remove();
		this.scrollIntoView.clear();
		this.scrollIntoView = null;
	}
};

/**
 * Handle focusAction events from the controller
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @param {number} index
 * @param {boolean} scrollTo
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onFocusAction = function () {
	this.onScrollIntoViewCloseClick();
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
		this.controller.on( 'focusAction', this.onFocusAction, null, this );

		this.renderActions( data.actions || this.controller.getActions(), data.newActions || [] );
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
 * Handle updates to the list of edit check actions.
 *
 * @param {string} listener Check listener
 * @param {mw.editcheck.EditCheckAction[]} actions All current actions
 * @param {mw.editcheck.EditCheckAction[]} newActions Newly added actions
 * @param {mw.editcheck.EditCheckAction[]} discardedActions Newly removed actions
 * @param {boolean} rejected The last action was rejected/dismissed
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onActionsUpdated = function ( listener, actions, newActions ) {
	if ( ( this.inBeforeSave && listener !== 'onBeforeSave' ) || ( !this.inBeforeSave && listener === 'onBeforeSave' ) ) {
		return;
	}
	this.renderActions(
		this.controller.filterActionsForDisplay( actions ),
		this.controller.filterActionsForDisplay( newActions )
	);
};

/**
 * Handle position events from the controller
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.onPosition = function () {
	this.renderActions( this.controller.getActions(), [] );
};

/**
 * Render the edit check actions as gutter icons, grouping overlapping actions.
 *
 * @param {mw.editcheck.EditCheckAction[]} actions List of actions to render
 * @param {mw.editcheck.EditCheckAction[]} newActions Newly found actions, which could takeFocus
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.renderActions = function ( actions, newActions = [] ) {
	this.sections = [];
	if ( this.scrollIntoView ) {
		this.scrollIntoView.clear();
	}

	if ( actions.length === 0 ) {
		this.close( 'complete' );
		return;
	}

	const surfaceView = this.surface.getView();

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
		const prev = this.sections.find( ( p ) => !( p.rect.bottom < boundingRect.top || boundingRect.bottom < p.rect.top ) );
		if ( prev ) {
			// overlap, so merge
			prev.actions.push( action );
			// top, bottom, left, right, width, height
			prev.rect.top = Math.min( prev.rect.top, boundingRect.top );
			prev.rect.bottom = Math.max( prev.rect.bottom, boundingRect.bottom );
			prev.rect.height = prev.rect.bottom - prev.rect.top;
			return;
		}
		this.sections.push( { actions: [ action ], rect: boundingRect } );
	} );

	// Now try to reuse old widgets if possible, to avoid icons flickering
	const oldWidgets = this.widgets || [];
	let shown = newActions.length === 0; // Skip this entirely if there are no new actions
	this.widgets = [];
	this.sections.forEach( ( section ) => {
		let widget;
		const index = oldWidgets.findIndex(
			( owidget ) => owidget.actions.length === section.actions.length &&
				owidget.actions.every( ( oact ) => section.actions.includes( oact ) )
		);
		if ( index !== -1 ) {
			widget = oldWidgets.splice( index, 1 )[ 0 ];
		} else {
			widget = new mw.editcheck.EditCheckGutterSectionWidget( {
				actions: section.actions,
				controller: this.controller
			} );
			this.$body.append( widget.$element );
		}
		widget.setPosition( section.rect );
		this.widgets.push( widget );
		if ( this.scrollIntoView && widget.actions.some( ( action ) => action.isSuggestion() ) ) {
			this.scrollIntoView.observe( widget.$element[ 0 ] );
		}

		// See if one of these should be autofocused; it's simplest to check
		// here rather than doing a separate loop because we need to call this
		// on the section widget.
		const actionToShow = !shown && section.actions.find( ( action ) => (
			action.check.takesFocus() && newActions.includes( action )
		) );
		if ( actionToShow ) {
			widget.showDialogWithAction( actionToShow );
			shown = true;
		}
		section.actions.forEach( ( action ) => {
			action.emit( 'shown' );
		} );
	} );

	oldWidgets.forEach( ( widget ) => widget.teardown() );
};

/**
 * Show the edit check dialog with a specific widget's actions and a specific action focused
 *
 * @param {mw.editcheck.EditCheckAction} action Action to focus
 * @param {boolean} [alignToTop] Align the selection to the top of the viewport
 */
ve.ui.GutterSidebarEditCheckDialog.prototype.showDialogWithAction = function ( action, alignToTop ) {
	// The focus changing will also trigger onPosition after this, so we don't
	// need to update the state of anything. We do need to trigger the drawer
	// showing if this is an action that takesFocus, however. We can assume
	// that the widgets will have been redrawn already by an earlier
	// actionsUpdated when the actions were actually discovered.
	if ( action ) {
		for ( const widget of this.widgets ) {
			if ( widget.actions.includes( action ) ) {
				widget.showDialogWithAction( action, alignToTop );
				return;
			}
		}
	}
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.GutterSidebarEditCheckDialog );
