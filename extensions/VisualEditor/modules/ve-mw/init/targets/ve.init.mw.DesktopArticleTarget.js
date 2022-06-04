/*!
 * VisualEditor MediaWiki Initialization DesktopArticleTarget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */

/**
 * MediaWiki desktop article target.
 *
 * @class
 * @extends ve.init.mw.ArticleTarget
 *
 * @constructor
 * @param {Object} config Configuration options
 */
ve.init.mw.DesktopArticleTarget = function VeInitMwDesktopArticleTarget( config ) {
	// Parent constructor
	ve.init.mw.DesktopArticleTarget.super.call( this, config );

	// Parent constructor bound key event handlers, but we don't want them bound until
	// we activate; so unbind them again
	this.unbindHandlers();

	this.onWatchToggleHandler = this.onWatchToggle.bind( this );

	// Properties
	this.onBeforeUnloadFallback = null;
	this.onBeforeUnload = this.onBeforeUnload.bind( this );
	this.onUnloadHandler = this.onUnload.bind( this );
	this.activating = false;
	this.deactivating = false;
	this.recreating = false;
	this.activatingDeferred = null;
	this.toolbarSetupDeferred = null;
	this.suppressNormalStartupDialogs = false;
	this.editingTabDialog = null;
	this.welcomeDialog = null;
	this.welcomeDialogPromise = null;

	// If this is true then #transformPage / #restorePage will not call pushState
	// This is to avoid adding a new history entry for the url we just got from onpopstate
	// (which would mess up with the expected order of Back/Forwards browsing)
	this.actFromPopState = false;
	this.popState = {
		tag: 'visualeditor'
	};
	this.scrollTop = null;
	this.section = null;
	if ( $( '#wpSummary' ).length ) {
		this.initialEditSummary = $( '#wpSummary' ).val();
	} else {
		this.initialEditSummary = this.currentUri.query.summary;
	}
	this.initialCheckboxes = $( '.editCheckboxes input' ).toArray()
		.reduce( function ( initialCheckboxes, node ) {
			initialCheckboxes[ node.name ] = node.checked;
			return initialCheckboxes;
		}, {} );

	this.viewUri = new mw.Uri( mw.util.getUrl( this.getPageName() ) );
	this.isViewPage = (
		mw.config.get( 'wgAction' ) === 'view' &&
		this.currentUri.query.diff === undefined
	);

	this.tabLayout = mw.config.get( 'wgVisualEditorConfig' ).tabLayout;
	this.events = new ve.init.mw.ArticleTargetEvents( this );
	this.$originalContent = $( '<div>' ).addClass( 've-init-mw-desktopArticleTarget-originalContent' );
	this.$editableContent.addClass( 've-init-mw-desktopArticleTarget-editableContent' );

	// Initialization
	this.$element
		.addClass( 've-init-mw-desktopArticleTarget' )
		.append( this.$originalContent );

	if ( history.replaceState ) {
		// We replace the current state with one that's marked with our tag. This way, when users
		// use the Back button to exit the editor we can restore Read mode. This is because we want
		// to ignore foreign states in onWindowPopState. Without this, the Read state is foreign.
		// FIXME: There should be a much better solution than this.
		history.replaceState( this.popState, document.title, this.currentUri );
	}

	this.setupSkinTabs();

	window.addEventListener( 'popstate', this.onWindowPopState.bind( this ) );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.DesktopArticleTarget, ve.init.mw.ArticleTarget );

/* Static Properties */

ve.init.mw.DesktopArticleTarget.static.actionGroups = [
	{
		name: 'help',
		include: [ 'help', 'notices' ]
	},
	{
		name: 'pageMenu',
		type: 'list',
		icon: 'menu',
		indicator: null,
		title: ve.msg( 'visualeditor-pagemenu-tooltip' ),
		label: ve.msg( 'visualeditor-pagemenu-tooltip' ),
		invisibleLabel: true,
		include: [ 'meta', 'categories', 'settings', 'advancedSettings', 'languages', 'templatesUsed', 'changeDirectionality', 'findAndReplace' ]
	},
	{
		name: 'editMode',
		type: 'list',
		icon: 'edit',
		title: ve.msg( 'visualeditor-mweditmode-tooltip' ),
		label: ve.msg( 'visualeditor-mweditmode-tooltip' ),
		invisibleLabel: true,
		include: [ 'editModeVisual', 'editModeSource' ]
	},
	{
		name: 'save',
		type: 'bar',
		include: [ 'showSave' ]
	}
];

/**
 * Compatibility map used with jQuery.client to decide if a browser should
 * receive a compatibility warning. Blacklisting is handled in DesktopArticleTarget.init.
 *
 * @static
 * @property {Object}
 */
ve.init.mw.DesktopArticleTarget.static.compatibility = {
	// The key is the browser name returned by jQuery.client
	// The value is either null (match all versions) or a list of tuples
	// containing an inequality (<,>,<=,>=) and a version number
	supportedList: {
		chrome: [ [ '>=', 19 ] ],
		opera: [ [ '>=', 15 ] ],
		// All versions not in unsupportedList are fully supported:
		firefox: null,
		safari: null,
		msie: null,
		edge: null
	}
};

ve.init.mw.DesktopArticleTarget.static.platformType = 'desktop';

/* Events */

/**
 * @event deactivate
 */

/**
 * @event transformPage
 */

/**
 * @event restorePage
 */

/**
 * @event saveWorkflowBegin
 * Fired when user clicks the button to open the save dialog.
 */

/**
 * @event saveWorkflowEnd
 * Fired when user exits the save workflow
 */

/**
 * @event saveReview
 * Fired when user initiates review changes in save workflow
 */

/**
 * @event saveInitiated
 * Fired when user initiates saving of the document
 */

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.addSurface = function ( dmDoc, config ) {
	config = ve.extendObject( { $overlayContainer: $( '#content' ) }, config );
	return ve.init.mw.DesktopArticleTarget.parent.prototype.addSurface.call( this, dmDoc, config );
};

/**
 * Set the container for the target, appending the target to it
 *
 * @param {jQuery} $container
 */
ve.init.mw.DesktopArticleTarget.prototype.setContainer = function ( $container ) {
	$container.append( this.$element );
	this.$container = $container;
};

/**
 * Verify that a PopStateEvent correlates to a state we created.
 *
 * @param {Mixed} popState From PopStateEvent#state
 * @return {boolean}
 */
ve.init.mw.DesktopArticleTarget.prototype.verifyPopState = function ( popState ) {
	return popState && popState.tag === 'visualeditor';
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.setupToolbar = function ( surface ) {
	var mode = surface.getMode(),
		wasSetup = !!this.toolbar,
		target = this;

	ve.track( 'trace.setupToolbar.enter', { mode: mode } );

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.setupToolbar.call( this, surface );

	var toolbar = this.getToolbar();

	// Allow the toolbar to start floating now if necessary
	this.onContainerScroll();

	ve.track( 'trace.setupToolbar.exit', { mode: mode } );
	if ( !wasSetup ) {
		toolbar.$element
			.addClass( 've-init-mw-desktopArticleTarget-toolbar-open' );
		if ( !toolbar.isFloating() ) {
			toolbar.$element.css( 'height', '' );
		}
		this.toolbarSetupDeferred.resolve();

		this.toolbarSetupDeferred.done( function () {
			var newSurface = target.getSurface();
			// Check the surface wasn't torn down while the toolbar was animating
			if ( newSurface ) {
				ve.track( 'trace.initializeToolbar.enter', { mode: mode } );
				target.getToolbar().initialize();
				newSurface.getView().emit( 'position' );
				newSurface.getContext().updateDimensions();
				ve.track( 'trace.initializeToolbar.exit', { mode: mode } );
				ve.track( 'trace.activate.exit', { mode: mode } );
			}
		} );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.attachToolbar = function () {
	// Set edit notices, will be shown after welcome dialog.
	// Make sure notices actually exists, because this might be a mode-switch and
	// we've already removed it.
	var editNotices = this.getEditNotices(),
		actionTools = this.actionsToolbar.tools;
	if ( editNotices && editNotices.length && actionTools.notices ) {
		actionTools.notices.setNotices( editNotices );
	} else if ( actionTools.notices ) {
		actionTools.notices.destroy();
		actionTools.notices = null;
	}

	// Move the toolbar to top of target, before heading etc.
	// Avoid re-attaching as it breaks CSS animations
	if ( !this.toolbar.$element.parent().is( this.$element ) ) {
		this.toolbar.$element
			// Set 0 before attach (expanded in #setupToolbar)
			.css( 'height', '0' )
			.addClass( 've-init-mw-desktopArticleTarget-toolbar' );
		this.$element.prepend( this.toolbar.$element );

		// Calculate if the 'oo-ui-toolbar-narrow' class is needed (OOUI does it too late for our
		// toolbar because the methods are called in the wrong order, see T92282).
		this.toolbar.onWindowResize();
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.setupToolbarSaveButton = function () {
	this.toolbarSaveButton = this.actionsToolbar.getToolGroupByName( 'save' ).items[ 0 ];
};

/**
 * Set up notices for things like unknown browsers.
 * Needs to be done on each activation because localNoticeMessages is cleared in clearState.
 */
ve.init.mw.DesktopArticleTarget.prototype.setupLocalNoticeMessages = function () {
	if ( !(
		'vesupported' in this.currentUri.query ||
		$.client.test( this.constructor.static.compatibility.supportedList, null, true )
	) ) {
		// Show warning in unknown browsers that pass the support test
		// Continue at own risk.
		this.localNoticeMessages.push( 'visualeditor-browserwarning' );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.loadSuccess = function () {
	var target = this;

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.loadSuccess.apply( this, arguments );

	this.wikitextFallbackLoading = false;
	// Duplicate of this code in ve.init.mw.DesktopArticleTarget.init.js
	// eslint-disable-next-line no-jquery/no-class-state
	if ( $( '#ca-edit' ).hasClass( 'visualeditor-showtabdialog' ) ) {
		$( '#ca-edit' ).removeClass( 'visualeditor-showtabdialog' );
		// Set up a temporary window manager
		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		this.editingTabDialog = new mw.libs.ve.EditingTabDialog();
		windowManager.addWindows( [ this.editingTabDialog ] );
		windowManager.openWindow( this.editingTabDialog )
			.closed.then( function ( data ) {
				// Detach the temporary window manager
				windowManager.destroy();

				if ( data && data.action === 'prefer-wt' ) {
					target.switchToWikitextEditor( false );
				} else if ( data && data.action === 'multi-tab' ) {
					location.reload();
				}
			} );

		// Pretend the user saw the welcome dialog before suppressing it.
		mw.libs.ve.stopShowingWelcomeDialog();
		this.suppressNormalStartupDialogs = true;
	}
};

/**
 * Handle the watch button being toggled on/off.
 *
 * @param {boolean} isWatched
 * @param {string} expiry
 * @param {string} expirySelected
 */
ve.init.mw.DesktopArticleTarget.prototype.onWatchToggle = function ( isWatched ) {
	if ( !this.active && !this.activating ) {
		return;
	}
	if ( this.checkboxesByName.wpWatchthis ) {
		this.checkboxesByName.wpWatchthis.setSelected(
			!!mw.user.options.get( 'watchdefault' ) ||
			( !!mw.user.options.get( 'watchcreations' ) && !this.pageExists ) ||
			isWatched
		);
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.bindHandlers = function () {
	ve.init.mw.DesktopArticleTarget.super.prototype.bindHandlers.call( this );
	if ( this.onWatchToggleHandler ) {
		mw.hook( 'wikipage.watchlistChange' ).add( this.onWatchToggleHandler );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.unbindHandlers = function () {
	ve.init.mw.DesktopArticleTarget.super.prototype.unbindHandlers.call( this );
	if ( this.onWatchToggleHandler ) {
		mw.hook( 'wikipage.watchlistChange' ).remove( this.onWatchToggleHandler );
	}
};

/**
 * Switch to edit mode.
 *
 * @param {jQuery.Promise} [dataPromise] Promise for pending request from
 *   mw.libs.ve.targetLoader#requestPageData, if any
 * @return {jQuery.Promise}
 */
ve.init.mw.DesktopArticleTarget.prototype.activate = function ( dataPromise ) {
	var target = this;

	// We may be re-activating an old target, during which time ve.init.target
	// has been overridden.
	ve.init.target = ve.init.articleTarget;

	if ( !this.active && !this.activating ) {
		this.activating = true;
		this.activatingDeferred = ve.createDeferred();
		this.toolbarSetupDeferred = ve.createDeferred();

		$( 'html' ).addClass( 've-activating' );
		ve.promiseAll( [ this.activatingDeferred, this.toolbarSetupDeferred ] ).done( function () {
			target.afterActivate();
		} ).fail( function () {
			$( 'html' ).removeClass( 've-activating' );
		} );

		// Handlers were unbound in constructor. Will be unbound again in teardown.
		this.bindHandlers();

		this.originalEditondbclick = mw.user.options.get( 'editondblclick' );
		mw.user.options.set( 'editondblclick', 0 );

		// User interface changes
		this.changeDocumentTitle();
		this.transformPage();
		this.setupLocalNoticeMessages();

		this.load( dataPromise );
	}
	return this.activatingDeferred.promise();
};

/**
 * Edit mode has finished activating
 */
ve.init.mw.DesktopArticleTarget.prototype.afterActivate = function () {
	$( 'html' ).removeClass( 've-activating' ).addClass( 've-active' );

	// Disable TemplateStyles in the original content
	// (We do this here because toggling 've-active' class above hides it)
	this.$editableContent.find( 'style[data-mw-deduplicate^="TemplateStyles:"]' ).prop( 'disabled', true );

	this.afterSurfaceReady();

	if ( !this.editingTabDialog ) {
		if ( this.sectionTitle ) {
			this.sectionTitle.focus();
		} else {
			// We have to focus the page after hiding the original content, otherwise
			// in firefox the contentEditable container was below the view page, and
			// 'focus' scrolled the screen down.
			// Support: Firefox
			this.getSurface().getView().focus();
		}
		// Transfer and initial source range to the surface (e.g. from tempWikitextEditor)
		if ( this.initialSourceRange && this.getSurface().getMode() === 'source' ) {
			var surfaceModel = this.getSurface().getModel();
			var range = surfaceModel.getRangeFromSourceOffsets( this.initialSourceRange.from, this.initialSourceRange.to );
			surfaceModel.setLinearSelection( range );
		}
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.setSurface = function ( surface ) {
	var resetSurface = surface !== this.surface;

	if ( resetSurface ) {
		this.$editableContent.after( surface.$element );
	}

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.setSurface.apply( this, arguments );

	if ( resetSurface ) {
		this.setupNewSection( surface );
	}
};

/**
 * Setup new section input for a surface, if required
 *
 * @param {ve.ui.Surface} surface
 */
ve.init.mw.DesktopArticleTarget.prototype.setupNewSection = function ( surface ) {
	if ( surface.getMode() === 'source' && this.section === 'new' ) {
		if ( !this.sectionTitle ) {
			this.sectionTitle = new OO.ui.TextInputWidget( {
				$element: $( '<h2>' ),
				classes: [ 've-ui-init-desktopArticleTarget-sectionTitle' ],
				placeholder: ve.msg( 'visualeditor-section-title-placeholder' ),
				spellcheck: true
			} );
			if ( this.recovered ) {
				this.sectionTitle.setValue(
					ve.init.platform.sessionStorage.get( 've-docsectiontitle' ) || ''
				);
			}
			this.sectionTitle.connect( this, { change: 'onSectionTitleChange' } );
		}
		surface.setPlaceholder( ve.msg( 'visualeditor-section-body-placeholder' ) );
		this.$editableContent.before( this.sectionTitle.$element );

		if ( this.currentUri.query.preloadtitle ) {
			this.sectionTitle.setValue( this.currentUri.query.preloadtitle );
		}
		surface.once( 'destroy', this.teardownNewSection.bind( this, surface ) );
	} else {
		ve.init.platform.sessionStorage.remove( 've-docsectiontitle' );
	}
};

/**
 * Handle section title changes
 */
ve.init.mw.DesktopArticleTarget.prototype.onSectionTitleChange = function () {
	ve.init.platform.sessionStorage.set( 've-docsectiontitle', this.sectionTitle.getValue() );
	this.updateToolbarSaveButtonState();
};

/**
 * Teardown new section inputs
 *
 * @param {ve.ui.Surface} surface
 */
ve.init.mw.DesktopArticleTarget.prototype.teardownNewSection = function ( surface ) {
	surface.setPlaceholder( '' );
	if ( this.sectionTitle ) {
		this.sectionTitle.$element.remove();
		this.sectionTitle = null;
	}
};

/**
 * @inheritdoc
 *
 * A prompt will not be shown if tryTeardown() is called while activation is still in progress.
 * If tryTeardown() is called while the target is deactivating, or while it's not active and
 * not activating, nothing happens.
 */
ve.init.mw.DesktopArticleTarget.prototype.tryTeardown = function ( noPrompt, trackMechanism ) {
	if ( this.deactivating || ( !this.active && !this.activating ) ) {
		return this.teardownPromise || ve.createDeferred().reject().promise();
	}

	// Just in case these weren't closed before
	if ( this.welcomeDialog ) {
		this.welcomeDialog.close();
	}
	if ( this.editingTabDialog ) {
		this.editingTabDialog.close();
	}
	this.editingTabDialog = null;

	// Parent method
	return ve.init.mw.DesktopArticleTarget.super.prototype.tryTeardown.call( this, noPrompt || this.activating, trackMechanism );
};

/**
 * @inheritdoc
 *
 * @param {string} [trackMechanism]
 * @fires deactivate
 */
ve.init.mw.DesktopArticleTarget.prototype.teardown = function ( trackMechanism ) {
	var
		saveDialogPromise = ve.createDeferred().resolve().promise(),
		target = this;

	this.emit( 'deactivate' );

	// Event tracking
	var abortType, abortedMode;
	if ( trackMechanism ) {
		if ( this.activating ) {
			abortType = 'preinit';
		} else if ( !this.edited ) {
			abortType = 'nochange';
		} else if ( this.saving ) {
			abortType = 'abandonMidsave';
		} else {
			// switchwith and switchwithout do not go through this code path,
			// they go through switchToWikitextEditor() instead
			abortType = 'abandon';
		}
		abortedMode = this.surface ? this.surface.getMode() : this.getDefaultMode();
	}

	// Cancel activating, start deactivating
	this.deactivating = true;
	this.activating = false;
	this.activatingDeferred.reject();
	$( 'html' ).addClass( 've-deactivating' ).removeClass( 've-activated ve-active' );

	// Restore TemplateStyles of the original content
	// (We do this here because toggling 've-active' class above displays it)
	this.$editableContent.find( 'style[data-mw-deduplicate^="TemplateStyles:"]' ).prop( 'disabled', false );

	// User interface changes
	this.restorePage();
	this.restoreDocumentTitle();

	mw.user.options.set( 'editondblclick', this.originalEditondbclick );
	this.originalEditondbclick = undefined;

	// TODO: Use better checks to see if these restorations are required.
	if ( this.getSurface() ) {
		if ( this.active ) {
			this.teardownUnloadHandlers();
		}
	}

	if ( this.saveDialog ) {
		if ( this.saveDialog.isOpened() ) {
			// If the save dialog is still open (from saving) close it
			saveDialogPromise = this.saveDialog.close().closed;
		}
		// Release the reference
		this.saveDialog = null;
	}

	return saveDialogPromise.then( function () {
		// Parent method
		return ve.init.mw.DesktopArticleTarget.super.prototype.teardown.call( target ).then( function () {
			// After teardown
			target.active = false;

			// If there is a load in progress, try to abort it
			if ( target.loading && target.loading.abort ) {
				target.loading.abort();
			}

			target.clearState();
			target.initialEditSummary = new mw.Uri().query.summary;
			target.editSummaryValue = null;

			target.deactivating = false;
			$( 'html' ).removeClass( 've-deactivating' );

			// Move original content back out of the target
			target.$element.parent().append( target.$originalContent.children() );

			$( '.ve-init-mw-desktopArticleTarget-uneditableContent' )
				.removeClass( 've-init-mw-desktopArticleTarget-uneditableContent' );

			// Event tracking
			if ( trackMechanism ) {
				ve.track( 'mwedit.abort', {
					type: abortType,
					mechanism: trackMechanism,
					mode: abortedMode
				} );
			}

			if ( !target.isViewPage ) {
				location.href = target.viewUri.clone().extend( {
					redirect: mw.config.get( 'wgIsRedirect' ) ? 'no' : undefined
				} );
			}
		} );
	} );
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.loadFail = function ( code, errorDetails ) {
	var target = this;

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.loadFail.apply( this, arguments );

	if ( this.wikitextFallbackLoading ) {
		// Failed twice now
		mw.log.warn( 'Failed to fall back to wikitext', code, errorDetails );
		location.href = this.viewUri.clone().extend( { action: 'edit', veswitched: 1 } );
		return;
	}

	if ( !this.activating ) {
		// Load failed after activation abandoned (e.g. user pressed escape).
		// Nothing more to do.
		return;
	}

	var $confirmPromptMessage = this.extractErrorMessages( errorDetails );

	OO.ui.confirm( $confirmPromptMessage, {
		actions: [
			{ action: 'accept', label: OO.ui.msg( 'ooui-dialog-process-retry' ), flags: 'primary' },
			{ action: 'reject', label: OO.ui.msg( 'ooui-dialog-message-reject' ), flags: 'safe' }
		]
	} ).done( function ( confirmed ) {
		if ( confirmed ) {
			// Retry load
			target.load();
		} else {
			// User pressed cancel
			if ( target.getSurface() ) {
				// Switching from VE source mode
				target.activatingDeferred.reject();
				// TODO: Some sort of progress bar?
				target.wikitextFallbackLoading = true;
				target.switchToWikitextEditor( false );
			} else {
				// We're switching from read mode or the 2010 wikitext editor:
				// just give up and stay where you are
				target.tryTeardown( true );
			}
		}
	} );
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.surfaceReady = function () {
	if ( !this.activating ) {
		// Activation was aborted before we got here. Do nothing
		// TODO are there things we need to clean up?
		return;
	}

	var surface = this.getSurface();

	this.activating = false;

	// TODO: mwTocWidget should probably live in a ve.ui.MWSurface subclass
	if ( mw.config.get( 'wgVisualEditorConfig' ).enableTocWidget ) {
		surface.mwTocWidget = new ve.ui.MWTocWidget( this.getSurface() );
		surface.once( 'destroy', function () {
			surface.mwTocWidget.$element.remove();
		} );
	}

	var metaList = this.getSurface().getModel().getMetaList();

	metaList.connect( this, {
		insert: 'onMetaItemInserted',
		remove: 'onMetaItemRemoved'
	} );
	// Rebuild the category list from the page we got from the API. This makes
	// it work regardless of whether we came here from activating on an
	// existing page, or loading via an edit URL.
	this.rebuildCategories( metaList.getItemsInGroup( 'mwCategory' ), true );

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.surfaceReady.apply( this, arguments );

	var redirectMetaItems = this.getSurface().getModel().getMetaList().getItemsInGroup( 'mwRedirect' );
	if ( redirectMetaItems.length ) {
		this.setFakeRedirectInterface( redirectMetaItems[ 0 ].getAttribute( 'title' ) );
	} else {
		this.setFakeRedirectInterface( null );
	}

	this.setupUnloadHandlers();
	if ( !this.suppressNormalStartupDialogs ) {
		this.maybeShowWelcomeDialog();
		this.maybeShowMetaDialog();
	}

	this.activatingDeferred.resolve();
	this.events.trackActivationComplete();
};

/**
 * Update the redirect and category interfaces when a meta item is inserted into the page.
 *
 * @param {ve.dm.MetaItem} metaItem Item that was inserted
 */
ve.init.mw.DesktopArticleTarget.prototype.onMetaItemInserted = function ( metaItem ) {
	switch ( metaItem.getType() ) {
		case 'mwRedirect':
			this.setFakeRedirectInterface( metaItem.getAttribute( 'title' ) );
			break;
		case 'mwCategory':
			var metaList = this.getSurface().getModel().getMetaList();
			this.rebuildCategories( metaList.getItemsInGroup( 'mwCategory' ) );
			break;
	}
};

/**
 * Update the redirect and category interfaces when a meta item is removed from the page.
 *
 * @param {ve.dm.MetaItem} metaItem Item that was removed
 * @param {number} offset Linear model offset that the item was at
 * @param {number} index Index within that offset the item was at
 */
ve.init.mw.DesktopArticleTarget.prototype.onMetaItemRemoved = function ( metaItem ) {
	switch ( metaItem.getType() ) {
		case 'mwRedirect':
			this.setFakeRedirectInterface( null );
			break;
		case 'mwCategory':
			var metaList = this.getSurface().getModel().getMetaList();
			this.rebuildCategories( metaList.getItemsInGroup( 'mwCategory' ) );
			break;
	}
};

/**
 * Redisplay the category list on the page
 *
 * This is used for the preview while editing. Leaving the editor either restores the initial
 * categories, or uses the ones generated by the save API.
 *
 * @param {ve.dm.MetaItem[]} categoryItems Array of category metaitems to display
 */
ve.init.mw.DesktopArticleTarget.prototype.rebuildCategories = function ( categoryItems ) {
	var target = this;
	this.renderCategories( categoryItems ).done( function ( $categories ) {
		// Clone the existing catlinks for any specific properties which might
		// be needed by the rest of the page. Also gives us a not-attached
		// version, which we can pass to wikipage.categories as it requests.
		var $catlinks = $( '#catlinks' ).clone().empty().removeClass( 'categories-allhidden' )
			.append( $categories.children() );
		// If all categories are hidden, we need to hide the box.
		$catlinks.toggleClass( 'catlinks-allhidden',
			$catlinks.find( '.mw-normal-catlinks' ).length === 0 &&
			// Some situations make the hidden-categories visible (a user
			// preference, and being on a category page) so rather than
			// encoding that logic here just check whether they're visible:
			// eslint-disable-next-line no-jquery/no-sizzle
			$catlinks.find( '.mw-hidden-catlinks:visible' ).length === 0
		);
		target.transformCategoryLinks( $catlinks );
		mw.hook( 'wikipage.categories' ).fire( $catlinks );
		$( '#catlinks' ).replaceWith( $catlinks );
	} );
};

/**
 * Handle Escape key presses.
 *
 * @param {jQuery.Event} e Keydown event
 */
ve.init.mw.DesktopArticleTarget.prototype.onDocumentKeyDown = function ( e ) {
	var target = this;

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.onDocumentKeyDown.apply( this, arguments );

	if ( e.which === OO.ui.Keys.ESCAPE ) {
		setTimeout( function () {
			// Listeners should stopPropagation if they handle the escape key, but
			// also check they didn't fire after this event, as would be the case if
			// they were bound to the document.
			if ( !e.isPropagationStopped() ) {
				var toolbarDialogs = target.surface && target.surface.getToolbarDialogs();
				if ( toolbarDialogs && toolbarDialogs.getCurrentWindow() ) {
					toolbarDialogs.getCurrentWindow().close();
				} else {
					target.tryTeardown( false, 'navigate-read' );
				}
			}
		} );
		e.preventDefault();
	}
};

/**
 * Handle clicks on the view tab.
 *
 * @param {jQuery.Event} e Mouse click event
 */
ve.init.mw.DesktopArticleTarget.prototype.onViewTabClick = function ( e ) {
	if ( ( !this.active && !this.activating ) || !ve.isUnmodifiedLeftClick( e ) ) {
		return;
	}
	this.tryTeardown( false, 'navigate-read' );
	e.preventDefault();
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.saveComplete = function ( data ) {
	// Desktop post-edit notification
	if ( this.pageExists && !this.restoring && data.newrevid !== undefined ) {
		// Append postEdit module to the list that will be loaded in the parent method
		data.modules = data.modules.concat( [ 'mediawiki.action.view.postEdit' ] );
	}

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.saveComplete.apply( this, arguments );

	if ( this.pageExists && !this.restoring ) {
		// Fix permalinks
		if ( data.newrevid !== undefined ) {
			$( '#t-permalink a, #coll-download-as-rl a' ).each( function () {
				var uri = new mw.Uri( $( this ).attr( 'href' ) );
				uri.query.oldid = data.newrevid;
				$( this ).attr( 'href', uri.toString() );
			} );
		}

		// Actually fire the postEdit hook now that the save is complete
		if ( data.newrevid !== undefined ) {
			mw.hook( 'postEdit' ).fire( {
				// The following messages are used here:
				// * postedit-confirmation-published
				// * postedit-confirmation-saved
				message: ve.msg( 'postedit-confirmation-' + ( mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ? 'published' : 'saved' ), mw.user )
			} );
		}
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.serialize = function () {
	// Parent method
	var promise = ve.init.mw.DesktopArticleTarget.super.prototype.serialize.apply( this, arguments ),
		target = this;

	return promise.fail( function ( error, response ) {
		var $errorMessages = target.extractErrorMessages( response );
		OO.ui.alert(
			$( ve.htmlMsg(
				'visualeditor-serializeerror',
				$( '<span>' ).append( $errorMessages )[ 0 ]
			) )
		);

		// It's possible to get here while the save dialog has never been opened (if the user uses
		// the switch to source mode option)
		if ( target.saveDialog ) {
			target.saveDialog.popPending();
		}
	} );
};

/**
 * Handle clicks on the MwMeta button in the toolbar.
 *
 * @param {jQuery.Event} e Mouse click event
 */
ve.init.mw.DesktopArticleTarget.prototype.onToolbarMetaButtonClick = function () {
	this.getSurface().getDialogs().openWindow( 'meta' );
};

/**
 * Modify tabs in the skin to support in-place editing.
 *
 * 'Read' and 'Edit source' (when not using single edit tab) bound here,
 * 'Edit' and single edit tab are bound in mw.DesktopArticleTarget.init.
 */
ve.init.mw.DesktopArticleTarget.prototype.setupSkinTabs = function () {
	if ( this.isViewPage ) {
		var namespaceNumber = mw.config.get( 'wgNamespaceNumber' );
		var namespaceName = mw.config.get( 'wgCanonicalNamespace' );
		var isTalkNamespace = mw.Title.isTalkNamespace( namespaceNumber );
		// Title::getNamespaceKey()
		var namespaceKey = namespaceName.toLowerCase() || 'main';
		if ( namespaceKey === 'file' ) {
			namespaceKey = 'image';
		}
		var namespaceTabId;
		// SkinTemplate::buildContentNavigationUrls()
		if ( isTalkNamespace ) {
			namespaceTabId = 'ca-talk';
		} else {
			namespaceTabId = 'ca-nstab-' + namespaceKey;
		}
		// Allow instant switching back to view mode, without refresh
		$( '#ca-view a, #' + namespaceTabId + ' a' )
			.on( 'click.ve-target', this.onViewTabClick.bind( this ) );
	}

	// Used by Extension:GuidedTour
	mw.hook( 've.skinTabSetupComplete' ).fire();
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.getSaveDialogOpeningData = function () {
	var data = ve.init.mw.DesktopArticleTarget.super.prototype.getSaveDialogOpeningData.apply( this, arguments );
	data.editSummary = this.editSummaryValue || this.initialEditSummary;
	return data;
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.teardownToolbar = function () {
	var target = this,
		deferred = ve.createDeferred();

	if ( !this.toolbar ) {
		return deferred.resolve().promise();
	}

	this.toolbar.$element
		.addClass( 've-init-mw-desktopArticleTarget-toolbar-preclose' )
		.css( 'height', this.toolbar.$bar[ 0 ].offsetHeight );
	setTimeout( function () {
		target.toolbar.$element
			.css( 'height', '0' )
			.addClass( 've-init-mw-desktopArticleTarget-toolbar-close' );
		setTimeout( function () {
			// Parent method
			ve.init.mw.DesktopArticleTarget.super.prototype.teardownToolbar.call( target );
			deferred.resolve();
		}, 250 );
	} );
	return deferred.promise();
};

/**
 * Change the document title to state that we are now editing.
 */
ve.init.mw.DesktopArticleTarget.prototype.changeDocumentTitle = function () {
	var title = mw.Title.newFromText( this.getPageName() );

	// Use the real title if we loaded a view page, otherwise reconstruct it
	this.originalDocumentTitle = this.isViewPage ? document.title : ve.msg( 'pagetitle', title.getPrefixedText() );

	// Reconstruct an edit title
	document.title = ve.msg( 'pagetitle',
		ve.msg(
			this.pageExists ? 'editing' : 'creating',
			title.getPrefixedText()
		)
	);
};

/**
 * Restore the original document title.
 */
ve.init.mw.DesktopArticleTarget.prototype.restoreDocumentTitle = function () {
	document.title = this.originalDocumentTitle;
};

/**
 * Page modifications for switching to edit mode.
 *
 * @fires transformPage
 */
ve.init.mw.DesktopArticleTarget.prototype.transformPage = function () {
	this.updateTabs( true );
	this.emit( 'transformPage' );

	// TODO: Deprecate in favour of ve.activationComplete
	// Only used by one gadget
	mw.hook( 've.activate' ).fire();

	// Move all native content inside the target
	// Exclude notification area to work around T143837
	this.$originalContent.append(
		this.$element.siblings()
			.not( '.mw-notification-area' )
			.not( '.ve-init-mw-desktopArticleTarget-toolbarPlaceholder' )
	);

	// To preserve event handlers (e.g. HotCat) if editing is cancelled, detach the original container
	// and replace it with a clone during editing
	this.$originalCategories = $( '#catlinks' );
	this.$originalCategories.after( this.$originalCategories.clone() );
	this.$originalCategories.detach();

	// Mark every non-direct ancestor between editableContent and the container as uneditable
	var $content = this.$editableContent;
	while ( $content && $content.length && !$content.parent().is( this.$container ) ) {
		$content.prevAll( ':not( .ve-init-mw-tempWikitextEditorWidget )' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );
		$content.nextAll( ':not( .ve-init-mw-tempWikitextEditorWidget )' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );
		$content = $content.parent();
	}

	this.updateHistoryState();
};

/**
 * Category link section transformations for switching to edit mode. Broken out
 * so it can be re-applied when displaying changes to the categories.
 *
 * @param {jQuery} $catlinks Category links container element
 */
ve.init.mw.DesktopArticleTarget.prototype.transformCategoryLinks = function ( $catlinks ) {
	var target = this;
	// Un-disable the catlinks wrapper, but not the links
	if ( this.getSurface() && this.getSurface().getMode() === 'visual' ) {
		$catlinks.removeClass( 've-init-mw-desktopArticleTarget-uneditableContent' )
			.on( 'click.ve-target', function () {
				var windowAction = ve.ui.actionFactory.create( 'window', target.getSurface() );
				windowAction.open( 'meta', { page: 'categories' } );
				return false;
			} )
			.find( 'a' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );
	} else {
		$catlinks.addClass( 've-init-mw-desktopArticleTarget-uneditableContent' ).off( 'click.ve-target' );
	}
};

/**
 * Update the history state based on the editor mode
 */
ve.init.mw.DesktopArticleTarget.prototype.updateHistoryState = function () {
	var veaction = this.getDefaultMode() === 'visual' ? 'edit' : 'editsource',
		section = this.section !== null ? this.section : undefined;

	// Push veaction=edit(source) url in history (if not already. If we got here by a veaction=edit(source)
	// permalink then it will be there already and the constructor called #activate)
	if (
		!this.actFromPopState &&
		history.pushState &&
		(
			this.currentUri.query.veaction !== veaction ||
			this.currentUri.query.section !== section
		) &&
		this.currentUri.query.action !== 'edit'
	) {
		// Set the current URL
		var uri = this.currentUri;

		if ( mw.libs.ve.isSingleEditTab ) {
			uri.query.action = 'edit';
			mw.config.set( 'wgAction', 'edit' );
		} else {
			uri.query.veaction = veaction;
			delete uri.query.action;
			mw.config.set( 'wgAction', 'view' );
		}
		if ( this.section !== null ) {
			uri.query.section = this.section;
		} else {
			delete uri.query.section;
		}

		history.pushState( this.popState, document.title, uri );
	}
	this.actFromPopState = false;
};

/**
 * Page modifications for switching back to view mode.
 *
 * @fires restorePage
 */
ve.init.mw.DesktopArticleTarget.prototype.restorePage = function () {
	// Skins like monobook don't have a tab for view mode and instead just have the namespace tab
	// selected. We didn't deselect the namespace tab, so we're ready after deselecting #ca-ve-edit.
	// In skins having #ca-view (like Vector), select that.
	this.updateTabs( false );

	// Restore any previous redirectMsg/redirectsub
	this.setRealRedirectInterface();
	if ( this.$originalCategories ) {
		$( '#catlinks' ).replaceWith( this.$originalCategories );
	}

	// TODO: Deprecate in favour of ve.deactivationComplete
	mw.hook( 've.deactivate' ).fire();
	this.emit( 'restorePage' );

	// Push article url into history
	if ( !this.actFromPopState && history.pushState ) {
		// Remove the VisualEditor query parameters
		var uri = this.currentUri;
		if ( 'veaction' in uri.query ) {
			delete uri.query.veaction;
		}
		if ( this.section !== null ) {
			// Translate into a fragment for the new URI:
			// This should be after replacePageContent if this is post-save, so we can just look
			// at the headers on the page.
			var fragment = this.getSectionFragmentFromPage();
			if ( fragment ) {
				uri.fragment = fragment;
				this.viewUri.fragment = fragment;
				var target = document.getElementById( fragment );

				if ( target ) {
					// Scroll the page to the edited section
					setTimeout( function () {
						target.scrollIntoView( true );
					} );
				}
			}
			delete uri.query.section;
		}
		if ( 'action' in uri.query && $( '#wpTextbox1:not(.ve-dummyTextbox)' ).length === 0 ) {
			// If we're not overlaid on an edit page, remove action=edit
			delete uri.query.action;
			mw.config.set( 'wgAction', 'view' );
		}
		if ( 'oldid' in uri.query && !this.restoring ) {
			// We have an oldid in the query string but it's the most recent one, so remove it
			delete uri.query.oldid;
		}

		// If there are any other query parameters left, re-use that uri object.
		// Otherwise use the canonical style view url (T44553, T102363).
		var keys = Object.keys( uri.query );
		if ( !keys.length || ( keys.length === 1 && keys[ 0 ] === 'title' ) ) {
			history.pushState( this.popState, document.title, this.viewUri );
		} else {
			history.pushState( this.popState, document.title, uri );
		}
	}
};

/**
 * @param {Event} e Native event object
 */
ve.init.mw.DesktopArticleTarget.prototype.onWindowPopState = function ( e ) {
	var target = this;

	if ( !this.verifyPopState( e.state ) ) {
		// Ignore popstate events fired for states not created by us
		// This also filters out the initial fire in Chrome (T59901).
		return;
	}

	var oldUri = this.currentUri;

	this.currentUri = new mw.Uri( location.href );
	var veaction = this.currentUri.query.veaction;

	if ( this.isModeAvailable( 'source' ) && this.active ) {
		if ( veaction === 'editsource' && this.getDefaultMode() === 'visual' ) {
			this.actFromPopState = true;
			this.switchToWikitextEditor();
		} else if ( veaction === 'edit' && this.getDefaultMode() === 'source' ) {
			this.actFromPopState = true;
			this.switchToVisualEditor();
		}
	}
	if ( !this.active && ( veaction === 'edit' || veaction === 'editsource' ) ) {
		this.actFromPopState = true;
		this.emit( 'reactivate' );
	}
	if ( this.active && veaction !== 'edit' && veaction !== 'editsource' ) {
		this.actFromPopState = true;
		// "Undo" the pop-state, as the event is not cancellable
		history.pushState( this.popState, document.title, oldUri );
		this.currentUri = oldUri;
		this.tryTeardown( false, 'navigate-back' ).then( function () {
			// Teardown was successful, re-apply the undone state
			history.back();
		} ).always( function () {
			target.actFromPopState = false;
		} );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.replacePageContent = function (
	html, categoriesHtml, displayTitle, lastModified, contentSub
) {
	var $content = $( $.parseHTML( html ) );

	if ( lastModified ) {
		// If we were not viewing the most recent revision before (a requirement
		// for lastmod to have been added by MediaWiki), we will be now.
		if ( !$( '#footer-info-lastmod' ).length ) {
			$( '#footer-info' ).prepend(
				$( '<li>' ).attr( 'id', 'footer-info-lastmod' )
			);
		}

		// Intentionally treated as HTML
		// eslint-disable-next-line no-jquery/no-html
		$( '#footer-info-lastmod' ).html( ' ' + mw.msg(
			'lastmodifiedat',
			lastModified.date,
			lastModified.time
		) );
	}

	this.$editableContent.find( '.mw-parser-output' ).replaceWith( $content );
	mw.hook( 'wikipage.content' ).fire( this.$editableContent );
	if ( displayTitle ) {
		// eslint-disable-next-line no-jquery/no-html
		$( '#firstHeading' ).html( displayTitle );
	}

	var $categories = $( $.parseHTML( categoriesHtml ) );
	mw.hook( 'wikipage.categories' ).fire( $categories );
	$( '#catlinks' ).replaceWith( $categories );
	this.$originalCategories = null;

	// eslint-disable-next-line no-jquery/no-html
	$( '#contentSub' ).html( contentSub );
	this.setRealRedirectInterface();

	// Re-set any edit section handlers now that the page content has been replaced
	mw.libs.ve.setupEditLinks();
};

/**
 * Add onunload and onbeforeunload handlers.
 */
ve.init.mw.DesktopArticleTarget.prototype.setupUnloadHandlers = function () {
	if ( window.onbeforeunload !== this.onBeforeUnload ) {
		// Remember any already set beforeunload handler
		this.onBeforeUnloadFallback = window.onbeforeunload;
		// Attach our handlers
		window.onbeforeunload = this.onBeforeUnload;
		window.addEventListener( 'unload', this.onUnloadHandler );
	}
};
/**
 * Remove onunload and onbeforunload handlers.
 */
ve.init.mw.DesktopArticleTarget.prototype.teardownUnloadHandlers = function () {
	// Restore whatever previous onbeforeunload hook existed
	window.onbeforeunload = this.onBeforeUnloadFallback;
	this.onBeforeUnloadFallback = null;
	window.removeEventListener( 'unload', this.onUnloadHandler );
};

/**
 * Show the beta dialog as needed
 */
ve.init.mw.DesktopArticleTarget.prototype.maybeShowWelcomeDialog = function () {
	var editorMode = this.getDefaultMode(),
		windowManager = this.getSurface().dialogs,
		target = this;

	this.welcomeDialogPromise = ve.createDeferred();

	if ( mw.libs.ve.shouldShowWelcomeDialog() ) {
		this.welcomeDialog = new mw.libs.ve.WelcomeDialog();
		windowManager.addWindows( [ this.welcomeDialog ] );
		windowManager.openWindow(
			this.welcomeDialog,
			{
				switchable: editorMode === 'source' ? this.isModeAvailable( 'visual' ) : true,
				editor: editorMode
			}
		)
			.closed.then( function ( data ) {
				target.welcomeDialogPromise.resolve();
				target.welcomeDialog = null;
				if ( data && data.action === 'switch-wte' ) {
					target.switchToWikitextEditor( false );
				} else if ( data && data.action === 'switch-ve' ) {
					target.switchToVisualEditor();
				}
			} );
		mw.libs.ve.stopShowingWelcomeDialog();
	} else {
		this.welcomeDialogPromise.reject();
	}
};

/**
 * Show the meta dialog as needed on load.
 */
ve.init.mw.DesktopArticleTarget.prototype.maybeShowMetaDialog = function () {
	var target = this;

	if ( this.welcomeDialogPromise ) {
		// Pop out the notices when the welcome dialog is closed
		this.welcomeDialogPromise
			.always( function () {
				if (
					target.switched &&
					!mw.user.options.get( 'visualeditor-hidevisualswitchpopup' )
				) {
					// Show "switched" popup
					var popup = new mw.libs.ve.SwitchPopupWidget( 'visual' );
					target.actionsToolbar.tools.editModeSource.toolGroup.$element.append( popup.$element );
					popup.toggle( true );
				} else if ( target.actionsToolbar.tools.notices ) {
					// Show notices
					target.actionsToolbar.tools.notices.getPopup().toggle( true );
				}
			} );
	}

	var redirectMetaItems = this.getSurface().getModel().getMetaList().getItemsInGroup( 'mwRedirect' );
	if ( redirectMetaItems.length ) {
		var windowAction = ve.ui.actionFactory.create( 'window', this.getSurface() );
		windowAction.open( 'meta', { page: 'settings' } );
	}
};

/**
 * Handle before unload event.
 *
 * @return {string} Message
 */
ve.init.mw.DesktopArticleTarget.prototype.onBeforeUnload = function () {
	// Check if someone already set on onbeforeunload hook
	if ( this.onBeforeUnloadFallback ) {
		// Get the result of their onbeforeunload hook
		var fallbackResult = this.onBeforeUnloadFallback();
		// If it returned something, exit here and return their message
		if ( fallbackResult !== undefined ) {
			return fallbackResult;
		}
	}
	// Check if there's been an edit
	if (
		this.getSurface() &&
		$.contains( document, this.getSurface().$element.get( 0 ) ) &&
		this.edited &&
		!this.submitting &&
		mw.user.options.get( 'useeditwarning' )
	) {
		// Return our message
		return ve.msg( 'mw-widgets-abandonedit' );
	}
};

/**
 * Handle unload event.
 */
ve.init.mw.DesktopArticleTarget.prototype.onUnload = function () {
	if ( !this.submitting ) {
		ve.track( 'mwedit.abort', {
			type: this.edited ? 'unknown-edited' : 'unknown',
			mechanism: 'navigate',
			mode: this.surface ? this.surface.getMode() : this.getDefaultMode()
		} );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.switchToVisualEditor = function () {
	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.switchToVisualEditor.apply( this, arguments );

	if ( this.isModeAvailable( 'visual' ) ) {
		ve.track( 'activity.editor-switch', { action: 'visual-desktop' } );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.switchToWikitextEditor = function () {
	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.switchToWikitextEditor.apply( this, arguments );

	if ( this.isModeAvailable( 'source' ) ) {
		ve.track( 'activity.editor-switch', { action: 'source-nwe-desktop' } );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.switchToWikitextSection = function () {
	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.switchToWikitextSection.apply( this, arguments );

	ve.track( 'activity.editor-switch', { action: 'source-nwe-desktop' } );
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.switchToFallbackWikitextEditor = function ( modified ) {
	var target = this;

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.switchToFallbackWikitextEditor.apply( this, arguments );

	var oldId = mw.config.get( 'wgRevisionId' ) || $( 'input[name=parentRevId]' ).val();
	var prefPromise = mw.libs.ve.setEditorPreference( 'wikitext' );

	if ( !modified ) {
		ve.track( 'activity.editor-switch', { action: 'source-desktop' } );
		ve.track( 'mwedit.abort', { type: 'switchnochange', mechanism: 'navigate', mode: 'visual' } );
		this.submitting = true;
		prefPromise.done( function () {
			var uri = target.viewUri.clone().extend( {
				action: 'edit',
				// No changes, safe to stay in section mode
				section: target.section !== null ? target.section : undefined,
				veswitched: 1
			} );
			if ( oldId && oldId !== mw.config.get( 'wgCurRevisionId' ) ) {
				uri.extend( { oldid: oldId } );
			}
			if ( mw.libs.ve.isWelcomeDialogSuppressed() ) {
				uri.extend( { vehidebetadialog: 1 } );
			}
			location.href = uri.toString();
		} );
	} else {
		this.serialize( this.getDocToSave() ).then( function ( data ) {
			ve.track( 'activity.editor-switch', { action: 'source-desktop' } );
			ve.track( 'mwedit.abort', { type: 'switchwith', mechanism: 'navigate', mode: 'visual' } );
			target.submitWithSaveFields( { wpDiff: true, wpAutoSummary: '' }, data.content );
		} );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopArticleTarget.prototype.reloadSurface = function () {
	var target = this;

	this.activating = true;
	this.activatingDeferred = ve.createDeferred();

	// Parent method
	ve.init.mw.DesktopArticleTarget.super.prototype.reloadSurface.apply( this, arguments );

	this.activatingDeferred.done( function () {
		target.updateHistoryState();
		target.afterActivate();
		target.setupTriggerListeners();
	} );
	this.toolbarSetupDeferred.resolve();
};

/* Registration */

ve.init.mw.targetFactory.register( ve.init.mw.DesktopArticleTarget );
