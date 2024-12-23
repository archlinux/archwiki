'use strict';

const featuresEnabled = mw.config.get( 'wgDiscussionToolsFeaturesEnabled' ) || {},
	MemoryStorage = require( './MemoryStorage.js' ),
	STORAGE_EXPIRY = 60 * 60 * 24 * 30,
	Parser = require( './Parser.js' ),
	ThreadItemSet = require( './ThreadItemSet.js' ),
	CommentDetails = require( './CommentDetails.js' ),
	HeadingItem = require( './HeadingItem.js' ),
	ReplyLinksController = require( './ReplyLinksController.js' ),
	utils = require( './utils.js' ),
	highlighter = require( './highlighter.js' ),
	topicSubscriptions = require( './topicsubscriptions.js' ),
	permalinks = require( './permalinks.js' ),
	defaultEditMode = mw.user.options.get( 'discussiontools-editmode' ) || mw.config.get( 'wgDiscussionToolsFallbackEditMode' ),
	defaultVisual = defaultEditMode === 'visual',
	enable2017Wikitext = featuresEnabled.sourcemodetoolbar,
	overflowMenu = require( './overflowMenu.js' );
let
	$pageContainer, linksController,
	pageThreads,
	lastControllerScrollOffset,
	pageDataCache = {},
	pageHandlersSetup = false;

let mobile = null;
if ( OO.ui.isMobile() && mw.config.get( 'skin' ) === 'minerva' ) {
	mobile = require( './mobile.js' );
}
require( './thanks.js' );

mw.messages.set( require( './controller/contLangMessages.json' ) );

/**
 * Get an MW API instance
 *
 * @return {mw.Api} API instance
 */
function getApi() {
	return new mw.Api( {
		parameters: {
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLanguage' )
		}
	} );
}

/**
 * Get various pieces of page metadata.
 *
 * This method caches responses. If you call it again with the same parameters, you'll get the exact
 * same Promise object, and no API request will be made.
 *
 * @param {string} pageName Page title
 * @param {number} oldId Revision ID
 * @param {Object} [apiParams] Additional parameters for the API
 * @return {jQuery.Promise}
 */
function getPageData( pageName, oldId, apiParams ) {
	const api = getApi();
	apiParams = apiParams || {};

	pageDataCache[ pageName ] = pageDataCache[ pageName ] || {};
	if ( pageDataCache[ pageName ][ oldId ] && $.isEmptyObject( apiParams ) ) {
		return pageDataCache[ pageName ][ oldId ];
	}

	let lintPromise, transcludedFromPromise;
	if ( oldId ) {
		lintPromise = api.get( {
			action: 'query',
			list: 'linterrors',
			lntcategories: 'fostered',
			lntlimit: 1,
			lnttitle: pageName
		} ).then( ( response ) => OO.getProp( response, 'query', 'linterrors' ) || [] );

		transcludedFromPromise = api.get( {
			action: 'discussiontoolspageinfo',
			page: pageName,
			oldid: oldId
		} ).then( ( response ) => OO.getProp( response, 'discussiontoolspageinfo', 'transcludedfrom' ) || {} );
	} else {
		lintPromise = $.Deferred().resolve( [] ).promise();
		transcludedFromPromise = $.Deferred().resolve( {} ).promise();
	}

	const veMetadataPromise = api.get( Object.assign( {
		action: 'visualeditor',
		paction: 'metadata',
		page: pageName
	}, apiParams ) ).then( ( response ) => OO.getProp( response, 'visualeditor' ) || [] );

	const promise = $.when( lintPromise, transcludedFromPromise, veMetadataPromise )
		.then( ( linterrors, transcludedfrom, metadata ) => ( {
			linterrors: linterrors,
			transcludedfrom: transcludedfrom,
			metadata: metadata
		} ), () => {
			// Clear on failure
			pageDataCache[ pageName ][ oldId ] = null;
			// Let caller handle the error
			return $.Deferred().rejectWith( this, arguments );
		} );

	if ( $.isEmptyObject( apiParams ) ) {
		pageDataCache[ pageName ][ oldId ] = promise;
	}

	return promise;
}

/**
 * Check if a given thread item on a page can be replied to
 *
 * @param {string} pageName Page title
 * @param {number} oldId Revision ID
 * @param {ThreadItem} threadItem Thread item
 * @return {jQuery.Promise} Resolved with a CommentDetails object if the comment appears on the page.
 *  Rejects with error data if the comment is transcluded, or there are lint errors on the page.
 */
function checkThreadItemOnPage( pageName, oldId, threadItem ) {
	const isNewTopic = threadItem.id === utils.NEW_TOPIC_COMMENT_ID;
	const defaultMode = mw.user.options.get( 'discussiontools-editmode' ) || mw.config.get( 'wgDiscussionToolsFallbackEditMode' );
	let apiParams = null;
	if ( isNewTopic ) {
		apiParams = {
			section: 'new',
			editintro: threadItem.editintro,
			preload: threadItem.preload,
			preloadparams: threadItem.preloadparams,
			paction: defaultMode === 'source' ? 'wikitext' : 'parse'
		};
	}

	return getPageData( pageName, oldId, apiParams )
		.then( ( response ) => {
			const metadata = response.metadata,
				lintErrors = response.linterrors,
				transcludedFrom = response.transcludedfrom;

			if ( !isNewTopic ) {
				// First look for data by the thread item's ID. If not found, also look by name.
				// Data by ID may not be found due to differences in headings (e.g. T273413, T275821),
				// or if a thread item's parent changes.
				// Data by name might be combined from two or more thread items, which would only allow us to
				// treat them both as transcluded from unknown source, unless we check ID first.
				let isTranscludedFrom = transcludedFrom[ threadItem.id ];
				if ( isTranscludedFrom === undefined ) {
					isTranscludedFrom = transcludedFrom[ threadItem.name ];
				}
				if ( isTranscludedFrom === undefined ) {
					// The thread item wasn't found when generating the "transcludedfrom" data,
					// so we don't know where the reply should be posted. Just give up.
					return $.Deferred().reject( 'discussiontools-commentid-notfound-transcludedfrom', { errors: [ {
						code: 'discussiontools-commentid-notfound-transcludedfrom',
						html: mw.message( 'discussiontools-error-comment-disappeared' ).parse() +
							'<br>' +
							mw.message( 'discussiontools-error-comment-disappeared-reload' ).parse()
					} ] } ).promise();
				} else if ( isTranscludedFrom ) {
					const mwTitle = isTranscludedFrom === true ? null : mw.Title.newFromText( isTranscludedFrom );
					// If this refers to a template rather than a subpage, we never want to edit it
					const follow = mwTitle && mwTitle.getNamespaceId() !== mw.config.get( 'wgNamespaceIds' ).template;

					let transcludedErrMsg;
					if ( follow ) {
						transcludedErrMsg = mw.message(
							'discussiontools-error-comment-is-transcluded-title',
							mwTitle.getPrefixedText()
						).parse();
					} else if ( metadata.canEdit ) {
						// If the user can edit, advise them to use the edit button
						transcludedErrMsg = mw.message(
							'discussiontools-error-comment-is-transcluded',
							// eslint-disable-next-line no-jquery/no-global-selector
							$( '#ca-edit' ).text()
						).parse();
					} else {
						// Otherwise, tell them why they can't edit
						transcludedErrMsg = metadata.notices[ 'permissions-error' ];
					}

					return $.Deferred().reject( 'comment-is-transcluded', { errors: [ {
						data: {
							transcludedFrom: isTranscludedFrom,
							follow: follow
						},
						code: 'comment-is-transcluded',
						html: transcludedErrMsg
					} ] } ).promise();
				}

				if ( lintErrors.length ) {
					// We currently only request the first error
					const lintType = lintErrors[ 0 ].category;

					return $.Deferred().reject( 'lint', { errors: [ {
						code: 'lint',
						html: mw.message( 'discussiontools-error-lint',
							'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Lint_errors/' + lintType,
							'https://www.mediawiki.org/wiki/Special:MyLanguage/Help_talk:Lint_errors/' + lintType,
							mw.util.getUrl( pageName, { action: 'edit', lintid: lintErrors[ 0 ].lintId } ) ).parse()
					} ] } ).promise();
				}
			}

			if ( !metadata.canEdit ) {
				return $.Deferred().reject( 'permissions-error', { errors: [ {
					code: 'permissions-error',
					html: metadata.notices[ 'permissions-error' ]
				} ] } ).promise();
			}

			return new CommentDetails( pageName, oldId, metadata.notices, metadata.wouldautocreate, metadata.content, defaultMode );
		} );
}

/**
 * Get a promise which resolves with editor checkbox data
 *
 * @param {string} pageName Page title
 * @param {number} oldId Revision ID
 * @return {jQuery.Promise} See ve.init.mw.ArticleTargetLoader#createCheckboxFields
 */
function getCheckboxesPromise( pageName, oldId ) {
	return getPageData(
		pageName,
		oldId
	).then( ( pageData ) => {
		const data = pageData.metadata,
			checkboxesDef = {};

		mw.messages.set( data.checkboxesMessages );

		// Only show the watch checkbox for now
		if ( 'wpWatchthis' in data.checkboxesDef ) {
			checkboxesDef.wpWatchthis = data.checkboxesDef.wpWatchthis;
			// Override the label with a more verbose one to distinguish this from topic subscriptions (T290712)
			checkboxesDef.wpWatchthis[ 'label-message' ] = 'discussiontools-replywidget-watchthis';
		}
		return mw.loader.using( 'ext.visualEditor.targetLoader' )
			.then( () => mw.libs.ve.targetLoader.createCheckboxFields( checkboxesDef ) );
		// TODO: createCheckboxField doesn't make links in the label open in a new
		// window as that method currently lives in ve.utils
	} );
}

/**
 * Get the resourceloader modules required for a mode of the reply widget
 *
 * @return {string[]}
 */
function getReplyWidgetModules() {
	const modules = [];
	if ( OO.ui.isMobile() ) {
		modules.push(
			'ext.visualEditor.core.mobile',
			'ext.visualEditor.mwextensions'
		);
	} else {
		modules.push(
			'ext.visualEditor.core.desktop',
			'ext.visualEditor.desktopTarget',
			'ext.visualEditor.mwextensions.desktop'
		);
	}
	modules.push( 'ext.discussionTools.ReplyWidget' );

	const veConf = mw.config.get( 'wgVisualEditorConfig' );
	modules.push( ...veConf.pluginModules.filter( mw.loader.getState ) );

	return modules;
}

/**
 * Initialize Discussion Tools features
 *
 * @param {jQuery} $container Page container
 * @param {Object<string,any>} [state] Page state data object
 * @param {string} [state.repliedTo] The comment ID that was just replied to
 * @param {boolean} [state.tempUserCreated] Whether a temp user was just created
 */
function init( $container, state ) {
	let
		activeCommentId = null,
		activeController = null;
	// Loads later to avoid circular dependency
	const CommentController = require( './CommentController.js' ),
		NewTopicController = require( './NewTopicController.js' );

	// We may be re-initializing after posting a new comment, so clear the cache, because
	// wgCurRevisionId will not change if we posted to a transcluded page (T266275).
	// This also applies when another tool has posted a comment and reloaded page contents (T323661).
	pageDataCache = {};

	// Lazy-load postEdit module, may be required later (on desktop)
	mw.loader.using( 'mediawiki.action.view.postEdit' );

	if ( linksController ) {
		linksController.teardown();
		linksController = null;
	}

	$pageContainer = $container;
	linksController = new ReplyLinksController( $pageContainer );
	permalinks.init( $pageContainer );

	linksController.on( 'link-interact', () => {
		// Preload page metadata when the user is about to use a link, to make the tool load faster.
		// NOTE: As of January 2023, this is an EXPENSIVE API CALL. It must not be done on every
		// pageview, as that would generate enough load to take down Wikimedia sites (T325477).
		// It would be barely acceptable to do it on every *discussion* pageview, but we're trying
		// to be better and only do it when really needed (T325598).
		getPageData(
			mw.config.get( 'wgRelevantPageName' ),
			mw.config.get( 'wgCurRevisionId' )
		);
	} );

	const parser = new Parser( require( './parser/data.json' ) );

	const commentNodes = $pageContainer[ 0 ].querySelectorAll( '[data-mw-thread-id]' );
	pageThreads = ThreadItemSet.static.newFromJSON( mw.config.get( 'wgDiscussionToolsPageThreads' ) || [], $pageContainer[ 0 ], parser );

	if ( featuresEnabled.topicsubscription ) {
		topicSubscriptions.initTopicSubscriptions( $container, pageThreads );
	}

	overflowMenu.init( $container, pageThreads );

	if ( mobile ) {
		mobile.init( $container, pageThreads );
	}

	if ( linksController.pageHasReplyLinks() || linksController.pageHasNewTopicLink() ) {
		// Start loading reply widget code
		// The worst-case here is that we might be on a page with no comments
		// and the add-topic link suppressed, *but* which has valid links to
		// trigger the new topic tool within the content. If that happens,
		// the modules will still be loaded when those links are interacted with.
		mw.loader.using( getReplyWidgetModules() );
	}

	/**
	 * Setup comment controllers for each comment, and the new topic controller
	 *
	 * @param {ThreadItem} comment
	 * @param {jQuery} $link Add section link for new topic controller
	 * @param {Object} [options] Options, see CommentController
	 * @param {string} [options.mode] Optionally force a mode, 'visual' or 'source'
	 * @param {boolean} [options.hideErrors] Suppress errors, e.g. when restoring auto-save
	 * @param {boolean} [options.suppressNotifications] Don't notify the user if recovering auto-save
	 * @param {MemoryStorage} [storage] Storage object for autosave
	 */
	function setupController( comment, $link, options, storage ) {
		if ( !storage ) {
			storage = new MemoryStorage( mw.storage, 'mw-ext-DiscussionTools-reply/' + comment.id, STORAGE_EXPIRY );
		}

		let commentController, $addSectionLink;
		if ( comment.id === utils.NEW_TOPIC_COMMENT_ID ) {
			// eslint-disable-next-line no-jquery/no-global-selector
			$addSectionLink = $( '#ca-addsection' ).find( 'a' );
			// When opening new topic tool using any link, always activate the link in page tabs too
			$link = $link.add( $addSectionLink );
			commentController = new NewTopicController( $pageContainer, comment, pageThreads, storage );
		} else {
			commentController = new CommentController( $pageContainer, comment, pageThreads, storage );
		}

		activeCommentId = comment.id;
		activeController = commentController;
		linksController.setActiveLink( $link );

		commentController.on( 'teardown', ( teardownMode ) => {
			activeCommentId = null;
			activeController = null;

			if ( teardownMode !== 'refresh' ) {
				linksController.clearActiveLink();
			}

			if ( teardownMode === 'abandoned' ) {
				linksController.focusLink( $link );
			}
		} );
		commentController.on( 'reloadPage', () => {
			mw.dt.initState.newCommentIds = commentController.newComments.map( ( cmt ) => cmt.id );
			// Teardown active reply widget(s)
			commentController.replyWidgetPromise.then( ( replyWidget ) => {
				lastControllerScrollOffset = $( commentController.newListItem ).offset().top;
				replyWidget.teardown( 'refresh' );
				// Only fetch the last now "good" revision, on which we know the parent comment still exists.
				// As we poll frequently, this will almost always be the lastet revision.
				refreshPageContents( commentController.oldId );
			} );
		} );

		commentController.setup( options );
		if ( lastControllerScrollOffset ) {
			$( document.documentElement ).scrollTop(
				$( document.documentElement ).scrollTop() +
				( $( commentController.newListItem ).offset().top - lastControllerScrollOffset )
			);
			lastControllerScrollOffset = null;
		}
	}

	function newTopicComment( data ) {
		const comment = new HeadingItem( {}, 2 );
		comment.id = utils.NEW_TOPIC_COMMENT_ID;
		comment.isNewTopic = true;
		Object.assign( comment, data );
		return comment;
	}

	// Hook up each link to open a reply widget
	//
	// TODO: Allow users to use multiple reply widgets simultaneously.
	// Currently submitting a reply from one widget would also destroy the other ones.
	linksController.on( 'link-click', ( commentId, $link, data ) => {
		// If the reply widget is already open, activate it.
		// Reply links are also made unclickable using 'pointer-events' in CSS, but that doesn't happen
		// for new section links, because we don't have a good way of visually disabling them.
		if ( activeCommentId === commentId ) {
			activeController.showAndFocus();
			return;
		}

		let teardownPromise = $.Deferred().resolve();
		if ( commentId === utils.NEW_TOPIC_COMMENT_ID ) {
			// If this is a new topic link, and a reply widget is open, attempt to close it first.
			if ( activeController ) {
				teardownPromise = activeController.tryTeardown();
			} else if ( OO.getProp( window, 've', 'init', 'target', 'tryTeardown' ) ) {
				// If VE or 2017WTE is open, attempt to close it as well. (T317035#8590357)
				// FIXME This should be generalized, using some global router or something,
				// so that we don't try to open while something else is open on full screen.
				// Another example is the MultimediaViewer extension.
				teardownPromise = ve.init.target.tryTeardown() || $.Deferred().resolve();
			}
		}

		if ( mobile ) {
			teardownPromise = teardownPromise.then( () => mobile.closeLedeSectionDialog() );
		}

		teardownPromise.then( () => {
			// If another reply widget is open (or opening), do nothing.
			if ( activeController ) {
				return;
			}
			let comment;
			if ( commentId !== utils.NEW_TOPIC_COMMENT_ID ) {
				comment = pageThreads.findCommentById( commentId );
			} else {
				comment = newTopicComment( data );
			}
			if ( comment ) {
				setupController( comment, $link );
			} else {
				// We couldn't find the comment, so express that there's an issue
				mw.notify( mw.msg( 'discussiontools-error-comment-disappeared' ) );
			}
		} );
	} );

	const mobilePromise = OO.ui.isMobile() && mw.loader.getState( 'mobile.init' ) ?
		mw.loader.using( 'mobile.init' ) :
		$.Deferred().resolve().promise();

	// Restore autosave
	// Don't do anything when we're editing/previewing
	if ( mw.config.get( 'wgAction' ) === 'view' ) {
		pageThreads.threadItems.every( ( comment, i ) => {
			const replyStorage = new MemoryStorage( mw.storage, 'mw-ext-DiscussionTools-reply/' + comment.id, STORAGE_EXPIRY );
			if ( replyStorage.get( 'saveable' ) ) {
				const mode = replyStorage.get( 'mode' );
				const $link = $( commentNodes[ i ] );
				// Wait for mobile section toggling code to be ready
				mobilePromise.then( () => {
					if ( OO.ui.isMobile() ) {
						const urlFragment = mw.util.escapeIdForLink( comment.id );
						// Force the section to expand on mobile (T338920)
						location.hash = '#' + urlFragment;
					}
					// Wait for the 'hashchange' event to be handled by the mobile code
					setTimeout( () => {
						setupController( comment, $link, {
							mode: mode,
							hideErrors: true,
							suppressNotifications: !state.firstLoad
						}, replyStorage );
					} );
				} );
				return false;
			}
			return true;
		} );
		const newTopicStorage = new MemoryStorage( mw.storage, 'mw-ext-DiscussionTools-reply/' + utils.NEW_TOPIC_COMMENT_ID, STORAGE_EXPIRY );
		if ( newTopicStorage.get( 'saveable' ) || newTopicStorage.get( 'title' ) ) {
			const mode = newTopicStorage.get( 'mode' );
			setupController( newTopicComment(), $( [] ), {
				mode: mode,
				hideErrors: true,
				suppressNotifications: !state.firstLoad
			}, newTopicStorage );
		} else if ( mw.config.get( 'wgDiscussionToolsStartNewTopicTool' ) ) {
			const data = linksController.parseNewTopicLink( location.href );
			setupController( newTopicComment( data ), $( [] ) );
		}
	}

	// For debugging (now unused in the code)
	mw.dt.pageThreads = pageThreads;

	mobilePromise.then( () => {
		if ( state.repliedTo ) {
			highlighter.highlightPublishedComment( pageThreads, state.repliedTo );

			if ( state.repliedTo === utils.NEW_TOPIC_COMMENT_ID ) {
				mw.hook( 'postEdit' ).fire( {
					tempUserCreated: state.tempUserCreated,
					message: mw.msg( 'discussiontools-postedit-confirmation-topicadded', mw.user )
				} );
			} else {
				mw.hook( 'postEdit' ).fire( {
					tempUserCreated: state.tempUserCreated,
					message: mw.msg( 'discussiontools-postedit-confirmation-published', mw.user )
				} );
			}
		} else if ( state.newCommentIds ) {
			highlighter.highlightNewComments( pageThreads, true, state.newCommentIds );
		}

		// Check topic subscription states if the user has automatic subscriptions enabled
		// and has recently edited this page.
		if ( featuresEnabled.autotopicsub && mw.user.options.get( 'discussiontools-autotopicsub' ) ) {
			topicSubscriptions.updateAutoSubscriptionStates( $container, pageThreads, state.repliedTo );
		}
	} );

	let dismissableNotificationPromise = null;
	// Page-level handlers only need to be setup once
	if ( !pageHandlersSetup ) {
		$( window ).on( 'popstate', () => {
			// Delay with setTimeout() because "the Document's target element" (corresponding to the :target
			// selector in CSS) is not yet updated to match the URL when handling a 'popstate' event.
			setTimeout( () => {
				highlighter.highlightTargetComment( pageThreads, true );
			} );
		} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).on( 'click', ( e ) => {
			if ( utils.isUnmodifiedLeftClick( e ) && !e.target.closest( 'a' ) ) {
				// Remove the highlight and the hash from the URL, unless clicking on another link
				highlighter.clearHighlightTargetComment( pageThreads );
			}
			if ( dismissableNotificationPromise ) {
				dismissableNotificationPromise.then( ( notif ) => {
					notif.close();
				} );
				dismissableNotificationPromise = null;
			}
		} );
		pageHandlersSetup = true;
	}
	if ( state.firstLoad ) {
		mobilePromise.then( () => {
			let findCommentQuery;
			let isHeading = false;
			const highlightResult = highlighter.highlightTargetComment( pageThreads );

			// Hash contains a non-replaced space (should be underscore), maybe due to
			// manual creation or a broken third party tool. Just replace the spaces
			// and navigate to the new URL.
			// Ideally we'd use history.replaceState but that wouldn't scroll the page.
			// Only do the replacement if the original hash doesn't correspond to a target
			// element, but the fixed hash does, to avoid affects on other apps which
			// may use fragments with spaces.
			if ( location.hash && !mw.util.getTargetFromFragment() && location.hash.indexOf( '%20' ) !== -1 ) {
				const fixedHash = location.hash.slice( 1 ).replace( /%20/g, '_' );
				if ( mw.util.getTargetFromFragment( fixedHash ) ) {
					location.hash = fixedHash;
				}
			}
			if (
				// Fragment doesn't correspond to an element on the page
				location.hash &&
				!mw.util.getTargetFromFragment() &&
				// Not a DT comment
				highlightResult.highlighted.length === 0 && highlightResult.requested.length === 0
			) {
				const fragment = location.hash.slice( 1 );
				const ignorePatterns = [
					// A leading '/' or '!/' usually means a application route, e.g. /media, or /editor.
					// We can't rule out a heading title (T349498), but they are unlikely
					/^!?\//,
					// "top" is a magic value in the WHATWG spec that goes to the top of the
					// document (unless there's an actual id=top element on the page), see:
					// https://html.spec.whatwg.org/multipage/browsing-the-web.html#scrolling-to-a-fragment:top-of-the-document-2
					// There's a very rare edge case of actual headings named "top" that we'll
					// be missing here, but they're far less common than the top-of-page usage.
					/^top$/i,
					// Gadget: ConvenientDiscussions
					/^\d{12}_/,
					// Gadget: RedWarn
					/^noticeApplied-/
				];
				if ( ignorePatterns.every( ( pattern ) => !pattern.test( fragment ) ) ) {
					findCommentQuery = {
						heading: mw.util.percentDecodeFragment( fragment ).replace( / /g, '_' ),
						page: mw.config.get( 'wgRelevantPageName' )
					};
					isHeading = true;
				}
			} else if ( highlightResult.highlighted.length === 0 && highlightResult.requested.length === 1 ) {
				findCommentQuery = {
					idorname: mw.util.percentDecodeFragment( highlightResult.requested[ 0 ] )
				};
				isHeading = highlightResult.requested[ 0 ].slice( 0, 1 ) === 'h';
			}
			if ( findCommentQuery ) {
				// TODO: Support multiple commentIds being requested and not all being found
				const dtConf = require( './config.json' );
				const findCommentRequest = dtConf.enablePermalinksFrontend ?
					getApi().get( Object.assign( {
						action: 'discussiontoolsfindcomment'
					}, findCommentQuery ) ) :
					$.Deferred().resolve( [ {} ] ).promise();
				dismissableNotificationPromise = $.when(
					findCommentRequest,
					mw.loader.using( 'mediawiki.notification' )
				).then( ( results ) => {
					const result = results[ 0 ];
					let titles = [];
					if ( result.discussiontoolsfindcomment ) {
						titles = result.discussiontoolsfindcomment.map( ( threadItemData ) => {
							// Only show items that appear on the current revision of their page
							// and are not transcluded from another page
							if ( threadItemData.couldredirect ) {
								const title = mw.Title.newFromText(
									threadItemData.title + '#' +
									mw.util.escapeIdForLink( threadItemData.id )
								);
								return title;
							}
							return null;
						} ).filter( ( url ) => url );
					}
					if ( titles.length ) {
						const $list = $( '<ul>' );
						const $notification = $( '<div>' ).append(
							$( '<p>' ).text( mw.message(
								isHeading ?
									'discussiontools-target-heading-found-moved' :
									'discussiontools-target-comment-found-moved',
								titles.length
							).text() ),
							$list
						);
						titles.forEach( ( title ) => {
							$list.append(
								$( '<li>' ).append(
									$( '<a>' ).attr( 'href', title.getUrl() ).text( title.getPrefixedText() )
								)
							);
						} );
						mw.notification.notify(
							$notification,
							{ type: 'warn', autoHide: false }
						);
						// This notification should not be accidentally dismissable
						return $.Deferred().reject().promise();
					} else {
						return mw.notification.notify(
							mw.message( isHeading ?
								'discussiontools-target-heading-missing' :
								'discussiontools-target-comment-missing'
							).text(),
							{ type: 'warn', autoHide: false }
						);
					}
				} );
			}
			if ( highlightResult.highlighted.length === 0 && ( highlightResult.requested.length > 1 || highlightResult.requestedSince ) ) {
				dismissableNotificationPromise = mw.loader.using( 'mediawiki.notification' ).then( () => mw.notification.notify(
					mw.message( 'discussiontools-target-comments-missing' ).text(),
					{ type: 'warn', autoHide: false }
				) );
			}
		} );
	}
}

/**
 * Update the contents of the page with the data from an action=parse API response.
 *
 * @param {jQuery} $container Page container
 * @param {Object} data Data from action=parse API
 */
function updatePageContents( $container, data ) {
	$container.find( '.mw-parser-output' ).first().replaceWith( data.parse.text );

	mw.util.clearSubtitle();
	mw.util.addSubtitle( data.parse.subtitle );

	// eslint-disable-next-line no-jquery/no-global-selector
	if ( $( '#catlinks' ).length ) {
		const $categories = $( $.parseHTML( data.parse.categorieshtml ) );
		mw.hook( 'wikipage.categories' ).fire( $categories );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#catlinks' ).replaceWith( $categories );
	}

	mw.config.set( data.parse.jsconfigvars );
	mw.loader.load( data.parse.modulestyles );
	mw.loader.load( data.parse.modules );

	mw.config.set( {
		wgCurRevisionId: data.parse.revid,
		wgRevisionId: data.parse.revid
	} );

	// TODO: update displaytitle, lastmodified
	// We may not be able to use prop=displaytitle without making changes in the action=parse API,
	// VE API has some confusing code that changes the HTML escaping on it before returning???

	// We need our init code to run after everyone else's handlers for this hook,
	// so that all changes to the page layout have been completed (e.g. collapsible elements),
	// and we can measure things and display the highlight in the right place.
	mw.hook( 'wikipage.content' ).remove( mw.dt.init );
	mw.hook( 'wikipage.content' ).fire( $container );
	// The hooks have "memory" so calling add() after fire() actually fires the handler,
	// and calling add() before fire() would actually fire it twice.
	mw.hook( 'wikipage.content' ).add( mw.dt.init );

	mw.hook( 'wikipage.tableOfContents' ).fire(
		data.parse.showtoc ? data.parse.sections : []
	);

	// Copied from ve.init.mw.DesktopArticleTarget.prototype.saveComplete
	// TODO: Upstream this to core/skins, triggered by a hook (wikipage.content?)
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#t-permalink' ).add( '#coll-download-as-rl' ).find( 'a' ).each( ( i, link ) => {
		const permalinkUrl = new URL( link.href );
		permalinkUrl.searchParams.set( 'oldid', data.parse.revid );
		$( link ).attr( 'href', permalinkUrl.toString() );
	} );

	const url = new URL( location.href );
	url.searchParams.delete( 'oldid' );

	// If there are any other query parameters left, re-use that URL object.
	// Otherwise use the canonical style view url (T44553, T102363).
	const keys = [];
	url.searchParams.forEach( ( val, key ) => {
		keys.push( key );
	} );

	if ( !keys.length || ( keys.length === 1 && keys[ 0 ] === 'title' ) ) {
		const viewUrl = new URL( mw.util.getUrl( mw.config.get( 'wgRelevantPageName' ) ), document.baseURI );
		viewUrl.hash = location.hash;
		history.pushState( null, '', viewUrl );
	} else {
		history.pushState( null, '', url );
	}
}

/**
 * Load the latest revision of the page and display its contents.
 *
 * @param {number} [oldId] Revision ID to fetch, latest if not specified
 * @return {jQuery.Promise} Promise which resolves when the refresh is complete
 */
function refreshPageContents( oldId ) {
	// This should approximately match the API call in ApiVisualEditorEditor#parseWikitext
	return getApi().get( {
		action: 'parse',
		// HACK: 'useskin' triggers a different code path that runs our OutputPageBeforeHTML hook,
		// adding our reply links in the HTML (T266195)
		useskin: mw.config.get( 'skin' ),
		mobileformat: OO.ui.isMobile(),
		uselang: mw.config.get( 'wgUserLanguage' ),
		// Pass through dtenable query string param from original request
		dtenable: new URLSearchParams( location.search ).get( 'dtenable' ) ? '1' : undefined,
		prop: [ 'text', 'revid', 'categorieshtml', 'sections', 'displaytitle', 'subtitle', 'modules', 'jsconfigvars' ],
		page: !oldId ? mw.config.get( 'wgRelevantPageName' ) : undefined,
		oldid: oldId || undefined
	} ).then( ( parseResp ) => {
		updatePageContents( $pageContainer, parseResp );
	} );
}

/**
 * Update the page after a comment is published/saved
 *
 * @param {Object} data Edit API response data
 * @param {ThreadItem} threadItem Parent thread item
 * @param {string} pageName Page title
 * @param {mw.dt.ReplyWidget} replyWidget ReplyWidget
 */
function update( data, threadItem, pageName, replyWidget ) {
	function logSaveSuccess() {
		mw.track( 'editAttemptStep', {
			action: 'saveSuccess',
			timing: mw.now() - replyWidget.commentController.saveInitiated,
			// eslint-disable-next-line camelcase
			revision_id: data.newrevid
		} );
	}

	if (
		( pageName === mw.config.get( 'wgRelevantPageName' ) && data.nocontent ) ||
		data.tempusercreated
	) {
		// Reload if the page didn't exist before this update, or we just became logged in
		// as a temporary user. We'd handle setting up the content just fine (assuming there's
		// a mw-parser-output), but fixing up the UI tabs/behavior is outside our scope.
		replyWidget.unbindBeforeUnloadHandler();
		replyWidget.clearStorage();
		replyWidget.setPending( true );
		const params = { dtrepliedto: threadItem.id };
		if ( data.tempusercreated ) {
			params.dttempusercreated = '1';
		}
		window.location = data.tempusercreatedredirect || mw.util.getUrl( pageName, params );
		logSaveSuccess();
		return;
	}

	replyWidget.teardown();
	// TODO: Tell controller to teardown all other open widgets

	// Highlight the new reply after re-initializing
	mw.dt.initState.repliedTo = threadItem.id;
	mw.dt.initState.tempUserCreated = data.tempusercreated;

	// Update page state
	const pageUpdated = $.Deferred();
	if ( pageName === mw.config.get( 'wgRelevantPageName' ) ) {
		// We can use the result from the VisualEditor API
		updatePageContents( $pageContainer, {
			parse: {
				text: data.content,
				subtitle: data.contentSub,
				categorieshtml: data.categorieshtml,
				jsconfigvars: data.jsconfigvars,
				revid: data.newrevid,
				// Note: VE API merges 'modules' and 'modulestyles'
				modules: data.modules,
				modulestyles: [],
				// Note: VE API drops 'showtoc' and changes 'sections' depending on it
				showtoc: true,
				sections: data.sections
			}
		} );

		mw.config.set( {
			wgCurRevisionId: data.newrevid,
			wgRevisionId: data.newrevid
		} );

		pageUpdated.resolve();

	} else {
		// We saved to another page, we must purge and then fetch the current page
		const api = getApi();
		api.post( {
			action: 'purge',
			titles: mw.config.get( 'wgRelevantPageName' )
		} ).then( () => refreshPageContents() ).then( () => {
			pageUpdated.resolve();
		} ).catch( () => {
			// We saved the reply, but couldn't purge or fetch the updated page. Seems difficult to
			// explain this problem. Redirect to the page where the user can at least see their reply…
			window.location = mw.util.getUrl( pageName, { dtrepliedto: threadItem.id } );
			// We're confident the saving portion succeeded, so still log this:
			logSaveSuccess();
		} );
	}

	// User logged in if module loaded.
	if ( mw.loader.getState( 'mediawiki.page.watch.ajax' ) === 'ready' ) {
		const watch = require( 'mediawiki.page.watch.ajax' );

		watch.updateWatchLink(
			mw.Title.newFromText( pageName ),
			data.watched ? 'unwatch' : 'watch',
			'idle',
			data.watchlistexpiry
		);
	}

	pageUpdated.then( logSaveSuccess );
}

module.exports = {
	init: init,
	update: update,
	updatePageContents: updatePageContents,
	refreshPageContents: refreshPageContents,
	checkThreadItemOnPage: checkThreadItemOnPage,
	getCheckboxesPromise: getCheckboxesPromise,
	getApi: getApi,
	getReplyWidgetModules: getReplyWidgetModules,
	defaultVisual: defaultVisual,
	enable2017Wikitext: enable2017Wikitext
};
