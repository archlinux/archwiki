/*!
 * VisualEditor UserInterface EditCheckDialog class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * EditCheckDialog constructor.
 *
 * Abstract mixin for FixedEditCheckDialog and SidebarEditCheckDialog.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.ui.EditCheckDialog = function VeUiEditCheckDialog() {
	// Pre-initialization
	this.$element.addClass( 've-ui-editCheckDialog' );

	this.acting = false;
};

/* Inheritance */

OO.initClass( ve.ui.EditCheckDialog );

/* Static Properties */

// TODO: Keep surface active on mobile for some checks?
ve.ui.EditCheckDialog.static.activeSurface = !OO.ui.isMobile();

// Invisible title for accessibility
ve.ui.EditCheckDialog.static.title = OO.ui.deferMsg( 'editcheck-review-title' );

ve.ui.EditCheckDialog.static.alwaysFocusAction = false;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.initialize = function () {
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
		icon: 'collapse',
		title: ve.msg( 'last' ),
		invisibleLabel: true,
		framed: false
	} ).connect( this, {
		click: 'onPreviousButtonClick'
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'expand',
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
	this.$body.append( this.closeButton.$element, this.$actions, this.footer.$element );
	if ( mw.editcheck.experimental ) {
		const $warning = new OO.ui.MessageWidget( {
			type: 'error',
			label: 'Currently using experimental edit checks. For testing purposes only.',
			inline: true
		} ).$element.css( {
			'white-space': 'normal',
			margin: '0.5em 1em'
		} );
		if ( OO.ui.isMobile() ) {
			this.footer.$element.before( $warning );
		} else {
			this.$body.append( $warning );
		}
	}
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
ve.ui.EditCheckDialog.prototype.onActionsUpdated = function ( listener, actions, newActions, discardedActions, rejected ) {
	if ( this.inBeforeSave !== ( listener === 'onBeforeSave' ) ) {
		return;
	}
	if ( this.updateFilter ) {
		actions = this.updateFilter( actions, newActions, discardedActions, this.currentActions );
	}
	this.showActions( actions, newActions, rejected );
};

/**
 * Show the actions list
 *
 * @param {mw.editcheck.EditCheckAction[]} actions Actions
 * @param {mw.editcheck.EditCheckAction[]} newActions Newly added actions
 * @param {boolean} lastActionRejected Last action was rejected/dismissed
 */
ve.ui.EditCheckDialog.prototype.showActions = function ( actions, newActions, lastActionRejected ) {
	let currentAction;
	if ( this.currentActions && this.currentOffset !== null && actions.includes( this.currentActions[ this.currentOffset ] ) ) {
		currentAction = this.currentActions[ this.currentOffset ];
	}
	this.currentActions = actions;
	if ( actions.length === 0 ) {
		this.close( { action: lastActionRejected ? 'reject' : 'complete' } );
		return;
	}

	this.refresh();

	if ( currentAction ) {
		// This just adjusts so the previously selected check remains selected:
		this.setCurrentOffset( actions.indexOf( currentAction ), false, true );
	} else if ( newActions.length > 0 ) {
		this.setCurrentOffset( actions.indexOf( newActions[ 0 ] ), false, false );
	} else if ( this.constructor.static.alwaysFocusAction ) {
		// This dialog always wants to have an action focused, so slip the focus onto
		// a nearby action if the current one was removed.
		const newOffset = Math.min( this.currentOffset, actions.length - 1 );
		this.setCurrentOffset( newOffset, true, false );
	}
};

/**
 * Check if an action exists in the current actions.
 *
 * @param {Object} action Action
 * @return {boolean}
 */
ve.ui.EditCheckDialog.prototype.hasAction = function ( action ) {
	return this.currentActions.some( ( a ) => action.equals( a ) );
};

/**
 * Refresh the action list
 */
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

	// Update positions immediately to prevent flicker
	this.controller.updatePositions();
};

/**
 * Set the offset of the current check, within the list of all checks.
 *
 * @param {number|null} offset New offset
 * @param {boolean} fromUserAction The change was triggered by a user action
 * @param {boolean} [internal] Change was triggered internally
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

	this.currentActions.forEach( ( action, i ) => {
		action.widget.toggleCollapse( i !== this.currentOffset );
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

	// Warning: the toggleCollapse calls above may result in a promise's
	// `always` unsetting this.acting. This currently only happens for an
	// action widget's feedback form. If we switch away from jquery promises
	// or add anything which isn't completely synchronous, this call may need
	// to be deferred until this.acting settles:
	this.updateNavigationState();
	this.updateSize();

	if ( !internal ) {
		this.controller.focusAction(
			this.currentActions[ this.currentOffset ],
			// Scroll selection into view if user interacted with dialog
			fromUserAction,
			// Scroll to top of page in desktop fixed dialog (pre-save)
			this.constructor.static.name === 'fixedEditCheckDialog' && !OO.ui.isMobile()
		);
	}
};

/**
 * Update the disabled state of the navigation buttons
 */
ve.ui.EditCheckDialog.prototype.updateNavigationState = function () {
	const currentAction = this.currentActions[ this.currentOffset ];
	if ( currentAction ) {
		currentAction.widget.setDisabled( this.acting );
	}
	this.footerLabel.setDisabled( this.acting );
	this.nextButton.setDisabled(
		this.acting ||
		( this.currentOffset !== null && this.currentOffset >= this.currentActions.length - 1 )
	);
	this.previousButton.setDisabled(
		this.acting ||
		this.currentOffset === null || this.currentOffset <= 0
	);
};

/**
 * Handle focusAction events from the controller
 *
 * @param {mw.editcheck.EditCheckAction} action Action
 * @param {number} index Index of the action in #getActions
 * @param {boolean} scrollTo Scroll the action's selection into view
 */
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

		const actions = data.actions || this.controller.getActions();

		if ( !Object.prototype.hasOwnProperty.call( data, 'inBeforeSave' ) ) {
			throw new Error( 'inBeforeSave argument required' );
		}
		this.inBeforeSave = data.inBeforeSave;
		this.surface = data.surface;
		this.updateFilter = data.updateFilter;

		// Reset currentOffset so that reusing the dialog multiple times in a
		// session won't produce unexpected behavior. (T404661)
		this.currentOffset = null;

		this.singleAction = this.inBeforeSave || OO.ui.isMobile();

		this.closeButton.toggle( OO.ui.isMobile() );
		if ( data.footer !== undefined ) {
			this.footer.toggle( data.footer );
		} else {
			this.footer.toggle( this.singleAction );
		}
		this.$element.toggleClass( 've-ui-editCheckDialog-singleAction', this.singleAction );

		this.surface.context.hide();

		this.showActions( actions, actions );
		if ( this.onPosition ) {
			// This currently only applies to SidebarEditCheckDialog but needs to be
			// called immediately so margin-top is set before the animation starts.
			this.onPosition();
		}
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
 * HACK: Override #ready to prevent trying to focus $content
 *
 * @param {Object} data
 * @return {jQuery.Promise}
 */
ve.ui.EditCheckDialog.prototype.ready = function ( data ) {
	return this.getReadyProcess( data ).execute().then( () => {
		// Force redraw by asking the browser to measure the elements' widths
		this.$element.addClass( 'oo-ui-window-ready' ).width();
		this.$content.addClass( 'oo-ui-window-content-ready' ).width();
	} );
};

/**
 * Handle 'act' events from the mw.widget.EditCheckActionWidget.
 *
 * @param {mw.editcheck.EditCheckAction} action Action
 * @param {mw.editcheck.EditCheckActionWidget} widget Action's widget
 * @param {jQuery.Promise} promise Promise which resolves when the action is complete
 */
ve.ui.EditCheckDialog.prototype.onAct = function ( action, widget, promise ) {
	this.acting = true;
	this.updateNavigationState();
	this.updateSize();
	promise.then( ( data ) => {
		if ( data && this.inBeforeSave ) {
			// If an action has been taken, we want to linger for a brief moment
			// to show the result of the action before moving away
			// TODO: This was written for AddReferenceEditCheck but should be
			// more generic
			const pause = data.action !== 'reject' ? 500 : 0;
			setTimeout( () => {
				const rejected = [ 'reject', 'dismiss' ].includes( data.action );
				this.controller.removeAction( 'onBeforeSave', action, rejected );
			}, pause );
		} else {
			this.controller.refresh();
		}
	} ).always( () => {
		this.acting = false;
		this.updateNavigationState();
	} );
};

/**
 * Handle 'togglecollapse' events from the mw.widget.EditCheckActionWidget.
 *
 * @param {mw.editcheck.EditCheckAction} action Action being expanded/collapsed
 * @param {number} index Index of action in list
 */
ve.ui.EditCheckDialog.prototype.onToggleCollapse = function ( action ) {
	if ( action.widget.collapsed ) {
		// Expand
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
	} else {
		this.setCurrentOffset( null );
	}
};

/**
 * Handle click events from the close button.
 */
ve.ui.EditCheckDialog.prototype.onCloseButtonClick = function () {
	// eslint-disable-next-line no-jquery/no-class-state
	const collapse = !this.$element.hasClass( 've-ui-editCheckDialog-collapsed' );
	this.$element.toggleClass( 've-ui-editCheckDialog-collapsed', collapse );
	this.closeButton.setIcon( collapse ? 'collapse' : 'expand' );
};

/**
 * Handle click events from the next button.
 */
ve.ui.EditCheckDialog.prototype.onNextButtonClick = function () {
	this.setCurrentOffset( this.currentOffset === null ? 0 : this.currentOffset + 1, true );
};

/**
 * Handle click events from the previous button.
 */
ve.ui.EditCheckDialog.prototype.onPreviousButtonClick = function () {
	this.setCurrentOffset( this.currentOffset === null ? this.currentActions.length - 1 : this.currentOffset - 1, true );
};

/* Command registration */

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
