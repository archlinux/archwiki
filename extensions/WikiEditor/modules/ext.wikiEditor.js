/*
 * JavaScript for WikiEditor
 */

( function () {
	var editingSessionId;

	// This sets $.wikiEditor and $.fn.wikiEditor
	require( './jquery.wikiEditor.js' );

	function log() {
		// mw.log is a no-op unless resource loader is in debug mode, so
		// this allows trackdebug to work independently (T211698)
		// eslint-disable-next-line no-console
		console.log.apply( console, arguments );
	}

	function sampledLogger( schema, callback ) {
		var trackdebug = !!mw.util.getParamValue( 'trackdebug' );
		return function () {
			if ( mw.loader.getState( 'ext.eventLogging' ) === null ) {
				return;
			}
			var args = Array.prototype.slice.call( arguments );

			mw.loader.using( [ 'ext.eventLogging' ] ).done( function () {
				// Sampling
				// We have to do this on the client too because the unload handler
				// can cause an editingSessionId to be generated on the client
				// Not using mw.eventLog.inSample() because we need to be able to pass our own editingSessionId
				var inSample = mw.eventLog.randomTokenMatch(
					1 / mw.config.get( 'wgWMESchemaEditAttemptStepSamplingRate' ),
					editingSessionId
				);

				if ( !inSample && !mw.config.get( 'wgWMESchemaEditAttemptStepOversample' ) && !trackdebug ) {
					return;
				}

				var data = callback.apply( this, [ inSample ].concat( args ) );

				if ( trackdebug ) {
					log( schema, data );
				} else {
					mw.eventLog.logEvent( schema, data );
				}
			} );
		};
	}

	function addABTestData( data, addToken ) {
		// DiscussionTools New Topic A/B test for logged out users
		if ( !mw.config.get( 'wgDiscussionToolsABTest' ) ) {
			return;
		}
		if ( mw.user.isAnon() ) {
			var tokenData = mw.storage.getObject( 'DTNewTopicABToken' );
			if ( !tokenData ) {
				return;
			}
			var anonid = parseInt( tokenData.token.slice( 0, 8 ), 16 );
			data.bucket = anonid % 2 === 0 ? 'test' : 'control';
			if ( addToken ) {
				// eslint-disable-next-line camelcase
				data.anonymous_user_token = tokenData.token;
			}
		} else if ( mw.user.options.get( 'discussiontools-abtest2' ) ) {
			data.bucket = mw.user.options.get( 'discussiontools-abtest2' );
		}
	}

	var actionPrefixMap = {
		firstChange: 'first_change',
		saveIntent: 'save_intent',
		saveAttempt: 'save_attempt',
		saveSuccess: 'save_success',
		saveFailure: 'save_failure'
	};

	var logEditEvent = sampledLogger( 'EditAttemptStep', function ( inSample, action, data ) {
		var actionPrefix = actionPrefixMap[ action ] || action;

		/* eslint-disable camelcase */
		data = $.extend( {
			version: 1,
			action: action,
			is_oversample: !inSample,
			editing_session_id: editingSessionId,
			page_token: mw.user.getPageviewToken(),
			session_token: mw.user.sessionId(),
			editor_interface: 'wikitext',
			platform: 'desktop', // FIXME T249944
			integration: 'page',
			page_id: mw.config.get( 'wgArticleId' ),
			page_title: mw.config.get( 'wgPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			revision_id: mw.config.get( 'wgRevisionId' ) || +$( 'input[name=parentRevId]' ).val() || 0,
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			mw_version: mw.config.get( 'wgVersion' )
		}, data );

		if ( mw.user.isAnon() ) {
			data.user_class = 'IP';
		}

		addABTestData( data, true );

		// Schema's kind of a mess of special properties
		if ( data.action === 'init' || data.action === 'abort' || data.action === 'saveFailure' ) {
			data[ actionPrefix + '_type' ] = data.type;
		}
		if ( data.action === 'init' || data.action === 'abort' ) {
			data[ actionPrefix + '_mechanism' ] = data.mechanism;
		}
		if ( data.action !== 'init' ) {
			data[ actionPrefix + '_timing' ] = data.timing === undefined ? 0 : Math.floor( data.timing );
		}
		/* eslint-enable camelcase */

		// Remove renamed properties
		delete data.type;
		delete data.mechanism;
		delete data.timing;

		return data;
	} );

	var logEditFeature = sampledLogger( 'VisualEditorFeatureUse', function ( inSample, feature, action ) {
		/* eslint-disable camelcase */
		var data = {
			feature: feature,
			action: action,
			editingSessionId: editingSessionId,
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			platform: 'desktop', // FIXME T249944
			integration: 'page',
			editor_interface: 'wikitext'
		};
		addABTestData( data );
		/* eslint-enable camelcase */
		return data;
	} );

	function logAbort( switchingToVE, unmodified ) {
		if ( switchingToVE ) {
			logEditFeature( 'editor-switch', 'visual-desktop' );
		}

		var abortType;
		if ( switchingToVE && unmodified ) {
			abortType = 'switchnochange';
		} else if ( switchingToVE ) {
			abortType = 'switchwithout';
		} else if ( unmodified ) {
			abortType = 'nochange';
		} else {
			abortType = 'abandon';
		}

		logEditEvent( 'abort', {
			type: abortType
		} );
	}

	$( function () {
		var $textarea = $( '#wpTextbox1' ),
			$editingSessionIdInput = $( '#editingStatsId' ),
			origText = $textarea.val();

		// T263505, T249038
		$( '#wikieditorUsed' ).val( 'yes' );

		if ( $editingSessionIdInput.length ) {
			editingSessionId = $editingSessionIdInput.val();
			if ( window.performance && window.performance.timing ) {
				// We want to track from the time the user started to try to
				// launch the editor which navigationStart approximates. All
				// of our supported browsers *should* allow this. Rather than
				// fall back to the timestamp when the page loaded for those
				// that don't, we just ignore them, so as to not skew the
				// results towards better-performance in those cases.
				var readyTime = Date.now();
				logEditEvent( 'ready', {
					timing: readyTime - window.performance.timing.navigationStart
				} );
				$textarea.on( 'wikiEditor-toolbar-doneInitialSections', function () {
					logEditEvent( 'loaded', {
						timing: Date.now() - window.performance.timing.navigationStart
					} );
				} ).one( 'input', function () {
					logEditEvent( 'firstChange', {
						timing: Date.now() - readyTime
					} );
				} );
			}
			var $form = $textarea.closest( 'form' );
			if ( mw.user.options.get( 'uselivepreview' ) ) {
				$form.find( '#wpPreview' ).on( 'click', function () {
					logEditFeature( 'preview', 'preview-live' );
				} );
			}

			var submitting;
			$form.on( 'submit', function () {
				submitting = true;
			} );
			var onUnloadFallback = window.onunload;

			window.onunload = function () {
				var unmodified = mw.config.get( 'wgAction' ) !== 'submit' && origText === $textarea.val(),
					caVeEdit = $( '#ca-ve-edit' )[ 0 ],
					switchingToVE = caVeEdit && (
						document.activeElement === caVeEdit ||
						$.contains( caVeEdit, document.activeElement )
					);

				var fallbackResult;
				if ( onUnloadFallback ) {
					fallbackResult = onUnloadFallback();
				}

				if ( !submitting ) {
					logAbort( switchingToVE, unmodified );
				}

				// If/when the user uses the back button to go back to the edit form
				// and the browser serves this from bfcache, regenerate the session ID
				// so we don't use the same ID twice. Ideally we'd do this by listening to the pageshow
				// event and checking e.originalEvent.persisted, but that doesn't work in Chrome:
				// https://code.google.com/p/chromium/issues/detail?id=344507
				// So instead we modify the DOM here, after sending the abort event.
				editingSessionId = mw.user.generateRandomSessionId();
				$editingSessionIdInput.val( editingSessionId );

				return fallbackResult;
			};
			$textarea.on( 'wikiEditor-switching-visualeditor', function () {
				var unmodified = mw.config.get( 'wgAction' ) !== 'submit' && origText === $textarea.val();
				// A non-navigation switch to VE has occurred. As such, avoid eventually
				// double-logging an abort when VE is done.
				window.onunload = onUnloadFallback;

				logAbort( true, unmodified );
			} );
		}

		// The old toolbar is still in place and needs to be removed so there aren't two toolbars
		$( '#toolbar' ).remove();
		// Add toolbar module
		// TODO: Implement .wikiEditor( 'remove' )
		mw.addWikiEditor( $textarea );
	} );

	mw.addWikiEditor = function ( $textarea ) {
		$textarea.wikiEditor(
			'addModule', require( './jquery.wikiEditor.toolbar.config.js' )
		);

		var dialogsConfig = require( './jquery.wikiEditor.dialogs.config.js' );
		// Replace icons
		dialogsConfig.replaceIcons( $textarea );
		// Add dialogs module
		$textarea.wikiEditor( 'addModule', dialogsConfig.getDefaultConfig() );

	};
}() );
