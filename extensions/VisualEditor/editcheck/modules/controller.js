'use strict';

function Controller( target ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	this.actionsByListener = {};

	this.target = target;

	this.surface = null;
	this.listener = 'onDocumentChange';

	this.$highlights = $( '<div>' );

	this.dismissedFragments = {};
	this.dismissedIds = {};

	this.onDocumentChangeDebounced = ve.debounce( this.onDocumentChange.bind( this ), 100 );
	this.onPositionDebounced = ve.debounce( this.onPosition.bind( this ), 100 );
	this.onSelectDebounced = ve.debounce( this.onSelect.bind( this ), 100 );
	this.onContextChangeDebounced = ve.debounce( this.onContextChange.bind( this ), 100 );

	// Don't run a scroll if the previous animation is still running (which is jQuery 'fast' === 200ms)
	this.scrollActionIntoViewDebounced = ve.debounce( this.scrollActionIntoView.bind( this ), 200, true );
}

OO.mixinClass( Controller, OO.EventEmitter );

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
		// ideally this would happen slightly earlier:
		document.documentElement.classList.add( 've-editcheck-available' );

		this.surface.getView().on( 'position', this.onPositionDebounced );
		this.surface.getModel().on( 'undoStackChange', this.onDocumentChangeDebounced );
		this.surface.getModel().on( 'select', this.onSelectDebounced );
		this.surface.getModel().on( 'contextChange', this.onContextChangeDebounced );

		this.on( 'actionsUpdated', this.onActionsUpdated, null, this );

		// Run on load (e.g. recovering from auto-save)
		setTimeout( () => this.onDocumentChange(), 100 );

		this.surface.on( 'destroy', () => {
			this.off( 'actionsUpdated' );
			this.$highlights.empty();

			this.surface = null;
			this.actionsByListener = {};

			this.dismissedFragments = {};
			this.dismissedIds = {};

			mw.editcheck.refCheckShown = false;
		} );
	}, null, this );

	target.on( 'teardown', () => {
		document.documentElement.classList.remove( 've-editcheck-available' );
	}, null, this );

	this.setupPreSaveProcess();
};

Controller.prototype.editChecksArePossible = function () {
	return [ 'onBeforeSave', 'onDocumentChange' ].some(
		( listener ) => mw.editcheck.editCheckFactory.getNamesByListener( listener ).some(
			( checkName ) => {
				const check = mw.editcheck.editCheckFactory.create( checkName, this, mw.editcheck.config[ checkName ] );
				return check.canBeShown();
			}
		)
	);
};

Controller.prototype.updatePositions = function () {
	this.drawSelections();
	this.drawGutter();

	this.emit( 'position' );
};

Controller.prototype.refresh = function () {
	if ( this.target.deactivating || !this.target.active ) {
		return;
	}
	if ( this.listener === 'onBeforeSave' ) {
		// These shouldn't be recalculated
		this.emit( 'actionsUpdated', this.listener, this.getActions( this.listener ), [], [], false );
	} else {
		this.updateForListener( this.listener, true );
	}
};

Controller.prototype.updateForListener = function ( listener, always ) {
	listener = listener || this.listener;
	const existing = this.actionsByListener[ listener ] || [];
	const actions = mw.editcheck.editCheckFactory.createAllByListener( this, listener, this.surface.getModel() )
		.map( ( action ) => existing.find( ( oldAction ) => oldAction.equals( action ) ) || action );

	this.actionsByListener[ listener ] = actions;

	const newActions = actions.filter( ( action ) => existing.every( ( oldAction ) => !action.equals( oldAction ) ) );
	const discardedActions = existing.filter( ( action ) => actions.every( ( newAction ) => !action.equals( newAction ) ) );
	if ( always || actions.length !== existing.length || newActions.length || discardedActions.length ) {
		this.emit( 'actionsUpdated', listener, actions, newActions, discardedActions, false );
	}
	return actions;
};

Controller.prototype.removeAction = function ( listener, action, rejected ) {
	const actions = this.getActions( listener );
	const index = actions.indexOf( action );
	if ( index === -1 ) {
		return;
	}
	const removed = actions.splice( index, 1 );

	if ( action === this.focused ) {
		this.focused = undefined;
	}

	this.emit( 'actionsUpdated', listener, actions, [], removed, rejected );
};

Controller.prototype.focusAction = function ( action, scrollTo ) {
	if ( !scrollTo && action === this.focused ) {
		// Don't emit unnecessary events if there is no change or scroll
		return;
	}

	this.focused = action;

	this.emit( 'focusAction', action, this.getActions().indexOf( action ), scrollTo );

	this.updatePositions();
};

Controller.prototype.getActions = function ( listener ) {
	return this.actionsByListener[ listener || this.listener ] || [];
};

Controller.prototype.onSelect = function ( selection ) {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}
	if ( this.surface.getView().reviewMode ) {
		// In review mode the selection and display of checks is being managed by the dialog
		return;
	}
	if ( OO.ui.isMobile() ) {
		// On mobile we want to close the drawer if the keyboard is shown
		if ( this.surface.getView().hasNativeCursorSelection() ) {
			// A native cursor selection means the keyboard will be visible
			this.closeDialog( 'mobile-keyboard' );
		}
	}
	if ( this.getActions().length === 0 || selection.isNull() ) {
		// Nothing to do
		return;
	}
	const actions = this.getActions().filter(
		( check ) => check.getHighlightSelections().some(
			( highlight ) => highlight.getCoveringRange().containsRange( selection.getCoveringRange() ) ) );

	this.focusAction( actions[ 0 ] || null, false );
};

Controller.prototype.onContextChange = function () {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}
	if ( OO.ui.isMobile() && this.surface.getContext().isVisible() ) {
		// The context overlaps the drawer on mobile, so we should get rid of the drawer
		this.closeDialog( 'context' );
	}
};

Controller.prototype.onPosition = function () {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}

	this.updatePositions();

	if ( this.getActions().length && this.focused && this.surface.getView().reviewMode ) {
		this.scrollActionIntoViewDebounced( this.focused );
	}
};

Controller.prototype.onDocumentChange = function () {
	if ( !this.surface ) {
		// This is debounced, and could potentially be called after teardown
		return;
	}
	if ( this.listener !== 'onBeforeSave' ) {
		this.updateForListener( 'onDocumentChange' );
	}

	this.updatePositions();
};

Controller.prototype.onActionsUpdated = function ( listener, actions, newActions, discardedActions ) {
	// do we need to redraw anything?
	if ( newActions.length || discardedActions.length ) {
		if ( this.focused && discardedActions.includes( this.focused ) ) {
			this.focused = undefined;
		}
		this.updatePositions();
	}

	// do we need to show mid-edit actions?
	if ( listener !== 'onDocumentChange' ) {
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
			{ listener: listener, actions: actions, controller: this }
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
		newActions.forEach( ( action ) => {
			ve.track( 'activity.editCheck-' + action.getName(), { action: 'check-shown-midedit' } );
		} );
	} );
};

Controller.prototype.setupPreSaveProcess = function () {
	const target = this.target;
	const preSaveProcess = target.getPreSaveProcess();
	preSaveProcess.next( () => {
		const surface = target.getSurface();
		if ( surface.getMode() !== 'visual' ) {
			return;
		}
		ve.track( 'counter.editcheck.preSaveChecksAvailable' );

		const oldFocused = this.focused;
		this.listener = 'onBeforeSave';
		const actions = this.updateForListener( 'onBeforeSave' );
		if ( actions.length ) {
			ve.track( 'counter.editcheck.preSaveChecksShown' );
			mw.editcheck.refCheckShown = mw.editcheck.refCheckShown ||
				actions.some( ( action ) => action.getName() === 'addReference' );

			this.setupToolbar( target );

			let $contextContainer, contextPadding;
			if ( surface.context.popup ) {
				contextPadding = surface.context.popup.containerPadding;
				$contextContainer = surface.context.popup.$container;
				surface.context.popup.$container = surface.$element;
				surface.context.popup.containerPadding = 20;
			}

			return this.closeSidebars( 'preSaveProcess' ).then( () => this.closeDialog( 'preSaveProcess' ).then( () => {
				this.originalToolbar.toggle( false );
				target.onContainerScroll();
				const windowAction = ve.ui.actionFactory.create( 'window', surface, 'check' );
				return windowAction.open( 'fixedEditCheckDialog', { listener: 'onBeforeSave', actions: actions, controller: this } )
					.then( ( instance ) => {
						ve.track( 'activity.editCheckDialog', { action: 'window-open-from-check-presave' } );
						actions.forEach( ( action ) => {
							ve.track( 'activity.editCheck-' + action.getName(), { action: 'check-shown-presave' } );
						} );
						instance.closed.then( () => {}, () => {} ).then( () => {
							surface.getView().setReviewMode( false );
							this.listener = 'onDocumentChange';
							this.focused = oldFocused;
							// Re-open the mid-edit sidebar if necessary.
							this.refresh();
						} );
						return instance.closing.then( ( data ) => {
							if ( target.deactivating || !target.active ) {
								// Someone clicking "read" to leave the article
								// will trigger the closing of this; in that
								// case, just abandon what we're doing
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
									ve.track( 'counter.editcheck.preSaveChecksCompleted' );
									delay.resolve();
								}, !OO.ui.isMobile() && data.action !== 'reject' ? 2000 : 0 );
								return delay.promise();
							} else {
								// closed via "back" or otherwise
								ve.track( 'counter.editcheck.preSaveChecksAbandoned' );
								return ve.createDeferred().reject().promise();
							}
						} );
					} );
			} ) );
		} else {
			this.listener = 'onDocumentChange';
			// Counterpart to earlier preSaveChecksShown, for use in tracking
			// errors in check-generation:
			ve.track( 'counter.editcheck.preSaveChecksNotShown' );
		}
	} );
};

Controller.prototype.setupToolbar = function ( target ) {
	const surface = target.getSurface();
	const toolbar = target.getToolbar();
	const reviewToolbar = new ve.ui.PositionedTargetToolbar( target, target.toolbarConfig );
	reviewToolbar.setup( [
		{
			name: 'back',
			type: 'bar',
			include: [ 'editCheckBack' ]
		},
		// Placeholder toolbar groups
		// TODO: Make a proper TitleTool?
		{
			name: 'title',
			type: 'bar',
			include: []
		},
		{
			name: 'save',
			// TODO: MobileArticleTarget should ignore 'align'
			align: OO.ui.isMobile() ? 'before' : 'after',
			type: 'bar',
			include: [ 'showSaveDisabled' ]
		}
	], surface );
	reviewToolbar.$element.addClass( 've-ui-editCheck-toolbar' );

	reviewToolbar.items[ 1 ].$element.removeClass( 'oo-ui-toolGroup-empty' );
	reviewToolbar.items[ 1 ].$group.append(
		$( '<span>' ).addClass( 've-ui-editCheck-toolbar-title' ).text( ve.msg( 'editcheck-dialog-title' ) )
	);
	if ( OO.ui.isMobile() ) {
		reviewToolbar.$element.addClass( 've-init-mw-mobileArticleTarget-toolbar' );
	}
	target.toolbar.$element.before( reviewToolbar.$element );
	target.toolbar = reviewToolbar;

	reviewToolbar.initialize();

	this.originalToolbar = toolbar;
	this.reviewToolbar = reviewToolbar;
};

Controller.prototype.restoreToolbar = function ( target ) {
	if ( !this.reviewToolbar ) {
		return;
	}
	this.reviewToolbar.$element.remove();
	this.originalToolbar.toggle( true );
	target.toolbar = this.originalToolbar;

	// Creating a new PositionedTargetToolbar stole the
	// toolbar windowmanagers, so we need to make the
	// original toolbar reclaims them:
	target.setupToolbar( target.getSurface() );
	// If the window was resized while the originalToolbar was hidden then
	// the cached measurements will be wrong. Recalculate.
	this.originalToolbar.onWindowResize();

	this.reviewToolbar = null;
	this.originalToolbar = null;
};

Controller.prototype.drawSelections = function () {
	const surfaceView = this.surface.getView();
	if ( this.focused ) {
		// The currently-focused check gets a selection:
		// TODO: clicking the selection should activate the sidebar-action
		surfaceView.getSelectionManager().drawSelections(
			'editCheckWarning',
			this.focused.getHighlightSelections().map(
				( selection ) => ve.ce.Selection.static.newFromModel( selection, surfaceView )
			)
		);
	} else {
		surfaceView.getSelectionManager().drawSelections( 'editCheckWarning', [] );
	}

	if ( this.listener === 'onBeforeSave' ) {
		// Review mode grays out everything that's not highlighted:
		const highlightNodes = [];
		this.getActions().forEach( ( action ) => {
			action.getHighlightSelections().forEach( ( selection ) => {
				highlightNodes.push.apply( highlightNodes, surfaceView.getDocument().selectNodes( selection.getCoveringRange(), 'branches' ).map( ( spec ) => spec.node ) );
			} );
		} );
		surfaceView.setReviewMode( true, highlightNodes );
	}
};

Controller.prototype.drawGutter = function () {
	if ( OO.ui.isMobile() ) {
		return;
	}
	this.$highlights.empty();
	const actions = this.getActions();
	if ( actions.length === 0 ) {
		return;
	}
	const surfaceView = this.surface.getView();

	actions.forEach( ( action ) => {
		action.top = Infinity;
		action.getHighlightSelections().forEach( ( selection ) => {
			const selectionView = ve.ce.Selection.static.newFromModel( selection, surfaceView );
			const rect = selectionView.getSelectionBoundingRect();
			if ( !rect ) {
				return;
			}
			// The following classes are used here:
			// * ve-ui-editCheck-gutter-highlight-error
			// * ve-ui-editCheck-gutter-highlight-warning
			// * ve-ui-editCheck-gutter-highlight-notice
			// * ve-ui-editCheck-gutter-highlight-success
			// * ve-ui-editCheck-gutter-highlight-active
			// * ve-ui-editCheck-gutter-highlight-inactive
			this.$highlights.append( $( '<div>' )
				.addClass( 've-ui-editCheck-gutter-highlight' )
				.addClass( 've-ui-editCheck-gutter-highlight-' + action.getType() )
				.addClass( 've-ui-editCheck-gutter-highlight-' + ( action === this.focused ? 'active' : 'inactive' ) )
				.css( {
					top: rect.top - 2,
					height: rect.height + 4
				} )
				.on( 'click', () => this.focusAction( action ) )
			);
			action.top = Math.min( action.top, rect.top );
		} );
	} );

	surfaceView.appendHighlights( this.$highlights, false );
};

Controller.prototype.scrollActionIntoView = function ( action ) {
	// scrollSelectionIntoView scrolls to the focus of a selection, but we
	// want the very beginning to be in view, so collapse it:
	const selection = action.getHighlightSelections()[ 0 ].collapseToStart();
	const padding = {
		top: OO.ui.isMobile() ? 80 : action.widget.$element[ 0 ].getBoundingClientRect().top,
		bottom: 20
	};
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
		alignToTop: true
	} );
};

Controller.prototype.closeDialog = function ( action ) {
	this.focusAction( undefined );
	const windowAction = ve.ui.actionFactory.create( 'window', this.surface, 'check' );
	return windowAction.close( 'fixedEditCheckDialog', action ? { action: action } : undefined ).closed.then( () => {}, () => {} );
};

Controller.prototype.closeSidebars = function ( action ) {
	const currentWindow = this.surface.getSidebarDialogs().getCurrentWindow();
	if ( currentWindow ) {
		// .always is not chainable
		return currentWindow.close( action ? { action: action } : undefined ).closed.then( () => {}, () => {} );
	}
	return ve.createDeferred().resolve().promise();
};

module.exports = {
	Controller: Controller
};
