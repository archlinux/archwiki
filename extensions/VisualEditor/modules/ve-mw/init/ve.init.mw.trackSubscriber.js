/*!
 * VisualEditor MediaWiki event subscriber.
 *
 * Subscribes to ve.track() events and routes them to mw.track().
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

( function () {
	var actionPrefixMap = {
			firstChange: 'first_change',
			saveIntent: 'save_intent',
			saveAttempt: 'save_attempt',
			saveSuccess: 'save_success',
			saveFailure: 'save_failure'
		},
		trackdebug = new URL( location.href ).searchParams.has( 'trackdebug' ),
		firstInitDone = false;

	function getEditingSessionIdFromRequest() {
		return mw.config.get( 'wgWMESchemaEditAttemptStepSessionId' ) ||
			new URL( location.href ).searchParams.get( 'editingStatsId' );
	}

	var timing = {};
	var editingSessionId = getEditingSessionIdFromRequest() || mw.user.generateRandomSessionId();
	ve.init.editingSessionId = editingSessionId;

	function log() {
		// mw.log is a no-op unless resource loader is in debug mode, so
		// this allows trackdebug to work independently (T211698)
		// eslint-disable-next-line no-console
		console.log.apply( console, arguments );
	}

	function inEASSample() {
		// Not using mw.eventLog.inSample() because we need to be able to pass our own editingSessionId
		return mw.eventLog.randomTokenMatch(
			1 / mw.config.get( 'wgWMESchemaEditAttemptStepSamplingRate' ),
			editingSessionId
		);
	}

	function inVEFUSample() {
		return mw.eventLog.randomTokenMatch(
			1 / mw.config.get( 'wgWMESchemaVisualEditorFeatureUseSamplingRate' ),
			editingSessionId
		);
	}

	function addABTestData( data, addToken ) {
		// DiscussionTools A/B test for logged out users
		if ( !mw.config.get( 'wgDiscussionToolsABTest' ) ) {
			return;
		}
		if ( mw.config.get( 'wgDiscussionToolsABTestBucket' ) ) {
			data.bucket = mw.config.get( 'wgDiscussionToolsABTestBucket' );
		}
		if ( mw.user.isAnon() && addToken ) {
			var token = mw.cookie.get( 'DTABid', '' );
			if ( token ) {
				// eslint-disable-next-line camelcase
				data.anonymous_user_token = token;
			}
		}
	}

	function computeDuration( action, event, timeStamp ) {
		if ( event.timing !== undefined ) {
			return event.timing;
		}

		switch ( action ) {
			case 'ready':
				return timeStamp - timing.init;
			case 'loaded':
				return timeStamp - timing.init;
			case 'firstChange':
				return timeStamp - timing.ready;
			case 'saveIntent':
				return timeStamp - timing.ready;
			case 'saveAttempt':
				return timeStamp - timing.saveIntent;
			case 'saveSuccess':
			case 'saveFailure':
				// HERE BE DRAGONS: the caller must compute these themselves
				// for sensible results. Deliberately sabotage any attempts to
				// use the default by returning -1
				mw.log.warn( 've.init.mw.trackSubscriber: Do not rely on default timing value for saveSuccess/saveFailure' );
				return -1;
			case 'abort':
				switch ( event.type ) {
					case 'preinit':
						return timeStamp - timing.init;
					case 'nochange':
					case 'switchwith':
					case 'switchwithout':
					case 'switchnochange':
					case 'abandon':
						return timeStamp - timing.ready;
					case 'abandonMidsave':
						return timeStamp - timing.saveAttempt;
				}
		}
		mw.log.warn( 've.init.mw.trackSubscriber: Unrecognized action', action );
		return -1;
	}

	/**
	 * Log the equivalent of an EditAttemptStep event via
	 * [the Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform).
	 *
	 * See https://phabricator.wikimedia.org/T309013.
	 *
	 * @param {Object} data
	 * @param {string} actionPrefix
	 */
	function logEditViaMetricsPlatform( data, actionPrefix ) {
		var eventName = 'eas.ve.' + actionPrefix;

		/* eslint-disable camelcase */
		var customData = ve.extendObject(
			{
				editor_interface: 'visualeditor',
				integration: ve.init && ve.init.target && ve.init.target.constructor.static.integrationType || 'page',
				editing_session_id: editingSessionId
			},
			data
		);

		if ( !mw.config.get( 'wgRevisionId' ) ) {

			// eslint-disable-next-line no-jquery/no-global-selector
			customData.revision_id = +$( 'input[name=parentRevId]' ).val() || 0;
		}

		/* eslint-enable camelcase */

		delete customData.action;

		// Sampling rate (and therefore whether a stream should oversample) is captured in the
		// stream config ($wgEventStreams).
		delete customData.is_oversample;

		// Platform can be derived from the agent_client_platform_family context attribute mixed in
		// by the JavaScript Metrics Platform Client. The context attribute will be
		// "desktop_browser" or "mobile_browser" depending on whether the MobileFrontend extension
		// has signalled that it is enabled.
		delete customData.platform;

		mw.eventLog.dispatch( eventName, customData );
	}

	function mwEditHandler( topic, data, timeStamp ) {
		var action = topic.split( '.' )[ 1 ],
			actionPrefix = actionPrefixMap[ action ] || action,
			duration = 0;

		if ( action === 'init' ) {
			if ( firstInitDone ) {
				// Regenerate editingSessionId
				editingSessionId = mw.user.generateRandomSessionId();
				ve.init.editingSessionId = editingSessionId;
			}
			firstInitDone = true;
		}

		if ( !inEASSample() && !mw.config.get( 'wgWMESchemaEditAttemptStepOversample' ) && !trackdebug ) {
			return;
		}

		if (
			action === 'abort' &&
			( data.type === 'unknown' || data.type === 'unknown-edited' )
		) {
			if (
				timing.saveAttempt &&
				timing.saveSuccess === undefined &&
				timing.saveFailure === undefined
			) {
				data.type = 'abandonMidsave';
			} else if (
				timing.init &&
				timing.ready === undefined
			) {
				data.type = 'preinit';
			} else if ( data.type === 'unknown' ) {
				data.type = 'nochange';
			} else {
				data.type = 'abandon';
			}
		}

		// Convert mode=source/visual to interface name
		if ( data && data.mode ) {
			// eslint-disable-next-line camelcase
			data.editor_interface = data.mode === 'source' ? 'wikitext-2017' : 'visualeditor';
			delete data.mode;
		}

		if ( !data.platform ) {
			if ( ve.init && ve.init.target && ve.init.target.constructor.static.platformType ) {
				data.platform = ve.init.target.constructor.static.platformType;
			} else {
				data.platform = 'other';
				// TODO: outright abort in this case, once we think we've caught everything
				mw.log.warn( 've.init.mw.trackSubscriber: no target available and no platform specified', action );
			}
		}

		// Schema's kind of a mess of special properties
		if ( action === 'init' || action === 'abort' || action === 'saveFailure' ) {
			data[ actionPrefix + '_type' ] = data.type;
		}
		if ( action === 'init' || action === 'abort' ) {
			data[ actionPrefix + '_mechanism' ] = data.mechanism;
		}
		if ( action !== 'init' ) {
			// Schema actually does have an init_timing field, but we don't want to
			// store it because it's not meaningful.
			duration = Math.round( computeDuration( action, data, timeStamp ) );
			data[ actionPrefix + '_timing' ] = duration;
		}
		if ( action === 'saveFailure' ) {
			data[ actionPrefix + '_message' ] = data.message;
		}

		// Remove renamed properties
		delete data.type;
		delete data.mechanism;
		delete data.timing;
		delete data.message;

		if ( action === 'abort' ) {
			timing = {};
		} else {
			timing[ action ] = timeStamp;
		}
		/* eslint-enable camelcase */

		addABTestData( data, true );

		logEditViaMetricsPlatform( data, actionPrefix );

		/* eslint-disable camelcase */
		var event = ve.extendObject( {
			version: 1,
			action: action,
			is_oversample: !inEASSample(),
			editor_interface: 'visualeditor',
			integration: ve.init && ve.init.target && ve.init.target.constructor.static.integrationType || 'page',
			page_id: mw.config.get( 'wgArticleId' ),
			page_title: mw.config.get( 'wgPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			// eslint-disable-next-line no-jquery/no-global-selector
			revision_id: mw.config.get( 'wgRevisionId' ) || +$( 'input[name=parentRevId]' ).val() || 0,
			editing_session_id: editingSessionId,
			page_token: mw.user.getPageviewToken(),
			session_token: mw.user.sessionId(),
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			mw_version: mw.config.get( 'wgVersion' )
		}, data );

		if ( mw.user.isAnon() ) {
			event.user_class = 'IP';
		}

		if ( trackdebug ) {
			log( topic, duration + 'ms', event );
		} else {
			mw.track( 'event.EditAttemptStep', event );
		}
	}

	function mwTimingHandler( topic, data ) {
		// Add type for save errors; not in the topic for stupid historical reasons
		if ( topic === 'mwtiming.performance.user.saveError' ) {
			topic = topic + '.' + data.type;
		}

		// Map mwtiming.foo --> timing.ve.foo.mobile
		topic = topic.replace( /^mwtiming/, 'timing.ve.' + data.targetName );
		if ( trackdebug ) {
			log( topic, Math.round( data.duration ) + 'ms' );
		} else {
			mw.track( topic, data.duration );
		}
	}

	function activityHandler( topic, data ) {
		var feature = topic.split( '.' )[ 1 ];

		if ( !inVEFUSample() && !mw.config.get( 'wgWMESchemaEditAttemptStepOversample' ) && !trackdebug ) {
			return;
		}

		if ( ve.init.target && (
			ve.init.target.constructor.static.platformType !== 'desktop'
		) ) {
			// We want to log activity events when we're also logging to
			// EditAttemptStep. The EAS events are only fired from DesktopArticleTarget
			// in this repo. As such, we suppress this unless the current target is at
			// least inheriting that. (Other tools may fire their own instances of
			// those events, but probably need to reimplement this anyway for
			// session-identification reasons.)
			return;
		}

		/* eslint-disable camelcase */
		var event = {
			feature: feature,
			action: data.action,
			editingSessionId: editingSessionId,
			is_oversample: !inVEFUSample(),
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			editor_interface: ve.getProp( ve, 'init', 'target', 'surface', 'mode' ) === 'source' ? 'wikitext-2017' : 'visualeditor',
			integration: ve.getProp( ve, 'init', 'target', 'constructor', 'static', 'integrationType' ) || 'page',
			platform: ve.getProp( ve, 'init', 'target', 'constructor', 'static', 'platformType' ) || 'other'
		};
		/* eslint-enable camelcase */

		addABTestData( data );

		if ( trackdebug ) {
			log( topic, event );
		} else {
			mw.track( 'event.VisualEditorFeatureUse', event );

			// T309602: Also log via the Metrics Platform:
			var eventName = 'vefu.' + event.action;

			/* eslint-disable camelcase */
			var customData = {
				feature: event.feature,
				editing_session_id: event.editingSessionId,
				editor_interface: event.editor_interface,
				integration: event.integration
			};
			/* eslint-enable camelcase */

			mw.eventLog.dispatch( eventName, customData );
		}
	}

	// Only log events if the WikimediaEvents extension is installed.
	// It provides variables that the above code depends on and registers the schemas.
	if ( mw.config.exists( 'wgWMESchemaEditAttemptStepSamplingRate' ) ) {
		// Ensure 'ext.eventLogging' first, it provides mw.eventLog.randomTokenMatch and
		// mw.eventLog.dispatch.
		mw.loader.using( 'ext.eventLogging' ).done( function () {
			ve.trackSubscribe( 'mwedit.', mwEditHandler );
			ve.trackSubscribe( 'mwtiming.', mwTimingHandler );
		} );
	}

	if ( mw.config.exists( 'wgWMESchemaVisualEditorFeatureUseSamplingRate' ) ) {
		mw.loader.using( 'ext.eventLogging' ).done( function () {
			ve.trackSubscribe( 'activity.', activityHandler );
		} );
	}

}() );
