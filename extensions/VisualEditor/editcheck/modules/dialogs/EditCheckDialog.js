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

	this.afterRefreshDebounced = ve.debounce( this.afterRefresh.bind( this ) );
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
		icon: 'close'
	} ).connect( this, {
		click: 'onCloseButtonClick'
	} );

	this.scrollIntoView = new ve.ui.EditCheckScrollIntoViewWidget();
	this.scrollIntoView.connect( this, {
		showClick: 'onScrollIntoViewShowClick',
		closeClick: 'onScrollIntoViewCloseClick'
	} );

	this.collapseExpandButton = new OO.ui.ButtonWidget( {
		classes: [ 've-ui-editCheckDialog-collapseExpand' ],
		framed: false,
		label: ve.msg( 'editcheck-dialog-toggle' ),
		invisibleLabel: true,
		icon: 'expand'
	} ).connect( this, {
		click: 'onCollapseExpandButtonClick'
	} );

	this.currentOffset = null;
	this.currentAction = null;
	this.currentActions = null;

	this.footerLabel = new OO.ui.LabelWidget();
	this.previousButton = new OO.ui.ButtonWidget( {
		icon: 'collapse',
		title: ve.msg( 'last' ),
		label: ve.msg( 'last' ),
		invisibleLabel: true,
		framed: false
	} ).connect( this, {
		click: 'onPreviousButtonClick'
	} );
	this.nextButton = new OO.ui.ButtonWidget( {
		icon: 'expand',
		title: ve.msg( 'next' ),
		label: ve.msg( 'next' ),
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
	this.$body.append(
		this.closeButton.$element,
		this.collapseExpandButton.$element,
		this.$actions,
		this.scrollIntoView.$element,
		this.footer.$element
	);
};

/**
 * Handle click events from scroll-into-view's show button.
 */
ve.ui.EditCheckDialog.prototype.onScrollIntoViewShowClick = function () {
	this.controller.focusAction( this.currentActions[ 0 ], true, true );
};

/**
 * Handle click events from scroll-into-view's close button.
 */
ve.ui.EditCheckDialog.prototype.onScrollIntoViewCloseClick = function () {
	if ( this.scrollIntoView ) {
		this.scrollIntoView.$element.remove();
		this.scrollIntoView.clear();
		this.scrollIntoView = null;
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

	this.showActions(
		this.controller.filterActionsForDisplay( actions ),
		this.controller.filterActionsForDisplay( newActions ),
		rejected
	);
};

ve.ui.EditCheckDialog.prototype.onActionsUpdatedProgress = function ( listener, action, oldAction ) {
	if ( this.inBeforeSave !== ( listener === 'onBeforeSave' ) ) {
		return;
	}
	if ( oldAction ) {
		// This can settle out in onActionsUpdated
		return;
	}
	let actions = ve.copy( this.currentActions );
	actions.push( action );
	actions.sort( mw.editcheck.EditCheckAction.static.compareStarts );
	if ( this.updateFilter ) {
		actions = this.updateFilter( actions, [ action ], [], this.currentActions );
	}
	if ( actions.includes( action ) ) {
		this.renderAction( action );
		this.afterRefreshDebounced();
	}
};

/**
 * Show the actions list
 *
 * @param {mw.editcheck.EditCheckAction[]} actions Actions
 * @param {mw.editcheck.EditCheckAction[]} newActions Newly added actions
 * @param {boolean} lastActionRejected Last action was rejected/dismissed
 */
ve.ui.EditCheckDialog.prototype.showActions = function ( actions, newActions, lastActionRejected ) {
	if ( actions.length === 0 ) {
		this.close( { action: lastActionRejected ? 'reject' : 'complete' } );
		return;
	}
	this.currentActions = actions;

	this.refresh();

	let currentAction = this.currentAction;
	let fromUserAction = false;
	if ( currentAction && !actions.includes( this.currentAction ) ) {
		// The current action has been removed. Was this a replacement with an
		// equivalent action due to a focus-change? (Allow overlaps in
		// equals, because the most likely reason for this would be an edit
		// causing the range covered to shift.)
		const replacementAction = actions.find( ( action ) => action.equals( currentAction, true ) );
		if ( replacementAction ) {
			currentAction = replacementAction;
		} else {
			currentAction = null;
			if ( this.constructor.static.alwaysFocusAction ) {
				fromUserAction = true;
			}
		}
	}
	if ( !this.currentAction && newActions.length > 0 ) {
		// There was no focused action, and new actions have arrived
		currentAction = newActions[ 0 ];
	}
	if ( !currentAction && this.constructor.static.alwaysFocusAction ) {
		// This dialog must always have an action focused
		if ( this.currentOffset !== null ) {
			// There was a focused action, so slip the focus onto an adjacent action
			const newOffset = Math.min( this.currentOffset, actions.length - 1 );
			currentAction = actions[ newOffset ];
		} else {
			// There wasn't a focused action, so focus the first available action
			currentAction = actions[ 0 ];
		}
	}
	this.setCurrentAction( currentAction, fromUserAction, currentAction === this.currentAction );
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
	if ( this.scrollIntoView ) {
		this.scrollIntoView.clear();
	}

	this.$actions.empty();

	this.currentActions.forEach( ( action ) => {
		this.renderAction( action );
	} );

	this.afterRefresh();
};

ve.ui.EditCheckDialog.prototype.afterRefresh = function () {
	if ( this.scrollIntoView ) {
		this.scrollIntoView.update();
	}

	// Update positions immediately to prevent flicker
	this.controller.updatePositions();
};

ve.ui.EditCheckDialog.prototype.renderAction = function ( action ) {
	const widget = action.render( action !== this.currentAction, this.singleAction, this.surface );
	widget.on( 'togglecollapse', this.onToggleCollapse, [ action ], this );
	action.off( 'act', this.onAct, this ).on( 'act', this.onAct, [ action, widget ], this );

	this.$actions.append( widget.$element );
	if ( this.scrollIntoView && action.isSuggestion() ) {
		this.scrollIntoView.observe( widget.$element[ 0 ] );
	}
};

/**
 * Set currently active check
 *
 * @param {mw.editcheck.EditCheckAction|null} action New action
 * @param {boolean} fromUserAction The change was triggered by a user action
 * @param {boolean} [internal] Change was triggered internally
 */
ve.ui.EditCheckDialog.prototype.setCurrentAction = function ( action, fromUserAction, internal ) {
	// TODO: work out how to tell the window to recalculate height here

	let offset = this.currentActions.indexOf( action );
	if ( !this.currentActions || !this.currentActions.includes( action ) ) {
		action = null;
		offset = null;
	}

	this.currentAction = action;
	this.currentOffset = offset;

	this.currentActions.forEach( ( cAction, i ) => {
		cAction.widget.toggleCollapse( i !== offset );
	} );

	if ( offset !== null ) {
		this.footerLabel.setLabel(
			ve.msg( 'visualeditor-find-and-replace-results',
				ve.init.platform.formatNumber( offset + 1 ),
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
			action,
			// Scroll selection into view if user interacted with dialog
			fromUserAction,
			// Scroll to top of page in desktop fixed dialog (pre-save)
			this.constructor.static.name === 'fixedEditCheckDialog' && !OO.ui.isMobile()
		);
	}
};

/**
 * Set the offset of the current check, within the list of all checks.
 *
 * @param {number|null} offset New offset
 * @param {boolean} fromUserAction The change was triggered by a user action
 * @param {boolean} [internal] Change was triggered internally
 */
ve.ui.EditCheckDialog.prototype.setCurrentOffset = function ( offset, fromUserAction, internal ) {
	if ( offset === null || offset === -1 ) {
		/* That's valid, carry on */
		offset = null;
	} else if ( !Number.isSafeInteger( offset ) || ( offset < 0 || offset > ( this.currentActions.length - 1 ) ) ) {
		throw new Error( `Bad offset ${ offset }, expected an integer between 0 and ${ this.currentActions.length - 1 }` );
	}
	this.setCurrentAction( this.currentActions[ offset ] || null, fromUserAction, internal );
};

/**
 * Update the disabled state of the navigation buttons
 */
ve.ui.EditCheckDialog.prototype.updateNavigationState = function () {
	const currentAction = this.currentAction;
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
	this.setCurrentAction( action, scrollTo, true );
	this.onScrollIntoViewCloseClick();
};

/**
 * @inheritdoc
 */
ve.ui.EditCheckDialog.prototype.getSetupProcess = function ( data, process ) {
	return process.first( () => {
		this.controller = data.controller;
		this.controller.on( 'actionsUpdated', this.onActionsUpdated, false, this );
		this.controller.on( 'actionsUpdatedProgress', this.onActionsUpdatedProgress, false, this );
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
		this.acting = false;
		this.currentOffset = null;
		this.currentAction = null;

		this.toggle( !this.surface.getTarget().isVirtualKeyboardOpen() );
		this.surface.getTarget().off( 'virtualKeyboardChange' );
		this.surface.getTarget().on( 'virtualKeyboardChange', ( isOpen ) => {
			this.toggle( !isOpen );
		} );

		this.closeButton.toggle( OO.ui.isMobile() && !this.inBeforeSave );
		this.collapseExpandButton.toggle( OO.ui.isMobile() && this.inBeforeSave );

		this.singleAction = this.inBeforeSave || OO.ui.isMobile();
		if ( data.footer !== undefined ) {
			this.footer.toggle( data.footer );
		} else {
			this.footer.toggle( this.singleAction );
		}
		this.$element.toggleClass( 've-ui-editCheckDialog-singleAction', this.singleAction );

		if ( this.surface.context.isVisible() ) {
			// Don't unconditionally do this, because the mobile context
			// triggers a setTimeout'd resize event that scrolls the
			// selection into view, potentially overriding our own scrolling.
			this.surface.context.hide();
		}

		this.showActions( actions, data.newActions || [] );
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
		this.controller.off( 'actionsUpdatedProgress', this.onActionsUpdatedProgress, this );
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
 */
ve.ui.EditCheckDialog.prototype.onToggleCollapse = function ( action ) {
	if ( action.widget.collapsed ) {
		// Expand
		this.setCurrentAction( action, true, false );
		if ( !OO.ui.isMobile() ) {
			this.controller.setIgnoreNextSelectionChange();
			action.select( this.surface );
		}
	} else {
		this.setCurrentAction( null );
	}
};

/**
 * Handle click events from the close button.
 */
ve.ui.EditCheckDialog.prototype.onCloseButtonClick = function () {
	this.close();
	if ( OO.ui.isMobile() ) {
		this.controller.focusAction( null );
	}
};

/**
 * Handle click events from the collapse/expand button.
 */
ve.ui.EditCheckDialog.prototype.onCollapseExpandButtonClick = function () {
	// eslint-disable-next-line no-jquery/no-class-state
	const collapse = !this.$element.hasClass( 've-ui-editCheckDialog-collapsed' );
	this.$element.toggleClass( 've-ui-editCheckDialog-collapsed', collapse );
	this.collapseExpandButton.setIcon( collapse ? 'collapse' : 'expand' );
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
