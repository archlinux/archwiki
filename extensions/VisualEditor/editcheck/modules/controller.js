'use strict';

const midEditListeners = [ 'onDocumentChange', 'onBranchNodeChange' ];

/**
 * EditCheck controller
 *
 * Manages triggering and updating edit checks.
 *
 * @class EditCheckController
 * @constructor
 * @mixes OO.EventEmitter
 * @param {ve.init.mw.Target} target The VisualEditor target
 * @param {Object} config
 * @param {boolean} config.suggestionsModeAvailable Suggestions mode is available
 */
function Controller( target, config ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	this.target = target;
	// Suggestion mode is available, and the suggestion mode toggle is visible in the toolbar
	this.suggestionsModeAvailable = config.suggestionsModeAvailable;
	// Suggestions are currently visible, toggled by the toolbar tool
	this.suggestionsVisible = this.suggestionsModeAvailable && (
		!!ve.userConfig( 'visualeditor-editcheck-suggestions-toggle' ) ||
		// Preference only applies to desktop for now
		OO.ui.isMobile()
	);
	// Suppress suggestions without affecting user config or toolbar state, used by external tools
	this.suppressSuggestions = false;

	// These are not in clearState as we want them to persist when switching sections (surface reload)
	this.lastAvailableSuggestionCount = 0;
	this.lastTargetSection = null;

	this.clearState();

	const teardownCheck = () => !!this.surface;

	this.onDocumentChangeDebounced = ve.debounceWithTest( teardownCheck, this.onDocumentChange.bind( this ), 100 );
	this.onPositionDebounced = ve.debounceWithTest( teardownCheck, this.onPosition.bind( this ), 100 );
	this.onSelectDebounced = ve.debounceWithTest( teardownCheck, this.onSelect.bind( this ), 100 );
	this.onContextChangeDebounced = ve.debounceWithTest( teardownCheck, this.onContextChange.bind( this ), 100 );
	this.updatePositionsDebounced = ve.debounceWithTest( teardownCheck, this.updatePositions.bind( this ) );
	this.updateSuggestionCountDebounced = ve.debounceWithTest( teardownCheck, this.updateSuggestionCount.bind( this ), 500 );

	// Don't run a scroll if the previous animation is still running (which is jQuery 'fast' === 200ms)
	this.scrollActionIntoViewDebounced = ve.debounceWithTest( teardownCheck, this.scrollActionIntoView.bind( this ), 200, true );

	this.perf = new mw.editcheck.EditCheckPerformance( this );
}

/* Inheritance */

OO.mixinClass( Controller, OO.EventEmitter );

/* Events */

/**
 * Actions for a given listener are updated
 *
 * @event EditCheckController#actionsUpdated
 * @param {string} listener The listener type (e.g. 'onBeforeSave')
 * @param {mw.editcheck.EditCheckAction[]} actions All current actions
 * @param {mw.editcheck.EditCheckAction[]} newActions Actions newly added
 * @param {mw.editcheck.EditCheckAction[]} discardedActions Actions newly removed
 * @param {boolean} rejected The update was due to a user rejecting/dismissing a check
 */

/**
 * Progress while actions for a given listener are being updated
 *
 * @event EditCheckController#actionsUpdatedProgress
 * @param {string} listener The listener type (e.g. 'onBeforeSave')
 * @param {mw.editcheck.EditCheckAction} action
 * @param {mw.editcheck.EditCheckAction} oldAction previously present equivalent action
 */

/**
 * An action is focused
 *
 * @event EditCheckController#focusAction
 * @param {mw.editcheck.EditCheckAction} action Action
 * @param {number} index Index of the action in #getActions
 * @param {boolean} scrollTo Scroll the action's selection into view
 */

/**
 * Actions have been redrawn or repositioned
 *
 * @event EditCheckController#position
 */

/* Methods */

/**
 * Reset controller state (on init or teardown)
 */
Controller.prototype.clearState = function () {
	this.actionsByListener = {};
	this.surface = null;
	this.inBeforeSave = false;
	this.branchNode = null;
	this.focusedAction = null;
	this.inSetup = null;
	this.ignoreNextSelectionChange = null;
	this.taggedFragments = {};
	this.taggedIds = {};
	this.lastBranchNodeChangeHistoryPointer = null;
	this.notifySwitchedToFullPage = false;
};

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

		this.surface.getView().connect( this, {
			position: 'onPositionDebounced',
			focus: 'onSurfaceFocus'
		} );
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

		if (
			target.section === null &&
			this.lastTargetSection !== null
		) {
			this.notifySwitchedToFullPage = true;
			this.surface.getModel().getDocument().once( 'transact', () => {
				// We only show the notification if the number of suggestions changes
				// due to switching to full page, so clear this after the user starts editing.
				this.notifySwitchedToFullPage = false;
			} );
		}

		this.lastTargetSection = target.section;

		// Run on load (e.g. recovering from auto-save)
		this.inSetup = true;
		setTimeout( () => this.refresh().always( () => {
			this.inSetup = null;
		} ), 100 );

		this.surface.on( 'destroy', () => {
			this.perf.recordTypingLagSummary();
			this.off( 'actionsUpdated' );

			const win = this.surface.getSidebarDialogs().getCurrentWindow();
			if ( win ) {
				win.close();
			}

			this.clearState();

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
		mw.hook( 've.hideVectorColumns' ).fire();
	} else {
		openingOrClosing.then( () => {
			mw.hook( 've.restoreVectorColumns' ).fire();
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
	if ( mw.editcheck.suggestionsModeAvailable ) {
		// Suggestions override user checks so assume something can be shown
		return true;
	}
	return [ 'onBeforeSave', 'onDocumentChange' ].some(
		( listener ) => mw.editcheck.editCheckFactory.getNamesByListener( listener ).some(
			( checkName ) => {
				const check = mw.editcheck.editCheckFactory.create( checkName, this );
				try {
					return check.canBeShown( this.surface.getModel().getDocument() );
				} catch ( e ) {
					mw.log.error( `Error checking canBeShown for ${ checkName }`, e );
					return false;
				}
			}
		)
	);
};

/**
 * Update position of edit check highlights
 *
 * @fires EditCheckController#position
 */
Controller.prototype.updatePositions = function () {
	this.drawSelections();

	this.emit( 'position' );
};

/**
 * Update edit check list
 *
 * @fires EditCheckController#actionsUpdated
 * @param {boolean} useCache Whether to piggyback onto an existing refresh if one is ongoing
 * @return {Promise<mw.editcheck.EditCheckAction[]>} An updated set of
 *  actions. This promise will resolve *after* any actionsUpdated events are
 *  fired.
 */
Controller.prototype.refresh = function ( useCache ) {
	if ( this.refreshDeferred && useCache ) {
		return this.refreshDeferred.promise();
	}
	const deferred = ve.createDeferred();
	deferred.always( () => {
		if ( this.refreshDeferred === deferred ) {
			this.refreshDeferred = null;
		}
	} );
	this.refreshDeferred = deferred;
	if ( this.target.deactivating || !this.target.active ) {
		return deferred.reject().promise();
	}
	if ( this.inBeforeSave ) {
		// These shouldn't be recalculated
		const actions = this.getActions();
		this.emit( 'actionsUpdated', 'onBeforeSave', actions, [], [], false );
		return deferred.resolve( actions ).promise();
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
		process.execute().always( () => {
			deferred.resolve( this.getActions() );
		} );
		return deferred.promise();
	}
};

/**
 * Toggle whether suggestions are shown to the user.
 */
Controller.prototype.toggleSuggestionsVisible = function () {
	if ( !this.suggestionsModeAvailable ) {
		return;
	}
	this.suggestionsVisible = !this.suggestionsVisible;
	if ( !!ve.userConfig( 'visualeditor-editcheck-suggestions-toggle' ) !== this.suggestionsVisible ) {
		ve.userConfig( 'visualeditor-editcheck-suggestions-toggle', this.suggestionsVisible );
	}
	mw.notify(
		ve.msg( this.suggestionsVisible ? 'editcheck-suggestions-turned-on' : 'editcheck-suggestions-turned-off' ),
		{ tag: 'editcheck-suggestions-toggle', type: 'notice' }
	);

	this.actionsByListener = {};
	// Treat this refresh as being as if we were in initial setup -- we don't
	// want the "new" suggestions to be focused.
	this.inSetup = true;
	this.refresh().always( () => {
		this.inSetup = null;
	} );
};

/**
 * Suppress suggestions without affecting user preferences
 *
 * Suggestions will still continue to be generated and cached, just not displayed.
 * For use by external tools.
 *
 * @param {boolean} suppress if true, does not display any suggestions
 */
Controller.prototype.suppressSuggestionDisplay = function ( suppress ) {
	if ( this.suppressSuggestions === suppress ) {
		return;
	}
	this.suppressSuggestions = suppress;
	this.refresh();
};

/**
 * Update the suggestion count shown on the toolbar tool
 *
 * @param {number} count The number of suggestions
 */
Controller.prototype.updateSuggestionCount = function ( count ) {
	const suggestionsModeTool = this.target.getToolbar().tools.editCheckSuggestions;
	if ( suggestionsModeTool ) {
		suggestionsModeTool.$icon.attr(
			'data-count',
			ve.msg( 'editcheck-toolbar-suggestions-count', Math.min( 100, count ) )
		);
	}
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
 * @fires EditCheckController#actionsUpdated
 * @fires EditCheckController#actionsUpdatedProgress
 */
Controller.prototype.updateForListener = function ( listener, fromRefresh ) {
	if ( this.surface.getModel().isStaging() ) {
		return Promise.resolve( this.getActions( listener ) );
	}
	const onProgress = ( action ) => {
		const existing = this.getActions( listener );
		const oldAction = existing.find( ( existingAction ) => action.equals( existingAction ) );
		if ( oldAction && !( oldAction.isSuggestion() && !action.isSuggestion() ) ) {
			// Let a new non-suggestion take over from an old suggestion
			action = oldAction;
		}
		this.emit( 'actionsUpdatedProgress', listener, action, oldAction );
	};
	let actionsPromise;
	// Create all actions for this listener
	if ( this.suggestionsModeAvailable && !this.inBeforeSave ) {
		// eslint-disable-next-line no-jquery/no-when
		actionsPromise = $.when(
			mw.editcheck.editCheckFactory.createAllActionsByListener( this, listener, this.surface.getModel(), true, onProgress ),
			mw.editcheck.editCheckFactory.createAllActionsByListener( this, listener, this.surface.getModel(), false, onProgress )
		).then( ( suggestionActions, checkActions ) => [
			...checkActions,
			// Discard any suggestions that have an equivalent non-suggestion
			...suggestionActions.filter( ( suggestion ) => !checkActions.find( ( action ) => action.equals( suggestion, true ) ) )
		] );
	} else {
		actionsPromise = mw.editcheck.editCheckFactory.createAllActionsByListener( this, listener, this.surface.getModel(), false, onProgress );
	}
	return actionsPromise
		.then( ( actionsFromListener ) => {
			// Get the existing actions for this listener
			const existing = this.getActions( listener );

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
						action.updateStale( false );
						staleUpdated = true;
					}
				} );
			}

			// Update the actions for this listener
			this.actionsByListener[ listener ] = actions;

			let newActions = actions.filter( ( action ) => existing.every( ( oldAction ) => !action.equals( oldAction ) ) );
			const discardedActions = existing.filter( ( action ) => actions.every( ( newAction ) => !action.equals( newAction ) ) );

			newActions.forEach( ( action ) => {
				action.once( 'shown', this.onActionShown.bind( this, action ) );
				action.once( 'seen', this.onActionSeen.bind( this, action ) );
				action.on( 'act', this.onActionAct, [ action ], this );
			} );

			// If the actions list changed, update
			if ( fromRefresh || staleUpdated || actions.length !== existing.length || newActions.length || discardedActions.length ) {
				if ( this.inSetup ) {
					// Any actions that are present during initial setup
					// shouldn't be treated as being "new". They're either
					// restored from a saved session, or are suggestions, and
					// in either case we don't want them treated as if the
					// user just caused them.
					newActions = [];
				}

				if ( this.suppressSuggestions ) {
					newActions = newActions.filter( ( action ) => !action.isSuggestion() );
				}
				// TODO: We need to consider a consistency check here as the document state may have changed since the
				// action within the promise was created
				// Notify listeners that actions have been updated
				this.emit( 'actionsUpdated', listener, this.getActions(), newActions, discardedActions, false );
			}
			// Return the updated actions
			return actions;
		} ).catch( ( error ) => {
			mw.log.error( 'Could not update for listener: ' + listener, error );
			return [];
		} );
};

/**
 * Filter actions for display based on current settings
 *
 * @param {mw.editcheck.EditCheckAction[]} actions Actions to filter
 * @return {mw.editcheck.EditCheckAction[]} Filtered actions
 */
Controller.prototype.filterActionsForDisplay = function ( actions ) {
	if ( !this.suggestionsVisible || this.suppressSuggestions ) {
		return actions.filter( ( action ) => !action.isSuggestion() );
	}
	return actions;
};

/**
 * Remove an edit check action
 *
 * @param {string} listener Listener which triggered the action
 * @param {mw.editcheck.EditCheckAction} action Action to remove
 * @param {boolean} rejected The action was rejected
 * @fires EditCheckController#actionsUpdated
 */
Controller.prototype.removeAction = function ( listener, action, rejected ) {
	const actions = this.actionsByListener[ listener ];
	if ( !actions || actions.length === 0 ) {
		return;
	}
	const index = actions.indexOf( action );
	if ( index === -1 ) {
		return;
	}
	const removed = actions.splice( index, 1 );

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
 * @fires EditCheckController#focusAction
 * @fires EditCheckController#position
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
 * Make sure an action is visible to the user
 *
 * This will scroll the action into view and make sure its widget is expanded
 * so the contents can be seen.
 *
 * @param {mw.editcheck.EditCheckAction} action Action to focus
 * @param {boolean} [alignToTop] Align selection to top of page when scrolling
 */
Controller.prototype.ensureActionIsShown = function ( action, alignToTop ) {
	if ( OO.ui.isMobile() ) {
		const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
		if ( !currentWindow || currentWindow.constructor.static.name !== 'gutterSidebarEditCheckDialog' ) {
			return;
		}
		// This will ultimately focus the action and scroll it into view as well:
		currentWindow.showDialogWithAction( action, true );
	} else {
		this.focusAction( action, true, alignToTop );
	}
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
		let lsActions = this.actionsByListener[ listener ] || [];
		if ( this.suppressSuggestions ) {
			lsActions = lsActions.filter( ( action ) => !action.isSuggestion() );
		}
		return lsActions;
	}
	const listeners = this.inBeforeSave ? [ 'onBeforeSave' ] : midEditListeners;
	let actions = [].concat( ...listeners.map( ( lr ) => this.actionsByListener[ lr ] || [] ) );
	if ( this.suppressSuggestions ) {
		actions = actions.filter( ( action ) => !action.isSuggestion() );
	}
	actions.sort( mw.editcheck.EditCheckAction.static.compareStarts );
	return actions;
};

/**
 * Handle focus events from the surface view
 */
Controller.prototype.onSurfaceFocus = function () {
	// On mobile we want to close the drawer if the keyboard is shown
	// A native cursor selection means the keyboard will be visible
	if ( OO.ui.isMobile() && !this.inBeforeSave && this.target.isVirtualKeyboardOpen() ) {
		this.closeDialog( 'mobile-keyboard' );
	}
};

/**
 * Handle select events from the surface model
 *
 * @param {ve.dm.Selection} selection New selection
 */
Controller.prototype.onSelect = function () {
	if ( this.ignoreNextSelectionChange ) {
		this.ignoreNextSelectionChange = null;
		return;
	}
	if ( !OO.ui.isMobile() ) {
		this.focusActionForSelection();
	}
};

/**
 * Update actions based on the current selection
 *
 * @fires EditCheckController#actionsUpdated
 * @fires EditCheckController#focusAction
 */
Controller.prototype.focusActionForSelection = function () {
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

	const actions = this.getActions();

	if ( actions.length === 0 || selection.isNull() ) {
		// Nothing to do
		return;
	}

	// First check if the selection matches any action's #getFocusSelection as this
	// is more specific than highlights.
	const focusSelectionActions = actions.filter(
		( action ) => action.getFocusSelection().getCoveringRange().containsRange( selection.getCoveringRange() )
	);
	if ( focusSelectionActions.length > 0 ) {
		// Focus the last action returned, because it should be the most-specific
		this.focusAction( focusSelectionActions[ focusSelectionActions.length - 1 ], false );
		return;
	}

	const highlightSelectionsActions = actions.filter(
		( action ) => action.getHighlightSelections().some(
			( highlightSelection ) => highlightSelection.getCoveringRange().containsRange( selection.getCoveringRange() ) ) );

	if ( highlightSelectionsActions.length > 0 ) {
		this.focusAction( highlightSelectionsActions[ highlightSelectionsActions.length - 1 ], false );
		return;
	}
};

/**
 * Whether to ignore the next select event that is received
 *
 * @param {boolean} [ignore=true]
 */
Controller.prototype.setIgnoreNextSelectionChange = function ( ignore = true ) {
	this.ignoreNextSelectionChange = ignore;
};

/**
 * Handle contextChange events from the surface model
 */
Controller.prototype.onContextChange = function () {
	if ( OO.ui.isMobile() && this.surface.getContext().isVisible() ) {
		if ( !this.inBeforeSave ) {
			// The context overlaps the drawer on mobile, so we should get rid of the drawer
			this.closeDialog( 'context' );
		} else {
			// We still want to hide the context, just not close the dialog
			this.surface.getModel().setNullSelection();
		}
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
		const historyPointer = this.surface.getModel().getDocument().getCompleteHistoryLength();
		if ( this.lastBranchNodeChangeHistoryPointer === historyPointer ) {
			return;
		}
		this.updateForListener( 'onBranchNodeChange' ).then( () => {
			if ( this.surface ) {
				this.lastBranchNodeChangeHistoryPointer = historyPointer;
			}
		} );
	}
};

/**
 * Handler when 'actionsUpdated' fires.
 *
 * Updates gutter and highlights when the action list has changed.
 * Displays the edit check dialog if it is not already on screen.
 *
 * @listens EditCheckController#actionsUpdated
 * @param {string} listener e.g. onBeforeSave, onDocumentChange, onBranchNodeChange
 * @param {mw.editcheck.EditCheckAction[]} actions
 * @param {mw.editcheck.EditCheckAction[]} newActions
 * @param {mw.editcheck.EditCheckAction[]} discardedActions
 */
Controller.prototype.onActionsUpdated = function ( listener, actions, newActions, discardedActions ) {
	// Do we need to redraw anything?
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

	// Do we need to show mid-edit actions?
	if ( listener === 'onBeforeSave' ) {
		return;
	}
	const suggestionRanges = actions.filter( ( action ) => action.isSuggestion() ).map( ( action ) => action.getFocusSelection().getCoveringRange() );
	const suggestionCount = suggestionRanges.length;
	let availableSuggestionCount = suggestionCount;
	const target = this.target;
	if ( target.enableVisualSectionEditing && target.section !== null ) {
		if ( !this.editFullPageIndicatorTop ) {
			this.editFullPageIndicatorTop = new OO.ui.IconWidget( {
				icon: 'lightbulb',
				classes: [ 've-ui-editCheck-editFullPage-indicator' ]
			} );
			this.editFullPageIndicatorBottom = new OO.ui.IconWidget( {
				icon: 'lightbulb',
				classes: [ 've-ui-editCheck-editFullPage-indicator' ]
			} );
			target.switchToFullPageButtonTop.$label.append( this.editFullPageIndicatorTop.$element );
			target.switchToFullPageButtonBottom.$label.append( this.editFullPageIndicatorBottom.$element );
		}
		const attachedRootRange = this.surface.getModel().getDocument().getAttachedRoot().getOuterRange();
		availableSuggestionCount = suggestionRanges.filter( ( range ) => attachedRootRange.containsRange( range ) ).length;
		const hasActionsAbove = suggestionRanges.some( ( range ) => range.end < attachedRootRange.start );
		const hasActionsBelow = suggestionRanges.some( ( range ) => range.start > attachedRootRange.end );
		this.editFullPageIndicatorTop.toggle( hasActionsAbove );
		this.editFullPageIndicatorBottom.toggle( hasActionsBelow );
	}

	// Ignore a count of 0 during initial setup
	if ( !( this.inSetup && suggestionCount === 0 ) ) {
		this.updateSuggestionCountDebounced( suggestionCount );
	}

	if ( this.suggestionsVisible && !this.suppressSuggestions ) {
		// Notify once when the user has completed/declined all suggestions.
		if ( this.lastAvailableSuggestionCount > 0 && availableSuggestionCount === 0 ) {
			mw.notify( ve.msg( 'editcheck-suggestions-none-left' ), {
				tag: 'editcheck-suggestions-none-left',
				type: 'notice'
			} );
		}
		// After switching to full-page, notify if more suggestions become available.
		if ( this.notifySwitchedToFullPage ) {
			if ( availableSuggestionCount > this.lastAvailableSuggestionCount ) {
				mw.notify( ve.msg( 'editcheck-suggestions-more-available' ), {
					tag: 'editcheck-suggestions-more-available',
					type: 'notice'
				} );
			}
			this.notifySwitchedToFullPage = false;
		}
	}

	this.lastAvailableSuggestionCount = availableSuggestionCount;

	const visibleActions = this.filterActionsForDisplay( actions );
	const visibleNewActions = this.filterActionsForDisplay( newActions );

	if ( !visibleActions.length ) {
		return;
	}
	const windowName = OO.ui.isMobile() ? 'gutterSidebarEditCheckDialog' : 'sidebarEditCheckDialog';
	let shownPromise;
	const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
	if ( !currentWindow || currentWindow.constructor.static.name !== windowName ) {
		target.$element.addClass( 've-ui-editCheck-sidebar-active' );
		const windowAction = ve.ui.actionFactory.create( 'window', this.surface, 'check' );
		shownPromise = windowAction.open(
			windowName,
			{ inBeforeSave: this.inBeforeSave, visibleActions, visibleNewActions, controller: this }
		).then( ( instance ) => {
			ve.track( 'activity.editCheckDialog', { action: 'window-open-from-check-midedit' } );
			instance.closed.then( () => {
				target.$element.removeClass( 've-ui-editCheck-sidebar-active' );
			} );
		} );
	} else {
		shownPromise = ve.createDeferred().resolve().promise();
	}
	shownPromise.then( () => {
		if ( visibleNewActions.length ) {
			// Check if any new actions are relevant to our current selection:
			this.focusActionForSelection();
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
					return windowAction.open( 'fixedEditCheckDialog', { inBeforeSave: true, actions, controller: this } )
						.then( ( instance ) => {
							ve.track( 'activity.editCheckDialog', { action: 'window-open-from-check-presave' } );
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
	const actions = this.filterActionsForDisplay( this.getActions() );
	const surfaceView = this.surface.getView();
	const activeSelections = this.focusedAction ? this.focusedAction.getHighlightSelections().map(
		( selection ) => ve.ce.Selection.static.newFromModel( selection, surfaceView )
	) : [];
	if ( this.focusedAction ) {
		this.focusedAction.updateStale();
	}
	const isStale = !!this.focusedAction && this.focusedAction.isStale();
	const showGutter = !isStale && !OO.ui.isMobile();
	const activeOptions = { showGutter, showRects: !isStale, showBounding: isStale };

	if ( this.inBeforeSave ) {
		// Review mode grays out everything that's not highlighted:
		const highlightNodes = [];
		actions.forEach( ( action ) => {
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

	if ( actions.length === 0 ) {
		// Clear any previously drawn selections
		surfaceView.getSelectionManager().drawSelections( 'editCheck-active', [] );
		surfaceView.getSelectionManager().drawSelections( 'editCheck-inactive', [] );
		return;
	}
	const inactiveOptions = { showGutter, showRects: true };

	const inactiveSelections = [];
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

	if ( isStale && activeSelections.length ) {
		// When in reviewing a check (stale), suppress all inactive selections that overlap with the active selection (T420712).
		const activeRange = activeSelections[ 0 ].getModel().getCoveringRange();
		for ( let i = inactiveSelections.length - 1; i >= 0; i-- ) {
			if ( activeRange.overlapsRange( inactiveSelections[ i ].getModel().getCoveringRange() ) ) {
				inactiveSelections.splice( i, 1 );
			}
		}
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
			if ( !isActive && action.widget ) {
				action.widget.setInactiveSelectionElements( selectionElements );
			}
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
		padding,
		alignToTop
	} );
};

/**
 * Closes the edit check dialog
 *
 * @param {string} [action] Name of action which triggered the close ('mobile-keyboard', 'context', 'preSaveProcess')
 * @return {jQuery.Promise}
 */
Controller.prototype.closeDialog = function ( action ) {
	const currentWindow = this.surface.getToolbarDialogs( ve.ui.FixedEditCheckDialog.static.position ).getCurrentWindow();
	if ( currentWindow && currentWindow.constructor.static.name === 'fixedEditCheckDialog' ) {
		// .always is not chainable
		return currentWindow.close( action ? { action } : undefined ).closed.then( () => {}, () => {} );
	}
	return ve.createDeferred().resolve().promise();
};

/**
 * Closes the sidebar edit check dialogs (mid-edit).
 *
 * @param {string} [action] Name of action which triggered the close (currently only 'preSaveProcess')
 * @return {jQuery.Promise}
 */
Controller.prototype.closeSidebars = function ( action ) {
	const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
	if ( currentWindow && currentWindow.constructor.static.name === 'sidebarEditCheckDialog' ) {
		// .always is not chainable
		return currentWindow.close( action ? { action } : undefined ).closed.then( () => {}, () => {} );
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

/**
 * Handle instrumentation and tracking when an action is shown
 *
 * @param {mw.editcheck.EditCheckAction} action that was shown
 */
Controller.prototype.onActionShown = function ( action ) {
	const moment = this.inBeforeSave ? 'presave' : 'midedit';
	if ( action.isSuggestion() ) {
		ve.track( 'activity.editCheck-' + action.getName(), { action: 'suggestion-shown-' + moment } );
	} else {
		mw.editcheck.checksShown[ action.getName() ] = true;
		ve.track( 'activity.editCheck-' + action.getName(), { action: 'check-shown-' + moment } );
	}
};
/**
 * Handle instrumentation and tracking when an action is marked as seen
 *
 * @param {mw.editcheck.EditCheckAction} action that was seen
 */
Controller.prototype.onActionSeen = function ( action ) {
	const moment = this.inBeforeSave ? 'presave' : 'midedit';
	if ( action.isSuggestion() ) {
		mw.editcheck.suggestionsSeen[ action.getName() ] = true;
		ve.track( 'activity.editCheck-' + action.getName(), { action: 'suggestion-seen-' + moment } );
	} else {
		mw.editcheck.checksSeen[ action.getName() ] = true;
		ve.track( 'activity.editCheck-' + action.getName(), { action: 'check-seen-' + moment } );
	}
};

/**
 * Handle instrumentation and tracking when an action is used
 *
 * @param {mw.editcheck.EditCheckAction} action that was used
 * @param {Promise|jQuery.Promise} promise that will resolve when the action finishes
 * @param {string} actionTaken name of the action taken
 */
Controller.prototype.onActionAct = function ( action, promise, actionTaken ) {
	ve.track( 'activity.editCheck-' + action.getName(), {
		action: ( action.isSuggestion() ? 'suggestion-' : '' ) + 'action-' + ( actionTaken || 'unknown' )
	} );
	const dismissalActions = [ 'dismiss', 'reject', 'keep' ];
	if ( dismissalActions.includes( actionTaken ) ) {
		// These are actions that represent "don't change anything", and so
		// don't count as the check having been used
		return;
	}
	if ( action.isSuggestion() ) {
		mw.editcheck.suggestionsUsed[ action.getName() ] = true;
	} else {
		mw.editcheck.checksUsed[ action.getName() ] = true;
	}
};

module.exports = {
	Controller
};
