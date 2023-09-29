'use strict';

var trackdebug = !!mw.util.getParamValue( 'trackdebug' ),
	featuresEnabled = mw.config.get( 'wgDiscussionToolsFeaturesEnabled' ) || {},
	enable2017Wikitext = featuresEnabled.sourcemodetoolbar,
	session = {
		// Create an initial session ID in case any VisualEditorFeatureUse events
		// trigger before our first init event
		// eslint-disable-next-line camelcase
		editing_session_id: mw.user.generateRandomSessionId()
	};

/**
 * Logs an event to http://meta.wikimedia.org/wiki/Schema:EditAttemptStep
 *
 * @instance
 * @param {Object} data
 */
module.exports = function ( data ) {
	mw.track( 'dt.schemaEditAttemptStep', data );
};
module.exports.getSessionId = function () {
	return session.editing_session_id;
};

// Ensure 'ext.eventLogging' first, it provides mw.eventLog.randomTokenMatch.
// (No explicit dependency is set because we want this to just quietly not-happen
// if EventLogging isn't installed.)
mw.loader.using( 'ext.eventLogging' ).done( function () {
	var // Schema class is provided by ext.eventLogging
		Schema = mw.eventLog.Schema,
		user = mw.user,
		easSampleRate = mw.config.get( 'wgDTSchemaEditAttemptStepSamplingRate' ) ||
			mw.config.get( 'wgWMESchemaEditAttemptStepSamplingRate' ),
		vefuSampleRate = mw.config.get( 'wgWMESchemaVisualEditorFeatureUseSamplingRate' ) || easSampleRate,
		actionPrefixMap = {
			firstChange: 'first_change',
			saveIntent: 'save_intent',
			saveAttempt: 'save_attempt',
			saveSuccess: 'save_success',
			saveFailure: 'save_failure'
		},
		timing = {},
		firstInitDone = false,
		/**
		 * Edit schema
		 * https://meta.wikimedia.org/wiki/Schema:EditAttemptStep
		 */
		/* eslint-disable camelcase */
		schemaEditAttemptStep = new Schema(
			'EditAttemptStep',
			easSampleRate,
			// defaults:
			{
				page_id: mw.config.get( 'wgArticleId' ),
				revision_id: mw.config.get( 'wgRevisionId' ),
				page_title: mw.config.get( 'wgPageName' ),
				page_ns: mw.config.get( 'wgNamespaceNumber' ),
				user_id: user.getId(),
				user_class: user.isAnon() ? 'IP' : undefined,
				user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
				mw_version: mw.config.get( 'wgVersion' ),
				// T249944 may someday change this to not hang from MobileFrontend
				platform: mw.config.get( 'wgMFMode' ) !== null ? 'phone' : 'desktop',
				integration: 'discussiontools',
				page_token: user.getPageviewToken(),
				session_token: user.sessionId(),
				version: 1
			}
		),
		schemaVisualEditorFeatureUse = new Schema(
			'VisualEditorFeatureUse',
			vefuSampleRate,
			// defaults:
			{
				user_id: user.getId(),
				user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
				// T249944 may someday change this to not hang from MobileFrontend
				platform: mw.config.get( 'wgMFMode' ) !== null ? 'phone' : 'desktop',
				integration: 'discussiontools'
			}
		);
		/* eslint-enable camelcase */

	function log() {
		// mw.log is a no-op unless resource loader is in debug mode, so
		// this allows trackdebug to work independently
		// eslint-disable-next-line no-console
		console.log.apply( console, arguments );
	}

	function computeDuration( action, event, timeStamp ) {
		// This is duplicated from the VisualEditor extension
		// (ve.init.mw.trackSubscriber.js). Changes to this should be kept in
		// sync with that file, so the data remains consistent.
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
				mw.log.warn( 'dt.schemaEditAttemptStep: Do not rely on default timing value for saveSuccess/saveFailure' );
				return -1;
			case 'abort':
				switch ( event.abort_type ) {
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
				mw.log.warn( 'dt.schemaEditAttemptStep: Unrecognized abort type', event.type );
				return -1;
		}
		mw.log.warn( 'dt.schemaEditAttemptStep: Unrecognized action', action );
		return -1;
	}

	mw.trackSubscribe( 'dt.schemaEditAttemptStep', function ( topic, data ) {
		var actionPrefix = actionPrefixMap[ data.action ] || data.action,
			timeStamp = mw.now(),
			duration = 0;

		// Update the rolling session properties
		if ( data.action === 'init' ) {
			if ( firstInitDone ) {
				// eslint-disable-next-line camelcase
				session.editing_session_id = mw.user.generateRandomSessionId();
			}
			firstInitDone = true;
		}
		// eslint-disable-next-line camelcase
		session.editor_interface = data.editor_interface || session.editor_interface;

		// Schema's kind of a mess of special properties
		if ( data.action === 'init' || data.action === 'abort' || data.action === 'saveFailure' ) {
			data[ actionPrefix + '_type' ] = data.type;
		}
		if ( data.action === 'init' || data.action === 'abort' ) {
			data[ actionPrefix + '_mechanism' ] = data.mechanism;
		}
		if ( data.action !== 'init' ) {
			// Schema actually does have an init_timing field, but we don't want to
			// store it because it's not meaningful.
			duration = Math.round( computeDuration( data.action, data, timeStamp ) );
			data[ actionPrefix + '_timing' ] = duration;
		}
		if ( data.action === 'saveFailure' ) {
			data[ actionPrefix + '_message' ] = data.message;
		}

		// Remove renamed properties
		delete data.type;
		delete data.mechanism;
		delete data.timing;
		delete data.message;
		// eslint-disable-next-line camelcase
		data.is_oversample =
			!mw.eventLog.inSample( 1 / easSampleRate );

		if ( data.action === 'abort' && data.abort_type !== 'switchnochange' ) {
			timing = {};
		} else {
			timing[ data.action ] = timeStamp;
		}

		// Switching between visual and source produces a chain of
		// abort/ready/loaded events and no init event, so suppress them for
		// consistency with desktop VE's logging.
		if ( data.abort_type === 'switchnochange' ) {
			// The initial abort, flagged as a switch
			return;
		}
		if ( timing.abort ) {
			// An abort was previously logged
			if ( data.action === 'ready' ) {
				// Just discard the ready
				return;
			}
			if ( data.action === 'loaded' ) {
				// Switch has finished; remove the abort timing so we stop discarding events.
				delete timing.abort;
				return;
			}
		}

		if ( mw.config.get( 'wgDiscussionToolsABTestBucket' ) ) {
			data.bucket = mw.config.get( 'wgDiscussionToolsABTestBucket' );
			if ( mw.user.isAnon() && mw.config.get( 'wgDiscussionToolsAnonymousUserId' ) ) {
				// eslint-disable-next-line camelcase
				data.anonymous_user_token = mw.config.get( 'wgDiscussionToolsAnonymousUserId' );
			}
		}

		$.extend( data, session );

		if ( trackdebug ) {
			log( topic + '.' + data.action, duration + 'ms', data, schemaEditAttemptStep.defaults );
		} else {
			schemaEditAttemptStep.log(
				data,
				(
					mw.config.get( 'wgDTSchemaEditAttemptStepOversample' ) ||
					mw.config.get( 'wgWMESchemaEditAttemptStepOversample' )
				) ? 1 : easSampleRate
			);

			// T309013: Also log via the Metrics Platform:
			var eventName = 'eas.dt.' + actionPrefix;
			var customData = $.extend(
				{
					integration: 'discussiontools'
				},
				data
			);

			delete customData.action;

			// Sampling rate (and therefore whether a stream should oversample) is captured in the
			// stream config ($wgEventStreams).
			delete customData.is_oversample;

			mw.eventLog.dispatch( eventName, customData );
		}
	} );

	mw.trackSubscribe( 'dt.schemaVisualEditorFeatureUse', function ( topic, data ) {
		// eslint-disable-next-line camelcase
		session.editor_interface = data.editor_interface || session.editor_interface;

		var event = {
			feature: data.feature,
			action: data.action,
			editingSessionId: session.editing_session_id,
			// eslint-disable-next-line camelcase
			editor_interface: session.editor_interface
		};

		if ( mw.config.get( 'wgDiscussionToolsABTestBucket' ) ) {
			event.bucket = mw.config.get( 'wgDiscussionToolsABTestBucket' );
		}

		if ( trackdebug ) {
			log( topic, event, schemaVisualEditorFeatureUse.defaults );
		} else {
			schemaVisualEditorFeatureUse.log( event, (
				mw.config.get( 'wgDTSchemaEditAttemptStepOversample' ) ||
				mw.config.get( 'wgWMESchemaEditAttemptStepOversample' )
			) ? 1 : vefuSampleRate );

			// T309602: Also log via the Metrics Platform:
			var eventName = 'vefu.' + data.action;

			/* eslint-disable camelcase */
			var customData = {
				feature: data.feature,
				editing_session_id: session.editing_session_id,
				editor_interface: session.editor_interface,
				integration: 'discussiontools'
			};
			/* eslint-enable camelcase */

			mw.eventLog.dispatch( eventName, customData );
		}

		if ( data.feature === 'editor-switch' && data.action.indexOf( 'dialog-' ) === -1 ) {
			// eslint-disable-next-line camelcase
			session.editor_interface = session.editor_interface === 'visualeditor' ?
				( enable2017Wikitext ? 'wikitext-2017' : 'wikitext' ) :
				'visualeditor';
		}
	} );
} );
