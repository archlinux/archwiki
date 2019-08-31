/*
 * JavaScript for WikiEditor
 */

( function () {
	var editingSessionId,
		actionPrefixMap = {
			saveIntent: 'save_intent',
			saveAttempt: 'save_attempt',
			saveSuccess: 'save_success',
			saveFailure: 'save_failure'
		};

	function logEditEvent( action, data ) {
		if ( mw.loader.getState( 'schema.EditAttemptStep' ) === null ) {
			return;
		}

		mw.loader.using( [ 'schema.EditAttemptStep', 'ext.eventLogging.subscriber' ] ).done( function () {
			// Sampling
			// We have to do this on the client too because the unload handler
			// can cause an editingSessionId to be generated on the client
			// Not using mw.eventLog.inSample() because we need to be able to pass our own editingSessionId
			var inSample = mw.eventLog.randomTokenMatch(
					1 / mw.config.get( 'wgWMESchemaEditAttemptStepSamplingRate' ),
					editingSessionId
				),
				actionPrefix = actionPrefixMap[ action ] || action;

			if ( !inSample && !mw.config.get( 'wgWMESchemaEditAttemptStepOversample' ) ) {
				return;
			}

			/* eslint-disable camelcase */
			data = $.extend( {
				version: 1,
				action: action,
				is_oversample: !inSample,
				editing_session_id: editingSessionId,
				page_token: mw.user.getPageviewToken(),
				session_token: mw.user.sessionId(),
				editor_interface: 'wikitext',
				platform: 'desktop', // FIXME
				integration: 'page',
				page_id: mw.config.get( 'wgArticleId' ),
				page_title: mw.config.get( 'wgPageName' ),
				page_ns: mw.config.get( 'wgNamespaceNumber' ),
				revision_id: mw.config.get( 'wgRevisionId' ),
				user_id: mw.user.getId(),
				user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
				mw_version: mw.config.get( 'wgVersion' )
			}, data );

			if ( mw.user.isAnon() ) {
				data.user_class = 'IP';
			}

			data[ actionPrefix + '_type' ] = data.type;
			data[ actionPrefix + '_mechanism' ] = data.mechanism;
			data[ actionPrefix + '_timing' ] = data.timing === undefined ? 0 : Math.floor( data.timing );
			/* eslint-enable camelcase */

			// Remove renamed properties
			delete data.type;
			delete data.mechanism;
			delete data.timing;

			mw.eventLog.logEvent( 'EditAttemptStep', data );
		} );
	}

	$( function () {
		var $textarea = $( '#wpTextbox1' ),
			$editingSessionIdInput = $( '#editingStatsId' ),
			origText = $textarea.val(),
			submitting, onUnloadFallback;

		if ( $editingSessionIdInput.length ) {
			editingSessionId = $editingSessionIdInput.val();
			if ( window.performance && window.performance.timing ) {
				// We want to track from the time the user started to try to
				// launch the editor which navigationStart approximates. All
				// of our supported browsers *should* allow this. Rather than
				// fall back to the timestamp when the page loaded for those
				// that don't, we just ignore them, so as to not skew the
				// results towards better-performance in those cases.
				logEditEvent( 'ready', {
					timing: Date.now() - window.performance.timing.navigationStart
				} );
				$textarea.on( 'wikiEditor-toolbar-doneInitialSections', function () {
					logEditEvent( 'loaded', {
						timing: Date.now() - window.performance.timing.navigationStart
					} );
				} );
			}
			$textarea.closest( 'form' ).on( 'submit', function () {
				submitting = true;
			} );
			onUnloadFallback = window.onunload;
			window.onunload = function () {
				var fallbackResult, abortType,
					caVeEdit = $( '#ca-ve-edit' )[ 0 ],
					switchingToVE = caVeEdit && (
						document.activeElement === caVeEdit ||
						$.contains( caVeEdit, document.activeElement )
					),
					unmodified = mw.config.get( 'wgAction' ) !== 'submit' && origText === $textarea.val();

				if ( onUnloadFallback ) {
					fallbackResult = onUnloadFallback();
				}

				if ( switchingToVE && unmodified ) {
					abortType = 'switchnochange';
				} else if ( switchingToVE ) {
					abortType = 'switchwithout';
				} else if ( unmodified ) {
					abortType = 'nochange';
				} else {
					abortType = 'abandon';
				}

				if ( !submitting ) {
					logEditEvent( 'abort', {
						type: abortType
					} );
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
		}
	} );
}() );
