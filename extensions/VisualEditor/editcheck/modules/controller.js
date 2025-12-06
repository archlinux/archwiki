'use strict';

const midEditListeners = [ 'onDocumentChange', 'onBranchNodeChange' ];

/**
 * EditCheck controller
 *
 * Manages triggering and updating edit checks.
 *
 * @class
 * @constructor
 * @mixes OO.EventEmitter
 * @param {ve.init.mw.Target} target The VisualEditor target
 * @param {Object} config
 * @param {boolean} config.suggestions Enable suggestion mode
 */
function Controller( target, config ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	this.actionsByListener = {};

	this.target = target;

	this.surface = null;
	this.inBeforeSave = false;
	this.branchNode = null;
	this.focusedAction = null;
	this.suggestionsMode = config.suggestions;

	this.taggedFragments = {};
	this.taggedIds = {};

	const debounceWithTeardownCheck = ( func, wait, immediate ) => ve.debounce( ( ...args ) => {
		// This could potentially be called after teardown
		if ( !this.surface ) {
			return;
		}
		return func( ...args );
	}, wait, immediate );

	this.onDocumentChangeDebounced = debounceWithTeardownCheck( this.onDocumentChange.bind( this ), 100 );
	this.onPositionDebounced = debounceWithTeardownCheck( this.onPosition.bind( this ), 100 );
	this.onSelectDebounced = debounceWithTeardownCheck( this.onSelect.bind( this ), 100 );
	this.onContextChangeDebounced = debounceWithTeardownCheck( this.onContextChange.bind( this ), 100 );
	this.updatePositionsDebounced = debounceWithTeardownCheck( this.updatePositions.bind( this ) );

	// Don't run a scroll if the previous animation is still running (which is jQuery 'fast' === 200ms)
	this.scrollActionIntoViewDebounced = debounceWithTeardownCheck( this.scrollActionIntoView.bind( this ), 200, true );
}

/* Inheritance */

OO.mixinClass( Controller, OO.EventEmitter );

/* Events */

/**
 * Actions for a given listener are updated
 *
 * @event Controller#actionsUpdated
 * @param {string} listener The listener type (e.g. 'onBeforeSave')
 * @param {mw.editcheck.EditCheckAction[]} actions All current actions
 * @param {mw.editcheck.EditCheckAction[]} newActions Actions newly added
 * @param {mw.editcheck.EditCheckAction[]} discardedActions Actions newly removed
 * @param {boolean} rejected The update was due to a user rejecting/dismissing a check
 */

/**
 * An action is focused
 *
 * @event Controller#focusAction
 * @param {mw.editcheck.EditCheckAction} action Action
 * @param {number} index Index of the action in #getActions
 * @param {boolean} scrollTo Scroll the action's selection into view
 */

/**
 * Actions have been redrawn or repositioned
 *
 * @event Controller#position
 */

/**
 * Set up controller
 */
Controller.prototype.setup = function () {
	const target = this.target;
	target.on( 'surfaceReady', () => {
		this.surface = target.getSurface();

		if ( this.surface.getMode() !== 'visual' ) {
			// Some checks will entirely work in source mode for most cases.
			// But others will fail spectacularly -- e.g. reference check
			// isn't aware of <ref> tags and so will suggest that all content
			// has references added. As such, disable in source mode for now.
			return;
		}
		if ( !this.editChecksArePossible() ) {
			return;
		}
		// Ideally this would happen slightly earlier:
		$( document.documentElement ).addClass( 've-editcheck-available' );
		// Adding the class can cause large layout changes (e.g. hiding Vector
		// side panels), so emit a window resize event to notify any components
		// that may be affected, e.g. the VE toolbar
		window.dispatchEvent( new Event( 'resize' ) );

		this.surface.getView().on( 'position', this.onPositionDebounced );
		this.surface.getModel().connect( this, {
			undoStackChange: 'onDocumentChangeDebounced',
			select: 'onSelectDebounced',
			contextChange: 'onContextChangeDebounced'
		} );

		this.surface.getSidebarDialogs().connect( this, {
			opening: 'onSidebarDialogsOpeningOrClosing',
			closing: 'onSidebarDialogsOpeningOrClosing'
		} );

		this.on( 'branchNodeChange', this.onBranchNodeChange, null, this );
		this.on( 'actionsUpdated', this.onActionsUpdated, null, this );

		// Run on load (e.g. recovering from auto-save)
		setTimeout( () => this.refresh(), 100 );

		this.surface.on( 'destroy', () => {
			this.off( 'actionsUpdated' );

			const win = this.surface.getSidebarDialogs().getCurrentWindow();
			if ( win ) {
				win.close();
			}

			this.surface = null;
			this.actionsByListener = {};
			this.focusedAction = null;

			this.taggedFragments = {};
			this.taggedIds = {};

			mw.editcheck.checksShown = {};

			$( document.documentElement ).removeClass( 've-editcheck-available' );
			window.dispatchEvent( new Event( 'resize' ) );
		} );
	}, null, this );

	this.setupPreSaveProcess();
};

/**
 * Handle sidebar dialog open/close events
 *
 * Transition skin/VE components around to make room for sidebar
 *
 * @param {OO.ui.Window} win The window instance
 * @param {jQuery.Promise} openingOrClosing Promise that resolves when closing finishes
 */
Controller.prototype.onSidebarDialogsOpeningOrClosing = function ( win, openingOrClosing ) {
	if ( win.constructor.static.name !== 'sidebarEditCheckDialog' ) {
		return;
	}
	const isOpening = !win.isOpened();
	// Wait for sidebar to render before applying CSS which starts transitions
	requestAnimationFrame( () => {
		$( document.documentElement ).toggleClass( 've-editcheck-enabled', isOpening );
	} );
	if ( isOpening ) {
		$( document.documentElement ).addClass( 've-editcheck-transitioning' );
	} else {
		openingOrClosing.then( () => {
			$( document.documentElement ).removeClass( 've-editcheck-transitioning' );
		} );
	}
	// Adjust toolbar position after animation ends
	setTimeout( () => {
		// Check the toolbar still exists (i.e. we haven't closed the editor)
		if ( this.target.toolbar ) {
			this.target.toolbar.onWindowResize();
		}
	}, OO.ui.theme.getDialogTransitionDuration() );
};

/**
 * Check if any edit checks could be run for the current user/context
 *
 * @return {boolean}
 */
Controller.prototype.editChecksArePossible = function () {
	return [ 'onBeforeSave', 'onDocumentChange' ].some(
		( listener ) => mw.editcheck.editCheckFactory.getNamesByListener( listener ).some(
			( checkName ) => {
				const check = mw.editcheck.editCheckFactory.create( checkName, this );
				return check.canBeShown();
			}
		)
	);
};

/**
 * Update position of edit check highlights
 *
 * @fires Controller#position
 */
Controller.prototype.updatePositions = function () {
	this.drawSelections();

	this.emit( 'position' );
};

/**
 * Update edit check list
 *
 * @fires Controller#actionsUpdated
 */
Controller.prototype.refresh = function () {
	if ( this.target.deactivating || !this.target.active ) {
		return;
	}
	if ( this.inBeforeSave ) {
		// These shouldn't be recalculated
		this.emit( 'actionsUpdated', 'onBeforeSave', this.getActions(), [], [], false );
	} else {
		// Use a process so that updateForListener doesn't run twice in parallel,
		// which causes problems as the active actions list can change.
		// TODO: this causes problems if the refresh triggers a sidebar opening
		// and both listeners have actions, as the second actionsUpdated won't be
		// caught by the still-opening sidebar.
		const process = new OO.ui.Process();
		midEditListeners.forEach(
			( listener ) => process.next( () => this.updateForListener( listener, true ) )
		);
		process.execute();
	}
};

Controller.prototype.toggleSuggestionsMode = function () {
	this.suggestionsMode = !this.suggestionsMode;
	this.actionsByListener = {};
	this.refresh();
};

/**
 * Fires all edit checks associated with a given listener.
 *
 * Actions are created anew for every run, but we want continuity for certain state changes. We therefore match them up
 * to existing actions by checking for equality, ie, the same constructor and same ID or fragments.
 *
 * We return a promise so that UI actions such as opening the pre-save dialog
 * do not occur until checks have completed.
 *
 * @param {string} listener e.g. onBeforeSave, onDocumentChange, onBranchNodeChange
 * @param {boolean} fromRefresh Update comes from a manual refresh, not a real event
 * @return {Promise<mw.editcheck.EditCheckAction[]>} An updated set of actions.
 * @fires Controller#actionsUpdated
 */
Controller.prototype.updateForListener = function ( listener, fromRefresh ) {
	// Get the existing actions for this listener
	const existing = this.getActions( listener );

	let actionsPromise = mw.editcheck.editCheckFactory.createAllActionsByListener( this, listener, this.surface.getModel(), false );
	// Create all actions for this listener
	if ( this.suggestionsMode && !this.inBeforeSave ) {
		// eslint-disable-next-line no-jquery/no-when
		actionsPromise = $.when(
			actionsPromise,
			mw.editcheck.editCheckFactory.createAllActionsByListener( this, listener, this.surface.getModel(), true )
		).then( ( checkActions, suggestionActions ) => [
			...checkActions,
			// Discard any suggestions that have an equivalent non-suggestion
			...suggestionActions.filter( ( suggestion ) => !checkActions.find( ( action ) => action.equals( suggestion, true ) ) )
		] );
	}
	return actionsPromise
		.then( ( actionsFromListener ) => {
			// Try to match each new action to an existing one (to preserve state)
			const actions = actionsFromListener.map( ( action ) => {
				const oldAction = existing.find( ( existingAction ) => action.equals( existingAction ) );
				if ( oldAction && !( oldAction.isSuggestion() && !action.isSuggestion() ) ) {
					// Let a new non-suggestion take over from an old suggestion
					return oldAction;
				}
				return action;
			} );

			let staleUpdated = false;
			if ( !fromRefresh ) {
				actions.forEach( ( action ) => {
					if ( action.isStale() ) {
						action.setStale( false );
						staleUpdated = true;
					}
				} );
			}

			// Update the actions for this listener
			this.actionsByListener[ listener ] = actions;

			const newActions = actions.filter( ( action ) => existing.every( ( oldAction ) => !action.equals( oldAction ) ) );
			const discardedActions = existing.filter( ( action ) => actions.every( ( newAction ) => !action.equals( newAction ) ) );

			// If the actions list changed, update
			if ( fromRefresh || staleUpdated || actions.length !== existing.length || newActions.length || discardedActions.length ) {
				// TODO: We need to consider a consistency check here as the document state may have changed since the
				// action within the promise was created
				// Notify listeners that actions have been updated
				this.emit( 'actionsUpdated', listener, this.getActions(), newActions, discardedActions, false );
			}
			// Return the updated actions
			return actions;
		} );
};

/**
 * Remove an edit check action
 *
 * @param {string} listener Listener which triggered the action
 * @param {mw.editcheck.EditCheckAction} action Action to remove
 * @param {boolean} rejected The action was rejected
 * @fires Controller#actionsUpdated
 */
Controller.prototype.removeAction = function ( listener, action, rejected ) {
	const actions = this.getActions( listener );
	const index = actions.indexOf( action );
	if ( index === -1 ) {
		return;
	}
	const removed = actions.splice( index, 1 );

	if ( action === this.focusedAction ) {
		this.focusedAction = null;
	}

	this.emit( 'actionsUpdated', listener, this.getActions(), [], removed, rejected );
};

/**
 * Trigger a focus state for a given action
 *
 * Will emit a focusAction event if the focused action changed or if scrolling
 * was requested.
 *
 * @param {mw.editcheck.EditCheckAction} action Action to focus
 * @param {boolean} [scrollTo] Scroll action's selection into view
 * @param {boolean} [alignToTop] Align selection to top of page when scrolling
 * @fires Controller#focusAction
 * @fires Controller#position
 */
Controller.prototype.focusAction = function ( action, scrollTo, alignToTop ) {
	if ( !scrollTo && action === this.focusedAction ) {
		// Don't emit unnecessary events if there is no change or scroll
		return;
	}

	this.focusedAction = action;

	if ( scrollTo ) {
		this.scrollActionIntoViewDebounced( action, alignToTop );
	}

	this.emit( 'focusAction', action, this.getActions().indexOf( action ), scrollTo );

	this.updatePositionsDebounced();
};

/**
 * Get actions by listener
 *
 * If no listener is specified, then get all actions relevant to the current moment, i.e.:
 * - During beforeSave, get onBeforeSave listeners
 * - Otherwise, get all mid-edit listeners
 *
 * @param {string} [listener] The listener; if omitted, get all relevant actions
 * @return {mw.editcheck.EditCheckAction[]} Actions
 */
Controller.prototype.getActions = function ( listener ) {
	if ( listener ) {
		return this.actionsByListener[ listener ] || [];
	}
	const listeners = this.inBeforeSave ? [ 'onBeforeSave' ] : midEditListeners;
	const actions = [].concat( ...listeners.map( ( lr ) => this.actionsByListener[ lr ] || [] ) );
	actions.sort( mw.editcheck.EditCheckAction.static.compareStarts );
	return actions;
};

/**
 * Handle select events from the surface model
 *
 * @param {ve.dm.Selection} selection New selection
 */
Controller.prototype.onSelect = function () {
	if ( OO.ui.isMobile() ) {
		// On mobile we want to close the drawer if the keyboard is shown
		if ( this.surface.getView().hasNativeCursorSelection() ) {
			// A native cursor selection means the keyboard will be visible
			this.closeDialog( 'mobile-keyboard' );
		}
	}
	this.updateActions();
};

/**
 * Update actions based on the current selection
 *
 * @fires Controller#actionsUpdated
 * @fires Controller#focusAction
 */
Controller.prototype.updateActions = function () {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}
	if ( this.surface.getView().reviewMode ) {
		// In review mode the selection and display of checks is being managed by the dialog
		return;
	}

	const selection = this.surface.getModel().getSelection();

	if ( !this.inBeforeSave && this.updateCurrentBranchNodeFromSelection( selection ) ) {
		this.emit( 'branchNodeChange', this.branchNode );
	}

	if ( this.getActions().length === 0 || selection.isNull() ) {
		// Nothing to do
		return;
	}
	const actions = this.getActions().filter(
		( check ) => check.getHighlightSelections().some(
			( highlight ) => highlight.getCoveringRange().containsRange( selection.getCoveringRange() ) ) );

	if ( actions.length > 0 ) {
		// Focus the last action returned, because it should be the most-specific
		this.focusAction( actions[ actions.length - 1 ], false );
	}
};

/**
 * Handle contextChange events from the surface model
 */
Controller.prototype.onContextChange = function () {
	if ( OO.ui.isMobile() && this.surface.getContext().isVisible() ) {
		// The context overlaps the drawer on mobile, so we should get rid of the drawer
		this.closeDialog( 'context' );
	}
};

/**
 * Handle position events from the surface view
 *
 * @param {boolean} passive Event is passive (don't scroll)
 */
Controller.prototype.onPosition = function ( passive ) {
	this.updatePositionsDebounced();

	if ( !passive && this.getActions().length && this.focusedAction && this.surface.getView().reviewMode ) {
		this.scrollActionIntoViewDebounced( this.focusedAction, !OO.ui.isMobile() );
	}
};

/**
 * Handle changes to the document model (undoStackChange)
 */
Controller.prototype.onDocumentChange = function () {
	if ( !this.inBeforeSave ) {
		this.updateForListener( 'onDocumentChange' );
	}
};

/**
 * Handle changes to the selection moving between branch nodes
 */
Controller.prototype.onBranchNodeChange = function () {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}
	if ( !this.inBeforeSave ) {
		this.updateForListener( 'onBranchNodeChange' );
	}
};

/**
 * Handler when 'actionsUpdated' fires.
 *
 * Updates gutter and highlights when the action list has changed.
 * Displays the edit check dialog if it is not already on screen.
 *
 * @param {string} listener e.g. onBeforeSave, onDocumentChange, onBranchNodeChange
 * @param {mw.editcheck.EditCheckAction[]} actions
 * @param {mw.editcheck.EditCheckAction[]} newActions
 * @param {mw.editcheck.EditCheckAction[]} discardedActions
 */
Controller.prototype.onActionsUpdated = function ( listener, actions, newActions, discardedActions ) {
	// do we need to redraw anything?
	if ( newActions.length || discardedActions.length ) {
		if ( this.focusedAction && discardedActions.includes( this.focusedAction ) ) {
			this.focusedAction = null;
		}
		this.updatePositionsDebounced();
	}

	// Let actions know they've been discarded
	for ( const action of discardedActions ) {
		action.discarded();
	}

	// do we need to show mid-edit actions?
	if ( listener === 'onBeforeSave' ) {
		return;
	}
	if ( !actions.length ) {
		return;
	}
	const windowName = OO.ui.isMobile() ? 'gutterSidebarEditCheckDialog' : 'sidebarEditCheckDialog';
	let shownPromise;
	const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
	if ( !currentWindow || currentWindow.constructor.static.name !== windowName ) {
		this.target.$element.addClass( 've-ui-editCheck-sidebar-active' );
		const windowAction = ve.ui.actionFactory.create( 'window', this.surface, 'check' );
		shownPromise = windowAction.open(
			windowName,
			{ inBeforeSave: this.inBeforeSave, actions: actions, controller: this }
		).then( ( instance ) => {
			ve.track( 'activity.editCheckDialog', { action: 'window-open-from-check-midedit' } );
			instance.closed.then( () => {
				this.target.$element.removeClass( 've-ui-editCheck-sidebar-active' );
			} );
		} );
	} else {
		shownPromise = ve.createDeferred().resolve().promise();
	}
	shownPromise.then( () => {
		this.updateShownStats( newActions, 'midedit' );

		if ( newActions.length ) {
			// Check if any new actions are relevant to our current selection:
			this.updateActions();
		}
	} );
};

/**
 * Adds the pre-save edit check dialog before the normal page commit dialog.
 * Handles closing the mid-edit dialog, as well as restoring it if the user
 * exits the pre-save check dialog.
 *
 * We execute all pre-save checks, which may be asynchronous, and wait for them
 * to complete before opening the pre-save dialog.
 *
 * TODO: Set a time-out so that we don't hang forever if an async check takes
 * too long.
 */
Controller.prototype.setupPreSaveProcess = function () {
	const target = this.target;
	const preSaveProcess = target.getPreSaveProcess();
	preSaveProcess.next( () => {
		const surface = target.getSurface();
		if ( surface.getMode() !== 'visual' ) {
			return;
		}
		ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'Available' } );

		const oldFocusedAction = this.focusedAction;
		this.inBeforeSave = true;
		return this.updateForListener( 'onBeforeSave' ).then( ( actions ) => {
			if ( actions.length ) {
				ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'Shown' } );

				this.setupToolbar( target );

				let $contextContainer, contextPadding;
				if ( surface.context.popup ) {
					contextPadding = surface.context.popup.containerPadding;
					$contextContainer = surface.context.popup.$container;
					surface.context.popup.$container = surface.$element;
					surface.context.popup.containerPadding = 20;
				}

				return this.closeSidebars( 'preSaveProcess' ).then( () => this.closeDialog( 'preSaveProcess' ).then( () => {
					target.onContainerScroll();
					const windowAction = ve.ui.actionFactory.create( 'window', surface, 'check' );
					return windowAction.open( 'fixedEditCheckDialog', { inBeforeSave: true, actions: actions, controller: this } )
						.then( ( instance ) => {
							ve.track( 'activity.editCheckDialog', { action: 'window-open-from-check-presave' } );
							this.updateShownStats( actions, 'presave' );

							this.scrollActionIntoViewDebounced( this.focusedAction, true );

							instance.closed.then( () => {}, () => {} ).then( () => {
								surface.getView().setReviewMode( false );
								this.inBeforeSave = false;
								this.focusedAction = oldFocusedAction;
								// Re-open the mid-edit sidebar if necessary.
								this.refresh();
							} );
							return instance.closing.then( ( data ) => {
								if ( target.deactivating || !target.active ) {
									// Someone clicking "read" to leave the article
									// will trigger the closing of this; in that
									// case, just abandon what we're doing
									ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'Abandoned' } );
									return ve.createDeferred().reject().promise();
								}
								this.restoreToolbar( target );

								if ( $contextContainer ) {
									surface.context.popup.$container = $contextContainer;
									surface.context.popup.containerPadding = contextPadding;
								}

								target.onContainerScroll();

								if ( data ) {
									const delay = ve.createDeferred();
									// If they inserted, wait 2 seconds on desktop
									// before showing save dialog to give user time
									// to see success notification.
									setTimeout( () => {
										ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'Completed' } );
										delay.resolve();
									}, !OO.ui.isMobile() && data.action !== 'reject' ? 2000 : 0 );
									return delay.promise();
								} else {
									// closed via "back" or otherwise
									ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'Abandoned' } );
									return ve.createDeferred().reject().promise();
								}
							} );
						} );
				} ) );
			} else {
				this.inBeforeSave = false;
				// Counterpart to earlier preSaveChecksShown, for use in tracking
				// errors in check-generation:
				ve.track( 'stats.mediawiki_editcheck_preSaveChecks_total', 1, { kind: 'NotShown' } );
			}
		} );
	} );
};

/**
 * Replace toolbar tools for review mode during pre-save checks.
 *
 * @param {ve.init.mw.ArticleTarget} target
 */
Controller.prototype.setupToolbar = function ( target ) {
	const surface = target.getSurface();
	const toolbar = target.getToolbar();
	this.$originalToolbarTools = toolbar.$group.add( toolbar.$after ).addClass( 'oo-ui-element-hidden' );

	const reviewToolbar = new ve.ui.TargetToolbar( target, target.toolbarConfig );
	reviewToolbar.setup( [
		{
			name: 'back',
			type: 'bar',
			include: [ 'editCheckBack' ]
		},
		{
			name: 'title',
			type: 'label',
			label: ve.msg( 'editcheck-dialog-title' )
		},
		{
			name: 'save',
			type: 'bar',
			include: [ 'showSaveDisabled' ]
		}
	], surface );

	reviewToolbar.items[ 1 ].$element.removeClass( 'oo-ui-toolGroup-empty' );
	// Just append the $group of the new toolbar, so we don't have to wire up all the toolbar events.
	this.$reviewToolbarGroup = reviewToolbar.$group.addClass( 've-ui-editCheck-toolbar-tools' );
	toolbar.$group.after( this.$reviewToolbarGroup );

	toolbar.onWindowResize();
};

/**
 * Restores the original toolbar tools after review mode is complete.
 *
 * @param {ve.init.mw.ArticleTarget} target
 */
Controller.prototype.restoreToolbar = function ( target ) {
	if ( !this.$reviewToolbarGroup ) {
		return;
	}
	const toolbar = target.getToolbar();

	this.$reviewToolbarGroup.remove();
	this.$reviewToolbarGroup = null;

	this.$originalToolbarTools.removeClass( 'oo-ui-element-hidden' );

	toolbar.onWindowResize();
};

/**
 * Redraw selection highlights
 */
Controller.prototype.drawSelections = function () {
	const surfaceView = this.surface.getView();
	const activeSelections = this.focusedAction ? this.focusedAction.getHighlightSelections().map(
		( selection ) => ve.ce.Selection.static.newFromModel( selection, surfaceView )
	) : [];
	const isStale = !!this.focusedAction && this.focusedAction.isStale();
	const showGutter = !isStale && !OO.ui.isMobile();
	const activeOptions = { showGutter: showGutter, showRects: !isStale, showBounding: isStale };

	if ( this.inBeforeSave ) {
		// Review mode grays out everything that's not highlighted:
		const highlightNodes = [];
		this.getActions().forEach( ( action ) => {
			action.getHighlightSelections().forEach( ( selection ) => {
				highlightNodes.push( ...surfaceView.getDocument().selectNodes( selection.getCoveringRange(), 'branches' ).map( ( spec ) => spec.node ) );
			} );
		} );
		surfaceView.setReviewMode( true, highlightNodes );
		// The following classes are used here:
		// * ve-ce-surface-selections-editCheck-active
		surfaceView.getSelectionManager().drawSelections( 'editCheck-active', activeSelections, activeOptions );
		return;
	}

	const actions = this.getActions();
	if ( actions.length === 0 ) {
		// Clear any previously drawn selections
		surfaceView.getSelectionManager().drawSelections( 'editCheck-active', [] );
		surfaceView.getSelectionManager().drawSelections( 'editCheck-inactive', [] );
		return;
	}
	const inactiveOptions = { showGutter: showGutter, showRects: false };

	const inactiveSelections = [];
	// Optimization: When showGutter is false inactive selections currently render nothing
	if ( showGutter ) {
		actions.forEach( ( action ) => {
			const isActive = ( action === this.focusedAction );
			action.getHighlightSelections().forEach( ( selection ) => {
				const selectionView = ve.ce.Selection.static.newFromModel( selection, surfaceView );
				if ( isActive ) {
					activeSelections.push( selectionView );
				} else {
					inactiveSelections.push( selectionView );
				}
			} );
		} );
	}

	// The following classes are used here:
	// * ve-ce-surface-selections-editCheck-active
	// * ve-ce-surface-selections-editCheck-inactive
	surfaceView.getSelectionManager().drawSelections( 'editCheck-active', activeSelections, activeOptions );
	surfaceView.getSelectionManager().drawSelections( 'editCheck-inactive', inactiveSelections, inactiveOptions );

	// Add 'type' classes
	actions.forEach( ( action ) => {
		const type = action.getType();
		const isActive = action === this.focusedAction;
		const isPending = action.isTagged( 'pending' );
		action.getHighlightSelections().forEach( ( selection ) => {
			if ( !isActive && !showGutter ) {
				// Optimization: When showGutter is false inactive selections currently render nothing
				return;
			}
			const selectionElements = surfaceView.getSelectionManager().getCachedSelectionElements(
				isActive ? 'editCheck-active' : 'editCheck-inactive', selection, isActive ? activeOptions : inactiveOptions
			);
			if ( selectionElements ) {
				// The following classes are used here:
				// * ve-ce-surface-selection-editCheck-error
				// * ve-ce-surface-selection-editCheck-warning
				// * ve-ce-surface-selection-editCheck-notice
				// * ve-ce-surface-selection-editCheck-success
				selectionElements.$selection.addClass( 've-ce-surface-selection-editCheck-' + type );
				if ( isPending ) {
					selectionElements.$selection.addClass( 've-ce-surface-selection-editCheck-pending' );
				}
			}
		} );
	} );
};

/**
 * Scrolls an action's selection into view
 *
 * @param {mw.editcheck.EditCheckAction} action
 * @param {boolean} [alignToTop] Align the selection to the top of the viewport
 */
Controller.prototype.scrollActionIntoView = function ( action, alignToTop ) {
	// scrollSelectionIntoView scrolls to the focus of a selection, but we
	// want the very beginning to be in view, so collapse it:
	const selection = action.getHighlightSelections()[ 0 ].collapseToStart();
	const padding = ve.copy( this.surface.getPadding() );

	padding.top += 10;
	padding.bottom += 10;

	if ( ve.ui.FixedEditCheckDialog.static.position === 'below' ) {
		// TODO: ui.surface getPadding should really be fixed for this
		const currentWindow = this.surface.getToolbarDialogs( ve.ui.FixedEditCheckDialog.static.position ).getCurrentWindow();
		if ( currentWindow ) {
			padding.bottom += currentWindow.getContentHeight();
		}
	}
	this.surface.scrollSelectionIntoView( selection, {
		animate: true,
		padding: padding,
		alignToTop: alignToTop
	} );
};

/**
 * Closes the fixed edit check dialog (pre-save).
 *
 * @param {string} [action] Name of action which triggered the close ('mobile-keyboard', 'context', 'preSaveProcess')
 * @return {jQuery.Promise}
 */
Controller.prototype.closeDialog = function ( action ) {
	if ( !this.focusedAction ) {
		return ve.createDeferred().resolve().promise();
	}
	this.focusAction( undefined );
	const windowAction = ve.ui.actionFactory.create( 'window', this.surface, 'check' );
	return windowAction.close( 'fixedEditCheckDialog', action ? { action: action } : undefined ).closed.then( () => {}, () => {} );
};

/**
 * Closes the sidebar edit check dialogs (mid-edit).
 *
 * @param {string} [action] Name of action which triggered the close (currently only 'preSaveProcess')
 * @return {jQuery.Promise}
 */
Controller.prototype.closeSidebars = function ( action ) {
	const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
	if ( currentWindow ) {
		// .always is not chainable
		return currentWindow.close( action ? { action: action } : undefined ).closed.then( () => {}, () => {} );
	}
	return ve.createDeferred().resolve().promise();
};

/**
 * Set the current branch node from a selection
 *
 * @param {ve.dm.Selection} selection New selection
 * @return {boolean} whether the branch node changed
 */
Controller.prototype.updateCurrentBranchNodeFromSelection = function ( selection ) {
	let newBranchNode = null;
	if ( selection instanceof ve.dm.LinearSelection ) {
		newBranchNode = this.surface.model.documentModel.getBranchNodeFromOffset( selection.range.to );
	}
	if ( newBranchNode !== this.branchNode ) {
		this.branchNode = newBranchNode;
		return true;
	}
	return false;
};

Controller.prototype.updateShownStats = function ( actions, moment ) {
	actions.forEach( ( action ) => {
		if ( action.isSuggestion() ) {
			ve.track( 'activity.editCheck-' + action.getName(), { action: 'suggestion-shown-' + moment } );
		} else {
			mw.editcheck.checksShown[ action.getName() ] = true;
			ve.track( 'activity.editCheck-' + action.getName(), { action: 'check-shown-' + moment } );
		}
	} );
};

module.exports = {
	Controller: Controller
};
