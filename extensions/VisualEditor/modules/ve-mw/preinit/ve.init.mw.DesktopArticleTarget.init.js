/*!
 * VisualEditor MediaWiki DesktopArticleTarget init.
 *
 * This file must remain as widely compatible as the base compatibility
 * for MediaWiki itself (see mediawiki/core:/resources/startup.js).
 * Avoid use of: SVG, HTML5 DOM, ContentEditable etc.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */
// TODO: ve.now and ve.track should be moved to mw.libs.ve
/* global ve */

/**
 * Platform preparation for the MediaWiki view page. This loads (when user needs it) the
 * actual MediaWiki integration and VisualEditor library.
 *
 * @class mw.libs.ve
 * @alternateClassName ve.init.mw.DesktopArticleTarget.init
 * @singleton
 */
( function () {
	var conf, tabMessages, uri, pageExists, viewUri, veEditUri, veEditSourceUri,
		init, targetPromise,
		tabPreference, initialWikitext, oldId,
		isLoading, tempWikitextEditor, tempWikitextEditorData,
		$toolbarPlaceholder, $toolbarPlaceholderBar,
		contentTop, wasFloating,
		configData = require( './data.json' ),
		veactionToMode = {
			edit: 'visual',
			editsource: 'source'
		},
		availableModes = [],
		active = false,
		targetLoaded = false,
		plugins = [],
		welcomeDialogDisabled = false,
		educationPopupsDisabled = false;

	function showLoading( /* mode */ ) {
		if ( isLoading ) {
			return;
		}

		isLoading = true;

		$( 'html' ).addClass( 've-activated ve-loading' );
		if ( !init.$loading ) {
			init.progressBar = new mw.libs.ve.ProgressBarWidget();
			init.$loading = $( '<div>' )
				.addClass( 've-init-mw-desktopArticleTarget-loading-overlay' )
				.append( init.progressBar.$element );
		}
		$( document ).on( 'keydown', onDocumentKeyDown );

		$toolbarPlaceholderBar.append( init.$loading );
	}

	function incrementLoadingProgress() {
		init.progressBar.incrementLoadingProgress();
	}

	function clearLoading() {
		init.progressBar.clearLoading();
		isLoading = false;
		$( document ).off( 'keydown', onDocumentKeyDown );
		$( 'html' ).removeClass( 've-loading' );
		if ( init.$loading ) {
			init.$loading.detach();
		}

		if ( tempWikitextEditor ) {
			teardownTempWikitextEditor();
		}
		hideToolbarPlaceholder();
	}

	function onWindowScroll() {
		var scrollTop = $( document.documentElement ).scrollTop();
		var floating = scrollTop > contentTop;
		if ( floating !== wasFloating ) {
			var width = $( '#content' ).outerWidth();
			$toolbarPlaceholder.toggleClass( 've-init-mw-desktopArticleTarget-toolbarPlaceholder-floating', floating );
			$toolbarPlaceholderBar.css( 'width', width );
			wasFloating = floating;
		}
	}

	var onWindowScrollListener = mw.util.throttle( onWindowScroll, 250 );

	function showToolbarPlaceholder() {
		if ( !$toolbarPlaceholder ) {
			// Create an equal-height placeholder for the toolbar to avoid vertical jump
			// when the real toolbar is ready.
			$toolbarPlaceholder = $( '<div>' ).addClass( 've-init-mw-desktopArticleTarget-toolbarPlaceholder' );
			$toolbarPlaceholderBar = $( '<div>' ).addClass( 've-init-mw-desktopArticleTarget-toolbarPlaceholder-bar' );
			$toolbarPlaceholder.append( $toolbarPlaceholderBar );
		}
		// Toggle -floating class before append (if required) to avoid content moving later
		contentTop = $( '#content' ).offset().top;
		wasFloating = null;
		onWindowScroll();

		var scrollTopBefore = $( document.documentElement ).scrollTop();

		$( '#content' ).prepend( $toolbarPlaceholder );

		// TODO: Would be better with ve.addPassiveEventListener
		$( window ).on( 'scroll', onWindowScrollListener );

		if ( wasFloating ) {
			// Browser might not support scroll anchoring:
			// https://developer.mozilla.org/en-US/docs/Web/CSS/overflow-anchor/Guide_to_scroll_anchoring
			// ...so compute the new scroll offset ourselves.
			window.scrollTo( 0, scrollTopBefore + $toolbarPlaceholder.outerHeight() );
		}

		// Add class for transition after first render
		setTimeout( function () {
			$toolbarPlaceholder.addClass( 've-init-mw-desktopArticleTarget-toolbarPlaceholder-open' );
		} );
	}

	function hideToolbarPlaceholder() {
		if ( $toolbarPlaceholder ) {
			$( window ).off( 'scroll', onWindowScrollListener );
			$toolbarPlaceholder.detach();
			$toolbarPlaceholder.removeClass( 've-init-mw-desktopArticleTarget-toolbarPlaceholder-open' );
		}
	}

	function setupTempWikitextEditor( data ) {
		var wikitext = data.content;
		// Add trailing linebreak to non-empty wikitext documents for consistency
		// with old editor and usability. Will be stripped on save. T156609
		if ( wikitext ) {
			wikitext += '\n';
		}
		tempWikitextEditor = new mw.libs.ve.MWTempWikitextEditorWidget( { value: wikitext } );
		tempWikitextEditorData = data;

		// Bring forward some transformations that show the editor is now ready
		$( '#firstHeading' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );
		$( '#mw-content-text' )
			.before( tempWikitextEditor.$element )
			.addClass( 'oo-ui-element-hidden' );
		$( 'html' ).addClass( 've-tempSourceEditing' ).removeClass( 've-loading' );

		// Resize the textarea to fit content. We could do this more often (e.g. on change)
		// but hopefully this temporary textarea won't be visible for too long.
		tempWikitextEditor.adjustSize().moveCursorToStart();
		ve.track( 'mwedit.ready', { mode: 'source', platform: 'desktop' } );
		mw.libs.ve.tempWikitextEditor = tempWikitextEditor;
		mw.hook( 've.wikitextInteractive' ).fire();
	}

	function syncTempWikitextEditor() {
		var wikitext = tempWikitextEditor.getValue();

		// Strip trailing linebreak. Will get re-added in ArticleTarget#parseDocument.
		if ( wikitext.slice( -1 ) === '\n' ) {
			wikitext = wikitext.slice( 0, -1 );
		}

		if ( wikitext !== tempWikitextEditorData.content ) {
			// Write changes back to response data object,
			// which will be used to construct the surface.
			tempWikitextEditorData.content = wikitext;
			// TODO: Consider writing changes using a
			// transaction so they can be undone.
			// For now, just mark surface as pre-modified
			tempWikitextEditorData.fromEditedState = true;
		}

		// Store the last-seen selection and pass to the target
		tempWikitextEditorData.initialSourceRange = tempWikitextEditor.getRange();

		tempWikitextEditor.$element.prop( 'readonly', true );
	}

	function teardownTempWikitextEditor() {
		// Destroy widget and placeholder
		tempWikitextEditor.$element.remove();
		mw.libs.ve.tempWikitextEditor = tempWikitextEditor = null;
		tempWikitextEditorData = null;

		$( '#mw-content-text' ).removeClass( 'oo-ui-element-hidden' );
		$( 'html' ).removeClass( 've-tempSourceEditing' );
	}

	function abortLoading() {
		$( 'html' ).removeClass( 've-activated' );
		active = false;
		// Push read tab URL to history
		if ( history.pushState && $( '#ca-view a' ).length ) {
			history.pushState( { tag: 'visualeditor' }, document.title, new mw.Uri( $( '#ca-view a' ).attr( 'href' ) ) );
		}
		clearLoading();
	}

	function onDocumentKeyDown( e ) {
		if ( e.which === 27 /* OO.ui.Keys.ESCAPE */ ) {
			abortLoading();
			e.preventDefault();
		}
	}

	/**
	 * Parse a section value from a query string object
	 *
	 *     @example
	 *     parseSection( uri.query.section )
	 *
	 * @param {string|undefined} section Section value from query object
	 * @return {string|null} Section if valid, null otherwise
	 */
	function parseSection( section ) {
		// Section must be a number, 'new' or 'T-' prefixed
		if ( section && /^(new|\d+|T-\d+)$/.test( section ) ) {
			return section;
		}
		return null;
	}

	/**
	 * Use deferreds to avoid loading and instantiating Target multiple times.
	 *
	 * @private
	 * @param {string} mode Target mode: 'visual' or 'source'
	 * @param {string} section Section to edit
	 * @return {jQuery.Promise}
	 */
	function getTarget( mode, section ) {
		if ( !targetPromise ) {
			// The TargetLoader module is loaded in the bottom queue, so it should have been
			// requested already but it might not have finished loading yet
			targetPromise = mw.loader.using( 'ext.visualEditor.targetLoader' )
				.then( function () {
					mw.libs.ve.targetLoader.addPlugin( function () {
						// Run VisualEditorPreloadModules, but if they fail, we still want to continue
						// loading, so convert failure to success
						return mw.loader.using( conf.preloadModules ).catch(
							function () {
								return $.Deferred().resolve();
							}
						);
					} );
					// Add modules specific to desktop (modules shared between desktop
					// and mobile are already added by TargetLoader)
					[ 'ext.visualEditor.desktopArticleTarget' ]
						// Add requested plugins
						.concat( plugins )
						.forEach( mw.libs.ve.targetLoader.addPlugin );
					plugins = [];
					return mw.libs.ve.targetLoader.loadModules( mode );
				} )
				.then( function () {
					if ( !active ) {
						// Loading was aborted
						// TODO: Make loaders abortable instead of waiting
						targetPromise = null;
						return $.Deferred().reject().promise();
					}

					var target = ve.init.mw.targetFactory.create(
						conf.contentModels[ mw.config.get( 'wgPageContentModel' ) ], {
							modes: availableModes,
							defaultMode: mode
						}
					);
					target.on( 'deactivate', function () {
						active = false;
					} );
					target.on( 'reactivate', function () {
						try {
							// T270331, see below.
							uri = new mw.Uri( null, { arrayParams: true } );
						} catch ( e ) {
							uri = viewUri;
						}

						activateTarget(
							getEditModeFromUri( uri ),
							parseSection( uri.query.section )
						);
					} );
					target.setContainer( $( '#content' ) );
					targetLoaded = true;
					return target;
				}, function ( e ) {
					mw.log.warn( 'VisualEditor failed to load: ' + e );
				} );
		}

		targetPromise.then( function ( target ) {
			target.section = section;
		} );

		return targetPromise;
	}

	function trackActivateStart( initData ) {
		ve.track( 'trace.activate.enter', { mode: initData.mode } );
		// ve.track normally tries to guess the current platform based on
		// ve.init.target. We're in a pre-target-loaded state, so have it
		// hardcode desktop here.
		initData.platform = 'desktop';
		ve.track( 'mwedit.init', initData );
		mw.libs.ve.activationStart = ve.now();
	}

	function getTabMessage( key ) {
		var tabMsgKey = tabMessages[ key ];
		if ( !tabMsgKey && ( key === 'edit' || key === 'create' ) ) {
			// Some skins don't use the default 'edit' and 'create' message keys.
			// e.g. vector-view-edit, vector-view-create
			tabMsgKey = mw.config.get( 'skin' ) + '-view-' + key;
			// The following messages can be used here:
			// * vector-view-edit
			// * vector-view-create
			// * messages for other skins
			if ( !mw.message( tabMsgKey ).exists() ) {
				tabMsgKey = key;
			}
		}
		return mw.msg( tabMsgKey );
	}

	/**
	 * Set the user's new preferred editor
	 *
	 * @param {string} editor Preferred editor, 'visualeditor' or 'wikitext'
	 * @return {jQuery.Promise} Promise which resolves when the preference has been set
	 */
	function setEditorPreference( editor ) {
		// If visual mode isn't available, don't set the editor preference as the
		// user has expressed no choice by opening this editor. (T246259)
		// Strictly speaking the same thing should happen if visual mode is
		// available but source mode isn't, but that is never the case.
		if (
			!init.isVisualAvailable ||
			// T253941: This option does not actually disable the editor, only leaves the tabs/links unchanged
			( conf.disableForAnons && mw.config.get( 'wgUserName' ) === null )
		) {
			return $.Deferred().resolve().promise();
		}

		if ( editor !== 'visualeditor' && editor !== 'wikitext' ) {
			throw new Error( 'setEditorPreference called with invalid option: ', editor );
		}

		var key = pageExists ? 'edit' : 'create',
			sectionKey = 'editsection';

		if (
			mw.config.get( 'wgVisualEditorConfig' ).singleEditTab &&
			tabPreference === 'remember-last'
		) {
			if ( $( '#ca-view-foreign' ).length ) {
				key += 'localdescription';
			}
			if ( editor === 'wikitext' ) {
				key += 'source';
				sectionKey += 'source';
			}

			$( '#ca-edit a' ).text( getTabMessage( key ) );
			$( '.mw-editsection a' ).text( getTabMessage( sectionKey ) );
		}

		mw.cookie.set( 'VEE', editor, { path: '/', expires: 30 * 86400, prefix: '' } );

		// Save user preference if logged in
		if (
			!mw.user.isAnon() &&
			mw.user.options.get( 'visualeditor-editor' ) !== editor
		) {
			// Same as ve.init.target.getLocalApi()
			return new mw.Api().saveOption( 'visualeditor-editor', editor ).then( function () {
				mw.user.options.set( 'visualeditor-editor', editor );
			} );
		}
		return $.Deferred().resolve().promise();
	}

	/**
	 * Scroll to a specific heading before VE loads
	 *
	 * Similar to ve.init.mw.ArticleTarget.prototype.scrollToHeading
	 *
	 * @param {string} section Parsed section (string)
	 */
	function scrollToSection( section ) {
		if ( section === '0' || section === 'new' ) {
			return;
		}

		var $heading;
		$( '#mw-content-text .mw-editsection a:not( .mw-editsection-visualeditor )' ).each( function () {
			var linkUri = new mw.Uri( this.href );
			if ( section === parseSection( linkUri.query.section ) ) {
				$heading = $( this ).closest( 'h1, h2, h3, h4, h5, h6' );
				return false;
			}
		} );
		// When loading on action=edit URLs, there is no page content
		if ( !$heading || !$heading.length ) {
			return;
		}

		var offset = 0;
		var enableVisualSectionEditing = mw.config.get( 'wgVisualEditorConfig' ).enableVisualSectionEditing;
		if ( enableVisualSectionEditing === true || enableVisualSectionEditing === 'desktop' ) {
			// Heading will jump to the top of the page in visual section editing
			offset = $( '#mw-content-text' ).offset().top;
		} else {
			// Align with top of heading margin. Doesn't apply in visual section editing as the margin collapses.
			offset = parseInt( $heading.css( 'margin-top' ) );
		}

		// Support for CSS `scroll-behavior: smooth;` and JS `window.scroll( { behavior: 'smooth' } )`
		// is correlated:
		// * https://caniuse.com/css-scroll-behavior
		// * https://caniuse.com/mdn-api_window_scroll_options_behavior_parameter
		var supportsSmoothScroll = 'scrollBehavior' in document.documentElement.style;
		var newScrollTop = $heading.offset().top - offset - $toolbarPlaceholder.outerHeight();
		if ( supportsSmoothScroll ) {
			window.scroll( {
				top: newScrollTop,
				behavior: 'smooth'
			} );
		} else {
			// Ideally we would use OO.ui.Element.static.getRootScrollableElement here
			// as it has slightly better browser support (Chrome < 60)
			var scrollContainer = document.documentElement;

			$( scrollContainer ).animate( {
				scrollTop: newScrollTop
			} );
		}
	}

	/**
	 * Load and activate the target.
	 *
	 * If you need to call methods on the target before activate is called, call getTarget()
	 * yourself, chain your work onto that promise, and pass that chained promise in as targetPromise.
	 * E.g. `activateTarget( getTarget().then( function( target ) { target.doAThing(); } ) );`
	 *
	 * @private
	 * @param {string} mode Target mode: 'visual' or 'source'
	 * @param {string} [section] Section to edit.
	 *  If visual section editing is not enabled, we will jump to the start of this section, and still
	 *  the heading to prefix the edit summary.
	 * @param {jQuery.Promise} [tPromise] Promise that will be resolved with a ve.init.mw.DesktopArticleTarget
	 * @param {boolean} [modified] The page was been modified before loading (e.g. in source mode)
	 */
	function activateTarget( mode, section, tPromise, modified ) {
		var dataPromise;

		// Only call requestPageData early if the target object isn't there yet.
		// If the target object is there, this is a second or subsequent load, and the
		// internal state of the target object can influence the load request.
		if ( !targetLoaded ) {
			// The TargetLoader module is loaded in the bottom queue, so it should have been
			// requested already but it might not have finished loading yet
			dataPromise = mw.loader.using( 'ext.visualEditor.targetLoader' )
				.then( function () {
					return mw.libs.ve.targetLoader.requestPageData( mode, mw.config.get( 'wgRelevantPageName' ), {
						sessionStore: true,
						section: section,
						oldId: oldId,
						// Should be ve.init.mw.DesktopArticleTarget.static.trackingName, but the
						// class hasn't loaded yet.
						// This is used for stats tracking, so do not change!
						targetName: 'mwTarget',
						modified: modified,
						editintro: uri.query.editintro,
						preload: uri.query.preload,
						preloadparams: uri.query.preloadparams,
						// If switching to visual with modifications, check if we have wikitext to convert
						wikitext: mode === 'visual' && modified ? $( '#wpTextbox1' ).textSelection( 'getContents' ) : undefined
					} );
				} );

			dataPromise
				.then( function ( response ) {
					if (
						// Check target promise hasn't already failed (isLoading=false)
						isLoading &&
						// TODO: Support tempWikitextEditor when section=new (T185633)
						mode === 'source' && section !== 'new' &&
						// Can't use temp editor when recovering an autosave
						!( response.visualeditor && response.visualeditor.recovered )
					) {
						setupTempWikitextEditor( response.visualeditor );
					}
				} )
				.then( incrementLoadingProgress );
		}

		// Do this before section scrolling
		showToolbarPlaceholder();
		mw.hook( 've.activationStart' ).fire();

		var visibleSection = null;
		var visibleSectionOffset = null;
		if ( section === null ) {
			var firstVisibleEditSection = null;
			$( '#firstHeading, #mw-content-text .mw-editsection' ).each( function () {
				var top = this.getBoundingClientRect().top;
				if ( top > 0 ) {
					firstVisibleEditSection = this;
					// break
					return false;
				}
			} );

			if ( firstVisibleEditSection && firstVisibleEditSection.id !== 'firstHeading' ) {
				var firstVisibleSectionLink = firstVisibleEditSection.querySelector( 'a' );
				var linkUri = new mw.Uri( firstVisibleSectionLink.href );
				visibleSection = parseSection( linkUri.query.section );

				var firstVisibleHeading = $( firstVisibleEditSection ).closest( 'h1, h2, h3, h4, h5, h6' )[ 0 ];
				visibleSectionOffset = firstVisibleHeading.getBoundingClientRect().top;
			}
		} else if ( mode === 'visual' ) {
			scrollToSection( section );
		}

		showLoading( mode );
		incrementLoadingProgress();
		active = true;

		tPromise = tPromise || getTarget( mode, section );
		tPromise
			.then( function ( target ) {
				target.visibleSection = visibleSection;
				target.visibleSectionOffset = visibleSectionOffset;

				incrementLoadingProgress();
				// If target was already loaded, ensure the mode is correct
				target.setDefaultMode( mode );
				// syncTempWikitextEditor modified the result object in the dataPromise
				if ( tempWikitextEditor ) {
					syncTempWikitextEditor();
				}
				var activatePromise = target.activate( dataPromise );

				// toolbarSetupDeferred resolves slightly before activatePromise, use done
				// to run in the same paint cycle as the VE toolbar being drawn
				target.toolbarSetupDeferred.done( function () {
					hideToolbarPlaceholder();
				} );

				return activatePromise;
			} )
			.then( function () {
				if ( mode === 'visual' ) {
					// 'mwedit.ready' has already been fired for source mode in setupTempWikitextEditor
					ve.track( 'mwedit.ready', { mode: mode } );
				} else if ( !tempWikitextEditor ) {
					// We're in source mode, but skipped the
					// tempWikitextEditor, so make sure we do relevant
					// tracking / hooks:
					ve.track( 'mwedit.ready', { mode: mode } );
					mw.hook( 've.wikitextInteractive' ).fire();
				}
				ve.track( 'mwedit.loaded', { mode: mode } );
			} )
			.always( clearLoading );
	}

	function activatePageTarget( mode, section, modified ) {
		trackActivateStart( { type: 'page', mechanism: mw.config.get( 'wgArticleId' ) ? 'click' : 'new', mode: mode } );

		if ( !active ) {
			if ( uri.query.action !== 'edit' && !( uri.query.veaction in veactionToMode ) ) {
				if ( history.pushState ) {
					// Replace the current state with one that is tagged as ours, to prevent the
					// back button from breaking when used to exit VE. FIXME: there should be a better
					// way to do this. See also similar code in the DesktopArticleTarget constructor.
					history.replaceState( { tag: 'visualeditor' }, document.title, uri );
					// Set veaction to edit
					history.pushState( { tag: 'visualeditor' }, document.title, mode === 'source' ? veEditSourceUri : veEditUri );
				}

				// Update mw.Uri instance
				uri = veEditUri;
			}

			activateTarget( mode, section, undefined, modified );
		}
	}

	function getLastEditor() {
		// This logic matches VisualEditorHooks::getLastEditor
		var editor = mw.cookie.get( 'VEE', '' );
		// Set editor to user's preference or site's default if …
		if (
			// … user is logged in,
			!mw.user.isAnon() ||
			// … no cookie is set, or
			!editor ||
			// value is invalid.
			!( editor === 'visualeditor' || editor === 'wikitext' )
		) {
			editor = mw.user.options.get( 'visualeditor-editor' );
		}
		return editor;
	}

	function getEditPageEditor() {
		// This logic matches VisualEditorHooks::getEditPageEditor
		// !!+ casts '0' to false
		var isRedLink = !!+uri.query.redlink;
		// On dual-edit-tab wikis, the edit page must mean the user wants wikitext,
		// unless following a redlink
		if ( !mw.config.get( 'wgVisualEditorConfig' ).singleEditTab && !isRedLink ) {
			return 'wikitext';
		}

		switch ( tabPreference ) {
			case 'prefer-ve':
				return 'visualeditor';
			case 'prefer-wt':
				return 'wikitext';
			case 'multi-tab':
				// 'multi-tab'
				// TODO: See VisualEditor.hooks.php
				return isRedLink ?
					getLastEditor() :
					'wikitext';
			case 'remember-last':
			default:
				return getLastEditor();
		}
	}

	function checkPreferenceOrStorage( prefName, storageKey, cookieName ) {
		storageKey = storageKey || prefName;
		cookieName = cookieName || storageKey;
		return mw.user.options.get( prefName ) ||
			(
				mw.user.isAnon() && (
					mw.storage.get( storageKey ) ||
					mw.cookie.get( cookieName, '' )
				)
			);
	}

	function setPreferenceOrStorage( prefName, storageKey, cookieName ) {
		storageKey = storageKey || prefName;
		cookieName = cookieName || storageKey;
		if ( mw.user.isAnon() ) {
			// Try local storage first; if that fails, set a cookie
			if ( !mw.storage.set( storageKey, 1 ) ) {
				mw.cookie.set( cookieName, 1, { path: '/', expires: 30 * 86400, prefix: '' } );
			}
		} else {
			new mw.Api().saveOption( prefName, '1' );
			mw.user.options.set( prefName, '1' );
		}
	}

	conf = mw.config.get( 'wgVisualEditorConfig' );
	tabMessages = conf.tabMessages;
	viewUri = new mw.Uri( mw.util.getUrl( mw.config.get( 'wgRelevantPageName' ) ) );
	try {
		uri = new mw.Uri( null, { arrayParams: true } );
	} catch ( e ) {
		// URI failed to parse, probably because of query string parameters. (T270331)
		// Fall back to the viewUri so that initialization completes.
		// Ideally mw.Uri would ignore invalid parameters or characters.
		uri = viewUri;
	}
	// T156998: Don't trust uri.query.oldid, it'll be wrong if uri.query.diff or uri.query.direction
	// is set to 'next' or 'prev'.
	oldId = mw.config.get( 'wgRevisionId' ) || $( 'input[name=parentRevId]' ).val();
	// wgFlaggedRevsEditLatestRevision is set by FlaggedRevs extension when viewing a stable revision
	if ( oldId === mw.config.get( 'wgCurRevisionId' ) || mw.config.get( 'wgFlaggedRevsEditLatestRevision' ) ) {
		// The page may have been edited by someone else after we loaded it, setting this to "undefined"
		// indicates that we should load the actual latest revision.
		oldId = undefined;
	}
	pageExists = !!mw.config.get( 'wgRelevantArticleId' );
	var isViewPage = mw.config.get( 'wgIsArticle' ) && !( 'diff' in uri.query );
	var isEditPage = mw.config.get( 'wgAction' ) === 'edit' || mw.config.get( 'wgAction' ) === 'submit';
	var pageCanLoadEditor = isViewPage || isEditPage;

	// Cast "0" (T89513)
	var enable = !!+mw.user.options.get( 'visualeditor-enable' );
	var tempdisable = !!+mw.user.options.get( 'visualeditor-betatempdisable' );
	var autodisable = !!+mw.user.options.get( 'visualeditor-autodisable' );
	tabPreference = mw.user.options.get( 'visualeditor-tabs' );

	function isOnlyTabVE() {
		return conf.singleEditTab && getEditPageEditor() === 'visualeditor';
	}

	function isOnlyTabWikitext() {
		return conf.singleEditTab && getEditPageEditor() === 'wikitext';
	}

	init = {
		unsupportedList: conf.unsupportedList,

		/**
		 * Add a plugin module or function.
		 *
		 * Plugins are run after VisualEditor is loaded, but before it is initialized. This allows
		 * plugins to add classes and register them with the factories and registries.
		 *
		 * The parameter to this function can be a ResourceLoader module name or a function.
		 *
		 * If it's a module name, it will be loaded together with the VisualEditor core modules when
		 * VE is loaded. No special care is taken to ensure that the module runs after the VE
		 * classes are loaded, so if this is desired, the module should depend on
		 * ext.visualEditor.core .
		 *
		 * If it's a function, it will be invoked once the VisualEditor core modules and any
		 * plugin modules registered through this function have been loaded, but before the editor
		 * is intialized. The function can optionally return a jQuery.Promise . VisualEditor will
		 * only be initialized once all promises returned by plugin functions have been resolved.
		 *
		 *     // Register ResourceLoader module
		 *     mw.libs.ve.addPlugin( 'ext.gadget.foobar' );
		 *
		 *     // Register a callback
		 *     mw.libs.ve.addPlugin( function ( target ) {
		 *         ve.dm.Foobar = .....
		 *     } );
		 *
		 *     // Register a callback that loads another script
		 *     mw.libs.ve.addPlugin( function () {
		 *         return $.getScript( 'http://example.com/foobar.js' );
		 *     } );
		 *
		 * @param {string|Function} plugin Module name or callback that optionally returns a promise
		 */
		addPlugin: function ( plugin ) {
			plugins.push( plugin );
		},

		/**
		 * Adjust edit page links in the current document
		 *
		 * This will run multiple times in a page lifecycle, notably when the
		 * page first loads and after post-save content replacement occurs. It
		 * needs to avoid doing anything which will cause problems if it's run
		 * twice or more.
		 */
		setupEditLinks: function () {
			// NWE
			if ( init.isWikitextAvailable && !isOnlyTabVE() ) {
				$(
					// Edit section links, except VE ones when both editors visible
					'.mw-editsection a:not( .mw-editsection-visualeditor ),' +
					// Edit tab
					'#ca-edit a,' +
					// Add section is currently a wikitext-only feature
					'#ca-addsection a'
				).each( function () {
					var linkUri;
					try {
						linkUri = new mw.Uri( this.href );
					} catch ( e ) {
						// T66884
						return;
					}
					if ( 'action' in linkUri.query ) {
						delete linkUri.query.action;
						linkUri.query.veaction = 'editsource';
						$( this ).attr( 'href', linkUri.toString() );
					}
				} );
			}

			// Set up the tabs appropriately if the user has VE on
			if ( init.isAvailable ) {
				// … on two-edit-tab wikis, or single-edit-tab wikis, where the user wants both …
				if (
					!init.isSingleEditTab && init.isVisualAvailable &&
					// T253941: This option does not actually disable the editor, only leaves the tabs/links unchanged
					!( conf.disableForAnons && mw.config.get( 'wgUserName' ) === null )
				) {
					// … set the skin up with both tabs and both section edit links.
					init.setupMultiTabSkin();
				} else if (
					pageCanLoadEditor && (
						( init.isVisualAvailable && isOnlyTabVE() ) ||
						( init.isWikitextAvailable && isOnlyTabWikitext() )
					)
				) {
					// … on single-edit-tab wikis, where VE or NWE is the user's preferred editor
					// Handle section edit link clicks
					$( '.mw-editsection a' ).off( '.ve-target' ).on( 'click.ve-target', function ( e ) {
						// isOnlyTabVE is computed on click as it may have changed since load
						init.onEditSectionLinkClick( isOnlyTabVE() ? 'visual' : 'source', e );
					} );
					// Allow instant switching to edit mode, without refresh
					$( '#ca-edit' ).off( '.ve-target' ).on( 'click.ve-target', function ( e ) {
						init.onEditTabClick( isOnlyTabVE() ? 'visual' : 'source', e );
					} );
				}
			}
		},

		setupMultiTabSkin: function () {
			init.setupMultiTabs();
			init.setupMultiSectionLinks();
		},

		setupMultiTabs: function () {
			var action = pageExists ? 'edit' : 'create',
				isMinerva = mw.config.get( 'skin' ) === 'minerva',
				pTabsId = isMinerva ? 'page-actions' :
					$( '#p-views' ).length ? 'p-views' : 'p-cactions',
				// Minerva puts the '#ca-...' ids on <a> nodes
				$caSource = $( 'li#ca-viewsource' ),
				$caEdit = $( 'li#ca-edit, li#page-actions-edit' ),
				$caVeEdit = $( 'li#ca-ve-edit' ),
				$caEditLink = $caEdit.find( 'a' ),
				$caVeEditLink = $caVeEdit.find( 'a' ),
				caVeEditNextnode =
					( conf.tabPosition === 'before' ) ?
						$caEdit.get( 0 ) :
						$caEdit.next().get( 0 );

			if ( !$caVeEdit.length ) {
				// The below duplicates the functionality of VisualEditorHooks::onSkinTemplateNavigation()
				// in case we're running on a cached page that doesn't have these tabs yet.

				// Alter the edit tab (#ca-edit)
				if ( $( '#ca-view-foreign' ).length ) {
					if ( tabMessages[ action + 'localdescriptionsource' ] !== null ) {
						// The following messages can be used here:
						// * editlocaldescriptionsource
						// * createlocaldescriptionsource
						$caEditLink.text( mw.msg( tabMessages[ action + 'localdescriptionsource' ] ) );
					}
				} else {
					if ( tabMessages[ action + 'source' ] !== null ) {
						// The following messages can be used here:
						// * editsource
						// * createsource
						$caEditLink.text( mw.msg( tabMessages[ action + 'source' ] ) );
					}
				}

				// If there is no edit tab or a view-source tab,
				// the user doesn't have permission to edit.
				if ( $caEdit.length && !$caSource.length ) {
					// Add the VisualEditor tab (#ca-ve-edit)
					var caVeEdit = mw.util.addPortletLink(
						pTabsId,
						// Use url instead of '#'.
						// So that 1) one can always open it in a new tab, even when
						// onEditTabClick is bound.
						// 2) when onEditTabClick is not bound (!pageCanLoadEditor) it will
						// just work.
						veEditUri,
						getTabMessage( action ),
						'ca-ve-edit',
						mw.msg( 'tooltip-ca-ve-edit' ),
						mw.msg( 'accesskey-ca-ve-edit' ),
						caVeEditNextnode
					);

					$caVeEdit = $( caVeEdit );
					if ( isMinerva ) {
						$caVeEdit.find( '.mw-ui-icon' ).addClass( 'mw-ui-icon-wikimedia-edit-base20' );
					}
				}
			} else if ( $caEdit.length && $caVeEdit.length ) {
				// Make the state of the page consistent with the config if needed
				if ( conf.tabPosition === 'before' ) {
					if ( $caEdit.next()[ 0 ] === $caVeEdit[ 0 ] ) {
						$caVeEdit.after( $caEdit );
					}
				} else {
					if ( $caVeEdit.next()[ 0 ] === $caEdit[ 0 ] ) {
						$caEdit.after( $caVeEdit );
					}
				}
				$caVeEditLink.text( getTabMessage( action ) );
			}

			// If the edit tab is hidden, remove it.
			if ( !( init.isVisualAvailable ) ) {
				$caVeEdit.remove();
			} else if ( pageCanLoadEditor ) {
				// Allow instant switching to edit mode, without refresh
				$caVeEdit.off( '.ve-target' ).on( 'click.ve-target', init.onEditTabClick.bind( init, 'visual' ) );
			}
			if ( pageCanLoadEditor ) {
				// Always bind "Edit source" tab, because we want to handle switching with changes
				$caEdit.off( '.ve-target' ).on( 'click.ve-target', init.onEditTabClick.bind( init, 'source' ) );
			}
			if ( pageCanLoadEditor && init.isWikitextAvailable ) {
				// Only bind "Add topic" tab if NWE is available, because VE doesn't support section
				// so we never have to switch from it when editing a section
				$( '#ca-addsection' ).off( '.ve-target' ).on( 'click.ve-target', init.onEditTabClick.bind( init, 'source' ) );
			}

			if ( isMinerva ) {
				// Minerva hides the link text - display tiny icons instead
				mw.loader.load( [ 'oojs-ui.styles.icons-editing-advanced', 'oojs-ui.styles.icons-accessibility' ] );
				$caEdit.find( '.mw-ui-icon' ).each( function () {
					// Use <b> to dodge some styles targeting <span> to hide labels
					var $icon = $( '<b>' ).addClass( 'mw-ui-icon mw-ui-icon-element mw-ui-icon-wikiText' );
					$( this ).addClass( 've-edit-source' ).prepend( $icon );
				} );
				$caVeEdit.find( '.mw-ui-icon' ).each( function () {
					var $icon = $( '<b>' ).addClass( 'mw-ui-icon mw-ui-icon-element mw-ui-icon-eye' );
					$( this ).addClass( 've-edit-visual' ).prepend( $icon );
				} );
			}

			if ( init.isVisualAvailable ) {
				if ( conf.tabPosition === 'before' ) {
					$caEdit.addClass( 'collapsible' );
				} else {
					$caVeEdit.addClass( 'collapsible' );
				}
			}
		},

		setupMultiSectionLinks: function () {
			var $editsections = $( '#mw-content-text .mw-editsection' ),
				bodyDir = $( document.body ).css( 'direction' );

			// Match direction of the user interface
			// TODO: Why is this needed? It seems to work fine without.
			if ( $editsections.css( 'direction' ) !== bodyDir ) {
				// Avoid creating inline style attributes if the inherited value is already correct
				$editsections.css( 'direction', bodyDir );
			}

			var isMinerva = mw.config.get( 'skin' ) === 'minerva';

			// The "visibility" css construct ensures we always occupy the same space in the layout.
			// This prevents the heading from changing its wrap when the user toggles editSourceLink.
			if ( $editsections.find( '.mw-editsection-visualeditor' ).length === 0 ) {
				// If PHP didn't build the section edit links (because of caching), build them
				$editsections.each( function () {
					var $editsection = $( this ),
						$editSourceLink = $editsection.find( 'a' ).eq( 0 ),
						$editLink = $editSourceLink.clone(),
						$divider = $( '<span>' ),
						dividerText = mw.msg( 'pipe-separator' );

					// The following messages can be used here:
					// * visualeditor-ca-editsource-section
					// * config value of tabMessages.editsectionsource
					$editSourceLink.text( mw.msg( tabMessages.editsectionsource ) );
					// The following messages can be used here:
					// * editsection
					// * config value of tabMessages.editsections
					$editLink.text( mw.msg( tabMessages.editsection ) );

					$divider
						.addClass( 'mw-editsection-divider' )
						.text( dividerText );
					// Don't mess with section edit links on foreign file description pages (T56259)
					if ( !$( '#ca-view-foreign' ).length ) {
						$editLink
							.attr( 'href', function ( i, href ) {
								var veUri = new mw.Uri( veEditUri );
								veUri.query.section = ( new mw.Uri( href ) ).query.section;
								return veUri.toString();
							} )
							.addClass( 'mw-editsection-visualeditor' );

						if ( conf.tabPosition === 'before' ) {
							$editSourceLink.before( $editLink, $divider );
							if ( isMinerva ) {
								$editLink.removeClass( 'mw-ui-icon-flush-right' );
							}
						} else {
							$editSourceLink.after( $divider, $editLink );
							if ( isMinerva ) {
								$editSourceLink.removeClass( 'mw-ui-icon-flush-right' );
							}
						}
					}
				} );
			}

			if ( isMinerva ) {
				// Minerva hides the link text - display tiny icons instead
				mw.loader.load( [ 'oojs-ui.styles.icons-editing-advanced', 'oojs-ui.styles.icons-accessibility' ] );
				$( '#mw-content-text .mw-editsection a:not(.mw-editsection-visualeditor)' ).each( function () {
					// Use <b> to dodge some styles targeting <span> to hide labels
					var $icon = $( '<b>' ).addClass( 'mw-ui-icon mw-ui-icon-element mw-ui-icon-wikiText' );
					$( this ).addClass( 've-edit-source' ).prepend( $icon );
				} );
				$( '#mw-content-text .mw-editsection a.mw-editsection-visualeditor' ).each( function () {
					var $icon = $( '<b>' ).addClass( 'mw-ui-icon mw-ui-icon-element mw-ui-icon-eye' );
					$( this ).addClass( 've-edit-visual' ).prepend( $icon );
				} );
			}

			if ( pageCanLoadEditor ) {
				// Only init without refresh if we're on a view page. Though section edit links
				// are rarely shown on non-view pages, they appear in one other case, namely
				// when on a diff against the latest version of a page. In that case we mustn't
				// init without refresh as that'd initialise for the wrong rev id (T52925)
				// and would preserve the wrong DOM with a diff on top.
				$editsections.find( '.mw-editsection-visualeditor' )
					.off( '.ve-target' ).on( 'click.ve-target', init.onEditSectionLinkClick.bind( init, 'visual' ) );
				if ( init.isWikitextAvailable ) {
					// TOOD: Make this less fragile
					$editsections.find( 'a:not( .mw-editsection-visualeditor )' )
						.off( '.ve-target' ).on( 'click.ve-target', init.onEditSectionLinkClick.bind( init, 'source' ) );
				}
			}
		},

		/**
		 * Check whether a jQuery event represents a plain left click, without
		 * any modifiers or a programmatically triggered click.
		 *
		 * This is a duplicate of a function in ve.utils, because this file runs
		 * before any of VE core or OOui has been loaded.
		 *
		 * @param {jQuery.Event} e
		 * @return {boolean} Whether it was an unmodified left click
		 */
		isUnmodifiedLeftClick: function ( e ) {
			return e && ( (
				e.which && e.which === 1 && !( e.shiftKey || e.altKey || e.ctrlKey || e.metaKey )
			) || e.isTrigger );
		},

		onEditTabClick: function ( mode, e ) {
			if ( !init.isUnmodifiedLeftClick( e ) ) {
				return;
			}
			if ( !active && mode === 'source' && !init.isWikitextAvailable ) {
				// We're not active so we don't need to manage a switch, and
				// we don't have source mode available so we don't need to
				// activate VE. Just follow the link.
				return;
			}
			e.preventDefault();
			if ( isLoading ) {
				return;
			}

			var section = $( e.target ).closest( '#ca-addsection' ).length ? 'new' : null;

			if ( active ) {
				targetPromise.done( function ( target ) {
					if ( target.getDefaultMode() === 'source' ) {
						if ( mode === 'visual' ) {
							target.switchToVisualEditor();
						} else if ( mode === 'source' ) {
							// Requested section may have changed --
							// switchToWikitextSection will do nothing if the
							// section is unchanged.
							target.switchToWikitextSection( section );
						}
					} else if ( target.getDefaultMode() === 'visual' ) {
						if ( mode === 'source' ) {
							if ( section ) {
								// switching from visual via the "add section" tab
								target.switchToWikitextSection( section );
							} else {
								target.editSource();
							}
						}
						// Visual-to-visual doesn't need to do anything,
						// because we don't have any section concerns. Just
						// no-op it.
					}
				} );
			} else {
				if ( section !== null ) {
					this.onEditSectionLinkClick( mode, e, section );
				} else {
					init.activateVe( mode );
				}
			}
		},

		activateVe: function ( mode ) {
			var wikitext = $( '#wpTextbox1' ).textSelection( 'getContents' ),
				sectionVal = $( 'input[name=wpSection]' ).val(),
				section = sectionVal !== '' && sectionVal !== undefined ? sectionVal : null,
				config = mw.config.get( 'wgVisualEditorConfig' ),
				canSwitch = config.fullRestbaseUrl || config.allowLossySwitching,
				modified = mw.config.get( 'wgAction' ) === 'submit' ||
					(
						mw.config.get( 'wgAction' ) === 'edit' &&
						wikitext !== initialWikitext
					);

			// Close any open jQuery.UI dialogs (e.g. WikiEditor's find and replace)
			if ( $.fn.dialog ) {
				$( '.ui-dialog-content' ).dialog( 'close' );
			}

			// Release the edit warning on #wpTextbox1 which was setup in mediawiki.action.edit.editWarning.js
			function releaseOldEditWarning() {
				$( window ).off( 'beforeunload.editwarning' );
			}

			if ( modified && !canSwitch ) {
				mw.loader.using( 'ext.visualEditor.switching' ).done( function () {
					var windowManager = new OO.ui.WindowManager(),
						switchWindow = new mw.libs.ve.SwitchConfirmDialog();

					$( document.body ).append( windowManager.$element );
					windowManager.addWindows( [ switchWindow ] );
					windowManager.openWindow( switchWindow )
						.closed.then( function ( data ) {
							var oldUri;
							// TODO: windowManager.destroy()?
							if ( data && data.action === 'discard' ) {
								releaseOldEditWarning();
								setEditorPreference( 'visualeditor' );
								oldUri = veEditUri.clone();
								delete oldUri.query.veswitched;
								location.href = oldUri.extend( { wteswitched: 1 } );
							}
						} );
				} );
			} else {
				releaseOldEditWarning();
				activatePageTarget( mode, section, modified );
			}
		},

		/**
		 * Handle section edit links being clicked
		 *
		 * @param {string} mode Edit mode
		 * @param {jQuery.Event} e Click event
		 * @param {string} [section] Override edit section, taken from link URL if not specified
		 */
		onEditSectionLinkClick: function ( mode, e, section ) {
			var linkUri = new mw.Uri( e.target.href ),
				title = mw.Title.newFromText( linkUri.query.title || '' );

			if (
				// Modified click (e.g. ctrl+click)
				!init.isUnmodifiedLeftClick( e ) ||
				// Not an edit action
				!( 'action' in linkUri.query || 'veaction' in linkUri.query ) ||
				// Edit target is on another host (e.g. commons file)
				linkUri.getHostPort() !== location.host ||
				// Title param doesn't match current page
				title && title.getPrefixedText() !== new mw.Title( mw.config.get( 'wgRelevantPageName' ) ).getPrefixedText()
			) {
				return;
			}
			e.preventDefault();
			if ( isLoading ) {
				return;
			}

			trackActivateStart( { type: 'section', mechanism: section === 'new' ? 'new' : 'click', mode: mode } );

			if ( history.pushState && !( linkUri.query.veaction in veactionToMode ) ) {
				// Replace the current state with one that is tagged as ours, to prevent the
				// back button from breaking when used to exit VE. FIXME: there should be a better
				// way to do this. See also similar code in the DesktopArticleTarget constructor.
				history.replaceState( { tag: 'visualeditor' }, document.title, linkUri );
				// Change the state to the href of the section link that was clicked. This saves
				// us from having to figure out the section number again.
				history.pushState( { tag: 'visualeditor' }, document.title, this.href );
			}

			// Use section from URL
			if ( section === undefined ) {
				section = parseSection( linkUri.query.section );
			}
			var tPromise = getTarget( mode, section );
			activateTarget( mode, section, tPromise );
		},

		/**
		 * Check whether the welcome dialog should be shown.
		 *
		 * The welcome dialog can be disabled in configuration; or by calling disableWelcomeDialog();
		 * or using a query string parameter; or if we've recorded that we've already shown it before
		 * in a user preference, local storage or a cookie.
		 *
		 * @return {boolean}
		 */
		shouldShowWelcomeDialog: function () {
			return !(
				// Disabled in config?
				!mw.config.get( 'wgVisualEditorConfig' ).showBetaWelcome ||
				// Disabled for the current request?
				this.isWelcomeDialogSuppressed() ||
				// Hidden using preferences, local storage or cookie?
				checkPreferenceOrStorage( 'visualeditor-hidebetawelcome', 've-beta-welcome-dialog' )
			);
		},

		/**
		 * Check whether the welcome dialog is temporarily disabled.
		 *
		 * @return {boolean}
		 */
		isWelcomeDialogSuppressed: function () {
			return !!(
				// Disabled by calling disableWelcomeDialog()?
				welcomeDialogDisabled ||
				// Hidden using URL parameter?
				'vehidebetadialog' in new mw.Uri().query ||
				// Check for deprecated hidewelcomedialog parameter (T249954)
				'hidewelcomedialog' in new mw.Uri().query
			);
		},

		/**
		 * Record that we've already shown the welcome dialog to this user, so that it won't be shown
		 * to them again.
		 *
		 * Uses a preference for logged-in users; uses local storage or a cookie for anonymous users.
		 */
		stopShowingWelcomeDialog: function () {
			setPreferenceOrStorage( 'visualeditor-hidebetawelcome', 've-beta-welcome-dialog' );
		},

		/**
		 * Prevent the welcome dialog from being shown on this page view only.
		 *
		 * Causes shouldShowWelcomeDialog() to return false, but doesn't save anything to preferences
		 * or local storage, so future page views are not affected.
		 */
		disableWelcomeDialog: function () {
			welcomeDialogDisabled = true;
		},

		/**
		 * Check whether the user education popups (ve.ui.MWEducationPopupWidget) should be shown.
		 *
		 * The education popups can be disabled by calling disableWelcomeDialog(), or if we've
		 * recorded that we've already shown it before in a user preference, local storage or a cookie.
		 *
		 * @return {boolean}
		 */
		shouldShowEducationPopups: function () {
			return !(
				// Disabled by calling disableEducationPopups()?
				educationPopupsDisabled ||
				// Hidden using preferences, local storage, or cookie?
				checkPreferenceOrStorage( 'visualeditor-hideusered', 've-hideusered' )
			);
		},

		/**
		 * Record that we've already shown the education popups to this user, so that it won't be
		 * shown to them again.
		 *
		 * Uses a preference for logged-in users; uses local storage or a cookie for anonymous users.
		 */
		stopShowingEducationPopups: function () {
			setPreferenceOrStorage( 'visualeditor-hideusered', 've-hideusered' );
		},

		/**
		 * Prevent the education popups from being shown on this page view only.
		 *
		 * Causes shouldShowEducationPopups() to return false, but doesn't save anything to
		 * preferences or local storage, so future page views are not affected.
		 */
		disableEducationPopups: function () {
			educationPopupsDisabled = true;
		}
	};

	init.isSingleEditTab = conf.singleEditTab && tabPreference !== 'multi-tab';

	// On a view page, extend the current URI so parameters like oldid are carried over
	// On a non-view page, use viewUri
	var veEditBaseUri = pageCanLoadEditor ? uri : viewUri;
	if ( init.isSingleEditTab ) {
		veEditSourceUri = veEditUri = veEditBaseUri.clone().extend( { action: 'edit' } );
		delete veEditUri.query.veaction;
	} else {
		veEditUri = veEditBaseUri.clone().extend( { veaction: 'edit' } );
		veEditSourceUri = veEditBaseUri.clone().extend( { veaction: 'editsource' } );
		delete veEditUri.query.action;
		delete veEditSourceUri.query.action;
	}
	if ( oldId ) {
		veEditUri.extend( { oldid: oldId } );
	}

	// Whether VisualEditor should be available for the current user, page, wiki, mediawiki skin,
	// browser etc.
	init.isAvailable = (
		VisualEditorSupportCheck() &&
		( ( 'vesupported' in uri.query ) || !$.client.test( init.unsupportedList, null, true ) )
		// Extensions can disable VE in certain circumstances using the VisualEditorBeforeEditor hook (T174180)
	);

	var enabledForUser = (
		// User has 'visualeditor-enable' preference enabled (for alpha opt-in)
		// User has 'visualeditor-betatempdisable' preference disabled
		// User has 'visualeditor-autodisable' preference disabled
		( conf.isBeta ? enable : !tempdisable ) && !autodisable
	);

	// Duplicated in VisualEditor.hooks.php#isVisualAvailable()
	init.isVisualAvailable = (
		init.isAvailable &&

		// If forced by the URL parameter, skip the namespace check (T221892) and preference check
		( uri.query.veaction === 'edit' || (
			// Only in enabled namespaces
			conf.namespaces.indexOf( new mw.Title( mw.config.get( 'wgRelevantPageName' ) ).getNamespaceId() ) !== -1 &&

			// Enabled per user preferences
			enabledForUser
		) ) &&

		// Only for pages with a supported content model
		Object.prototype.hasOwnProperty.call( conf.contentModels, mw.config.get( 'wgPageContentModel' ) )
	);

	// Duplicated in VisualEditor.hooks.php#isWikitextAvailable()
	init.isWikitextAvailable = (
		init.isAvailable &&

		// Enabled on site
		conf.enableWikitext &&

		// User preference
		mw.user.options.get( 'visualeditor-newwikitext' ) &&

		// Only on wikitext pages
		mw.config.get( 'wgPageContentModel' ) === 'wikitext'
	);

	if ( init.isVisualAvailable ) {
		availableModes.push( 'visual' );
	}

	if ( init.isWikitextAvailable ) {
		availableModes.push( 'source' );
	}

	// FIXME: We should do this more elegantly
	init.setEditorPreference = setEditorPreference;

	// Note: Though VisualEditor itself only needs this exposure for a very small reason
	// (namely to access init.unsupportedList from the unit tests...) this has become one of the nicest
	// ways to easily detect whether the VisualEditor initialisation code is present.
	//
	// The VE global was once available always, but now that platform integration initialisation
	// is properly separated, it doesn't exist until the platform loads VisualEditor core.
	//
	// Most of mw.libs.ve is considered subject to change and private.  An exception is that
	// mw.libs.ve.isVisualAvailable is public, and indicates whether the VE editor itself can be loaded
	// on this page. See above for why it may be false.
	mw.libs.ve = $.extend( mw.libs.ve || {}, init );

	if ( init.isVisualAvailable ) {
		$( 'html' ).addClass( 've-available' );
	} else {
		$( 'html' ).addClass( 've-not-available' );
		// Don't return here because we do want the skin setup to consistently happen
		// for e.g. "Edit" > "Edit source" even when VE is not available.
	}

	function isSupportedEditPage( editUri ) {
		return configData.unsupportedEditParams.every( function ( param ) {
			return editUri.query[ param ] === undefined;
		} );
	}

	function getEditModeFromUri( editUri ) {
		if ( mw.config.get( 'wgDiscussionToolsStartNewTopicTool' ) ) {
			// Avoid conflicts with DiscussionTools
			return false;
		}
		// On view pages if veaction is correctly set
		var m = veactionToMode[ editUri.query.veaction ];
		if ( isViewPage && init.isAvailable && availableModes.indexOf( m ) !== -1 ) {
			return m;
		}
		// Edit pages
		if ( isEditPage && isSupportedEditPage( editUri ) ) {
			// Just did a discard-switch from wikitext editor to VE (in no RESTBase mode)
			if ( editUri.query.wteswitched === '1' ) {
				return init.isVisualAvailable ? 'visual' : null;
			}
			// User has disabled VE, or we are in view source only mode, or we have landed here with posted data
			if ( !enabledForUser || $( '#ca-viewsource' ).length || mw.config.get( 'wgAction' ) === 'submit' ) {
				return null;
			}
			switch ( getEditPageEditor() ) {
				case 'visualeditor':
					if ( init.isVisualAvailable ) {
						return 'visual';
					}
					if ( init.isWikitextAvailable ) {
						return 'source';
					}
					return null;

				case 'wikitext':
					return init.isWikitextAvailable ? 'source' : null;
			}
		}
		return null;
	}

	$( function () {
		var showWikitextWelcome = true,
			section = parseSection( uri.query.section );

		var requiredSkinElements =
			$( '#content' ).length &&
			$( '#mw-content-text' ).length &&
			// A link to open the editor is technically not necessary if it's going to open itself
			( isEditPage || $( '#ca-edit, #ca-viewsource' ).length );

		if ( uri.query.action === 'edit' && $( '#wpTextbox1' ).length ) {
			initialWikitext = $( '#wpTextbox1' ).textSelection( 'getContents' );
		}

		if ( init.isAvailable && pageCanLoadEditor && !requiredSkinElements ) {
			mw.log.warn(
				'Your skin is incompatible with VisualEditor. ' +
				'See https://www.mediawiki.org/wiki/Extension:VisualEditor/Skin_requirements for the requirements.'
			);
		} else if ( init.isAvailable ) {
			var mode = getEditModeFromUri( uri );
			if ( mode ) {
				showWikitextWelcome = false;
				trackActivateStart( {
					type: section === null ? 'page' : 'section',
					mechanism: ( section === 'new' || !mw.config.get( 'wgArticleId' ) ) ? 'url-new' : 'url',
					mode: mode
				} );
				activateTarget( mode, section );
			} else if (
				init.isVisualAvailable &&
				pageCanLoadEditor &&
				init.isSingleEditTab
			) {
				// In single edit tab mode we never have an edit tab
				// with accesskey 'v' so create one
				$( document.body ).append(
					$( '<a>' )
						.attr( { accesskey: mw.msg( 'accesskey-ca-ve-edit' ), href: veEditUri } )
						// Accesskey fires a click event
						.on( 'click.ve-target', init.onEditTabClick.bind( init, 'visual' ) )
						.addClass( 'oo-ui-element-hidden' )
				);
			}

			// Add the switch button to WikiEditor on edit pages
			if (
				init.isVisualAvailable &&
				isEditPage &&
				$( '#wpTextbox1' ).length
			) {
				mw.loader.load( 'ext.visualEditor.switching' );
				$( '#wpTextbox1' ).on( 'wikiEditor-toolbar-doneInitialSections', function () {
					mw.loader.using( 'ext.visualEditor.switching' ).done( function () {
						var windowManager, editingTabDialog, switchToolbar, popup,
							showPopup = !!uri.query.veswitched && !mw.user.options.get( 'visualeditor-hidesourceswitchpopup' ),
							toolFactory = new OO.ui.ToolFactory(),
							toolGroupFactory = new OO.ui.ToolGroupFactory();

						toolFactory.register( mw.libs.ve.MWEditModeVisualTool );
						toolFactory.register( mw.libs.ve.MWEditModeSourceTool );
						switchToolbar = new OO.ui.Toolbar( toolFactory, toolGroupFactory, {
							classes: [ 've-init-mw-editSwitch' ]
						} );

						switchToolbar.on( 'switchEditor', function ( m ) {
							if ( m === 'visual' ) {
								init.activateVe( 'visual' );
								$( '#wpTextbox1' ).trigger( 'wikiEditor-switching-visualeditor' );
							}
						} );

						switchToolbar.setup( [ {
							name: 'editMode',
							type: 'list',
							icon: 'edit',
							title: mw.msg( 'visualeditor-mweditmode-tooltip' ),
							label: mw.msg( 'visualeditor-mweditmode-tooltip' ),
							invisibleLabel: true,
							include: [ 'editModeVisual', 'editModeSource' ]
						} ] );

						popup = new mw.libs.ve.SwitchPopupWidget( 'source' );

						switchToolbar.tools.editModeVisual.toolGroup.$element.append( popup.$element );
						switchToolbar.emit( 'updateState' );

						$( '.wikiEditor-ui-toolbar' ).prepend( switchToolbar.$element );
						popup.toggle( showPopup );

						// Duplicate of this code in ve.init.mw.DesktopArticleTarget.js
						// eslint-disable-next-line no-jquery/no-class-state
						if ( $( '#ca-edit' ).hasClass( 'visualeditor-showtabdialog' ) ) {
							$( '#ca-edit' ).removeClass( 'visualeditor-showtabdialog' );
							// Set up a temporary window manager
							windowManager = new OO.ui.WindowManager();
							$( document.body ).append( windowManager.$element );
							editingTabDialog = new mw.libs.ve.EditingTabDialog();
							windowManager.addWindows( [ editingTabDialog ] );
							windowManager.openWindow( editingTabDialog )
								.closed.then( function ( data ) {
									// Detach the temporary window manager
									windowManager.destroy();

									if ( data && data.action === 'prefer-ve' ) {
										location.href = veEditUri;
									} else if ( data && data.action === 'multi-tab' ) {
										location.reload();
									}
								} );
						}
					} );
				} );

				// Remember that the user wanted wikitext, at least this time
				mw.libs.ve.setEditorPreference( 'wikitext' );

				// If the user has loaded WikiEditor, clear any auto-save state they
				// may have from a previous VE session
				// We don't have access to the VE session storage methods, but invalidating
				// the docstate is sufficient to prevent the data from being used.
				mw.storage.session.remove( 've-docstate' );
			}

			init.setupEditLinks();
		}

		if (
			pageCanLoadEditor &&
			showWikitextWelcome &&
			// At least one editor is available (T201928)
			( init.isVisualAvailable || init.isWikitextAvailable || $( '#wpTextbox1' ).length ) &&
			isEditPage &&
			init.shouldShowWelcomeDialog() &&
			(
				// Not on protected pages
				mw.config.get( 'wgIsProbablyEditable' ) ||
				mw.config.get( 'wgRelevantPageIsProbablyEditable' )
			)
		) {
			mw.loader.using( 'ext.visualEditor.welcome' ).done( function () {
				var windowManager, welcomeDialog;
				// Check shouldShowWelcomeDialog() again: any code that might have called
				// stopShowingWelcomeDialog() wouldn't have had an opportunity to do that
				// yet by the first time we checked
				if ( !init.shouldShowWelcomeDialog() ) {
					return;
				}
				windowManager = new OO.ui.WindowManager();
				welcomeDialog = new mw.libs.ve.WelcomeDialog();
				$( document.body ).append( windowManager.$element );
				windowManager.addWindows( [ welcomeDialog ] );
				windowManager.openWindow(
					welcomeDialog,
					{
						switchable: init.isVisualAvailable,
						editor: 'source'
					}
				)
					.closed.then( function ( data ) {
						windowManager.destroy();
						if ( data && data.action === 'switch-ve' ) {
							init.activateVe( 'visual' );
						}
					} );

				init.stopShowingWelcomeDialog();
			} );
		}

		if ( uri.query.venotify ) {
			// Load postEdit code to execute the queued event below, which will handle it once it arrives
			mw.loader.load( 'mediawiki.action.view.postEdit' );

			var notify = uri.query.venotify;
			if ( notify === 'saved' ) {
				notify = mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ? 'published' : 'saved';
			}
			mw.hook( 'postEdit' ).fire( {
				// The following messages can be used here:
				// * postedit-confirmation-published
				// * postedit-confirmation-saved
				// * postedit-confirmation-created
				// * postedit-confirmation-restored
				message: mw.msg( 'postedit-confirmation-' + notify, mw.user )
			} );

			delete uri.query.venotify;
			// Get rid of the ?venotify= from the URL
			if ( history.replaceState ) {
				history.replaceState( null, document.title, uri );
			}
		}
	} );
}() );
