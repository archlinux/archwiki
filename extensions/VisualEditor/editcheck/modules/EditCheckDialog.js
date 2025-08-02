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
ve.ui.EditCheckDialog = function VeUiEditCheckDialog() {
	// Pre-initialization
	this.$element.addClass( 've-ui-editCheckDialog' );
};

/* Inheritance */

OO.initClass( ve.ui.EditCheckDialog );

/* Static Properties */

// TODO: Keep surface active on mobile for some checks?
ve.ui.EditCheckDialog.static.activeSurface = !OO.ui.isMobile();

// Invisible title for accessibility
ve.ui.EditCheckDialog.static.title = OO.ui.deferMsg( 'editcheck-review-title' );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.initialize = function () {
	this.title = new OO.ui.LabelWidget( {
		label: this.constructor.static.title,
		classes: [ 've-ui-editCheckDialog-title' ]
	} );

	// FIXME: click handlers are getting unbound when the window is closed

	this.closeButton = new OO.ui.ButtonWidget( {
		classes: [ 've-ui-editCheckDialog-close' ],
		framed: false,
		label: ve.msg( 'visualeditor-contextitemwidget-label-close' ),
		invisibleLabel: true,
		icon: 'expand'
	} ).connect( this, {
		click: 'onCloseButtonClick'
	} );

	this.currentOffset = null;
	this.currentActions = null;

	this.footerLabel = new OO.ui.LabelWidget();
	this.previousButton = new OO.ui.ButtonWidget( {
		icon: 'previous',
		title: ve.msg( 'last' ),
		invisibleLabel: true,
		framed: false
	} ).connect( this, {
		click: 'onPreviousButtonClick'
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'next',
		title: ve.msg( 'next' ),
		invisibleLabel: true,
		framed: false
	} ).connect( this, {
		click: 'onNextButtonClick'
	} );
	this.footer = new OO.ui.HorizontalLayout( {
		classes: [ 've-ui-editCheckDialog-footer' ],
		items: [
			this.footerLabel,
			this.previousButton,
			this.nextButton
		]
	} );

	this.$actions = $( '<div>' );
	if ( OO.ui.isMobile() ) {
		this.$body.append( this.title.$element );
	}
	this.$body.append( this.closeButton.$element, this.$actions, this.footer.$element );
};

ve.ui.EditCheckDialog.prototype.onActionsUpdated = function ( listener, actions, newActions, discardedActions, rejected ) {
	if ( listener !== this.listener ) {
		return;
	}
	if ( this.updateFilter ) {
		actions = this.updateFilter( actions, newActions, discardedActions, this.currentActions );
	}
	this.showActions( actions, newActions, rejected );
};

ve.ui.EditCheckDialog.prototype.showActions = function ( actions, newActions, lastActionRejected ) {
	this.currentActions = actions;
	if ( actions.length === 0 ) {
		return this.close( { action: lastActionRejected ? 'reject' : 'complete' } );
	}

	// This just adjusts so the previously selected check remains selected:
	let newOffset = Math.min( this.currentOffset, actions.length - 1 );
	if ( newActions.length ) {
		newOffset = actions.indexOf( newActions[ 0 ] );
	}

	this.refresh();

	this.setCurrentOffset( newOffset, false );
};

ve.ui.EditCheckDialog.prototype.hasAction = function ( action ) {
	return this.currentActions.some( ( caction ) => action.equals( caction ) );
};

ve.ui.EditCheckDialog.prototype.refresh = function () {
	this.$actions.empty();

	this.currentActions.forEach( ( action, index ) => {
		const widget = action.render( index !== this.currentOffset, this.singleAction, this.surface );
		widget.on( 'togglecollapse', this.onToggleCollapse, [ action, index ], this );
		action.off( 'act' ).on( 'act', this.onAct, [ action, widget ], this );
		this.$actions.append( widget.$element );

		// for scrolling later
		action.widget = widget;
	} );
};

/**
 * Set the offset of the current check, within the list of all checks
 *
 * @param {number|null} offset
 * @param {boolean} fromUserAction
 * @param {boolean} internal
 */
ve.ui.EditCheckDialog.prototype.setCurrentOffset = function ( offset, fromUserAction, internal ) {
	// TODO: work out how to tell the window to recalculate height here

	if ( offset === null || offset === -1 ) {
		/* That's valid, carry on */
		offset = null;
	} else if ( !Number.isSafeInteger( offset ) || ( offset < 0 || offset > ( this.currentActions.length - 1 ) ) ) {
		throw new Error( `Bad offset ${ offset }, expected an integer between 0 and ${ this.currentActions.length - 1 }` );
	}

	this.currentOffset = offset;

	this.$body.find( '.ve-ui-editCheckActionWidget' ).each( ( i, el ) => {
		$( el ).toggleClass( 've-ui-editCheckActionWidget-collapsed', i !== this.currentOffset );
	} );

	if ( this.currentOffset !== null ) {
		this.footerLabel.setLabel(
			ve.msg( 'visualeditor-find-and-replace-results',
				ve.init.platform.formatNumber( this.currentOffset + 1 ),
				ve.init.platform.formatNumber( this.currentActions.length )
			)
		);
	} else {
		this.footerLabel.setLabel( '' );
	}
	this.nextButton.setDisabled( this.currentOffset !== null && this.currentOffset >= this.currentActions.length - 1 );
	this.previousButton.setDisabled( this.currentOffset === null || this.currentOffset <= 0 );

	this.updateSize();

	if ( !internal ) {
		this.controller.focusAction( this.currentActions[ this.currentOffset ], fromUserAction );
	}
};

ve.ui.EditCheckDialog.prototype.onFocusAction = function ( action, index, scrollTo ) {
	this.setCurrentOffset( this.currentActions.indexOf( action ), scrollTo, true );
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getSetupProcess = function ( data, process ) {
	return process.first( () => {
		this.controller = data.controller;
		this.controller.on( 'actionsUpdated', this.onActionsUpdated, false, this );
		this.controller.on( 'focusAction', this.onFocusAction, false, this );

		const actions = data.actions || this.controller.getActions( this.listener );

		this.listener = data.listener || 'onDocumentChange';
		this.surface = data.surface;
		this.updateFilter = data.updateFilter;

		this.singleAction = ( this.listener === 'onBeforeSave' ) || OO.ui.isMobile();

		this.closeButton.toggle( OO.ui.isMobile() );
		if ( data.footer !== undefined ) {
			this.footer.toggle( data.footer );
		} else {
			this.footer.toggle(
				this.singleAction &&
				// If we're in single-check mode don't show even the disabled pagers:
				!mw.config.get( 'wgVisualEditorConfig' ).editCheckSingle
			);
		}
		this.$element.toggleClass( 've-ui-editCheckDialog-singleAction', this.singleAction );

		this.surface.context.hide();

		this.showActions( actions, actions );
	}, this );
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getTeardownProcess = function ( data, process ) {
	return process.next( () => {
		this.controller.off( 'actionsUpdated', this.onActionsUpdated, this );
		this.controller.off( 'focusAction', this.onFocusAction, this );
		this.$actions.empty();
	}, this );
};

/**
 * Handle 'act' events from the mw.widget.EditCheckActionWidget
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @param {mw.editcheck.EditCheckActionWidget} widget
 * @param {jQuery.Promise} promise Promise which resolves when the action is complete
 */
ve.ui.EditCheckDialog.prototype.onAct = function ( action, widget, promise ) {
	widget.setDisabled( true );
	this.nextButton.setDisabled( true );
	this.previousButton.setDisabled( true );
	promise.then( ( data ) => {
		widget.setDisabled( false );
		this.nextButton.setDisabled( false );
		this.previousButton.setDisabled( false );

		if ( data && this.listener === 'onBeforeSave' ) {
			// If an action has been taken, we want to linger for a brief moment
			// to show the result of the action before moving away
			// TODO: This was written for AddReferenceEditCheck but should be
			// more generic
			const pause = data.action !== 'reject' ? 500 : 0;
			setTimeout( () => {
				const rejected = [ 'feedback', 'reject', 'dismiss' ].includes( data.action );
				this.controller.removeAction( this.listener, action, rejected );
			}, pause );
		} else {
			this.controller.refresh();
		}
	} );
};

/**
 * Handle 'togglecollapse' events from the mw.widget.EditCheckActionWidget
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @param {number} index
 * @param {boolean} collapsed
 */
ve.ui.EditCheckDialog.prototype.onToggleCollapse = function ( action, index, collapsed ) {
	if ( !collapsed ) {
		// expanded one
		this.setCurrentOffset( this.currentActions.indexOf( action ), true );
		if ( !OO.ui.isMobile() ) {
			const surfaceModel = this.surface.getModel();
			const checkRange = action.getFocusSelection().getCoveringRange();
			const surfaceRange = surfaceModel.getSelection().getCoveringRange();
			// Collapse and move the selection to the nearest part of the check range
			// Don't alter it if it touches the check range
			if ( surfaceRange === null || surfaceRange.end < checkRange.start ) {
				surfaceModel.setLinearSelection( new ve.Range( checkRange.start ) );
				this.surface.getView().activate();
				this.surface.getView().focus();
			} else if ( surfaceRange.start > checkRange.end ) {
				surfaceModel.setLinearSelection( new ve.Range( checkRange.end ) );
				this.surface.getView().activate();
				this.surface.getView().focus();
			}
		}
	}
};

/**
 * Handle click events from the close button
 */
ve.ui.EditCheckDialog.prototype.onCloseButtonClick = function () {
	// eslint-disable-next-line no-jquery/no-class-state
	const collapse = !this.$element.hasClass( 've-ui-editCheckDialog-collapsed' );
	this.$element.toggleClass( 've-ui-editCheckDialog-collapsed', collapse );
	this.closeButton.setIcon( collapse ? 'collapse' : 'expand' );
};

/**
 * Handle click events from the next button
 */
ve.ui.EditCheckDialog.prototype.onNextButtonClick = function () {
	this.setCurrentOffset( this.currentOffset === null ? 0 : this.currentOffset + 1, true );
};

/**
 * Handle click events from the previous button
 */
ve.ui.EditCheckDialog.prototype.onPreviousButtonClick = function () {
	this.setCurrentOffset( this.currentOffset === null ? this.currentActions.length - 1 : this.currentOffset - 1, true );
};

ve.ui.SidebarEditCheckDialog = function VeUiSidebarEditCheckDialog( config ) {
	// Parent constructor
	ve.ui.SidebarEditCheckDialog.super.call( this, config );

	// Mixin constructor
	ve.ui.EditCheckDialog.call( this, config );
};

OO.inheritClass( ve.ui.SidebarEditCheckDialog, ve.ui.SidebarDialog );

OO.mixinClass( ve.ui.SidebarEditCheckDialog, ve.ui.EditCheckDialog );

ve.ui.SidebarEditCheckDialog.static.name = 'sidebarEditCheckDialog';

ve.ui.SidebarEditCheckDialog.static.size = 'medium';

ve.ui.SidebarEditCheckDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.SidebarEditCheckDialog.super.prototype.initialize.call( this );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.initialize.call( this );
};

ve.ui.SidebarEditCheckDialog.prototype.getSetupProcess = function ( data ) {
	// Parent method
	const process = ve.ui.SidebarEditCheckDialog.super.prototype.getSetupProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getSetupProcess.call( this, data, process ).next( () => {
		this.controller.on( 'position', this.onPosition, null, this );
	} );
};

ve.ui.SidebarEditCheckDialog.prototype.getTeardownProcess = function ( data ) {
	// Parent method
	const process = ve.ui.SidebarEditCheckDialog.super.prototype.getTeardownProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getTeardownProcess.call( this, data, process ).next( () => {
		this.controller.off( 'position', this.onPosition, this );
	} );
};

ve.ui.SidebarEditCheckDialog.prototype.onPosition = function () {
	if ( this.listener === 'onBeforeSave' ) {
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

ve.ui.SidebarEditCheckDialog.prototype.onToggleCollapse = function () {
	// mixin
	ve.ui.EditCheckDialog.prototype.onToggleCollapse.apply( this, arguments );

	this.onPosition();
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.SidebarEditCheckDialog );

ve.ui.FixedEditCheckDialog = function VeUiFixedEditCheckDialog( config ) {
	// Parent constructor
	ve.ui.FixedEditCheckDialog.super.call( this, config );

	// Mixin constructor
	ve.ui.EditCheckDialog.call( this, config );
};

OO.inheritClass( ve.ui.FixedEditCheckDialog, ve.ui.ToolbarDialog );

OO.mixinClass( ve.ui.FixedEditCheckDialog, ve.ui.EditCheckDialog );

ve.ui.FixedEditCheckDialog.static.name = 'fixedEditCheckDialog';

ve.ui.FixedEditCheckDialog.static.position = OO.ui.isMobile() ? 'below' : 'side';

ve.ui.FixedEditCheckDialog.static.size = OO.ui.isMobile() ? 'full' : 'medium';

ve.ui.FixedEditCheckDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.FixedEditCheckDialog.super.prototype.initialize.call( this );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.initialize.call( this );
};

ve.ui.FixedEditCheckDialog.prototype.getSetupProcess = function ( data ) {
	// Parent method
	const process = ve.ui.FixedEditCheckDialog.super.prototype.getSetupProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getSetupProcess.call( this, data, process );
};

ve.ui.FixedEditCheckDialog.prototype.getTeardownProcess = function ( data ) {
	// Parent method
	const process = ve.ui.FixedEditCheckDialog.super.prototype.getTeardownProcess.call( this, data );
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.getTeardownProcess.call( this, data, process );
};

ve.ui.FixedEditCheckDialog.prototype.onFocusAction = function ( action, index, scrollTo ) {
	if ( this.singleAction && action === null ) {
		// Can't unset the offset in single-action mode, because it hides the sidebar contents
		return;
	}
	// Mixin method
	return ve.ui.EditCheckDialog.prototype.onFocusAction.call( this, action, index, scrollTo );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.FixedEditCheckDialog );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'editCheckDialogInProcessOpen', 'window', 'open', { args: [ 'editCheckDialog', { listener: 'onDocumentChange' } ] }
	)
);

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'editCheckDialogInProcessToggle', 'window', 'toggle', { args: [ 'editCheckDialog', { listener: 'onDocumentChange' } ] }
	)
);

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'editCheckDialogBeforeSave', 'window', 'toggle', { args: [ 'editCheckDialog', { listener: 'onBeforeSave' } ] }
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
ve.ui.EditCheckDialogTool.static.commandName = 'editCheckDialogInProcessToggle';
// ve.ui.EditCheckDialogTool.static.commandName = 'editCheckDialogBeforeSave';

// Demo button for opening edit check sidebar
// ve.ui.toolFactory.register( ve.ui.EditCheckDialogTool );
