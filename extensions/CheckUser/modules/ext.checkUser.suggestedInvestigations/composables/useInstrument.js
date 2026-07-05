'use strict';

/**
 * Additional context for an instrumentation event.
 *
 * @typedef {Object} InteractionData
 * @property {string} [context] - Context of the action
 */

/**
 * @callback LogEvent Log an event to the Suggested Investigations event stream.
 *
 * @param {string} action
 * @param {InteractionData} [data]
 */

/**
 * Composable to create an event logging function configured to log events to the
 * Suggested Investigations event stream.
 *
 * @return {LogEvent}
 */
module.exports = () => {
	if ( !mw.eventLog ) {
		// EventLogging is not installed
		return () => Promise.resolve();
	}

	const instrument = mw.eventLog.newInstrument(
		'mediawiki.product_metrics.suggested_investigations_interaction.v2',
		'/analytics/mediawiki/suggested_investigations/interaction/1.1.4'
	);

	return ( action, data = {} ) => {
		// Performer data is set here rather than via provide_values in the
		// stream config, because the server-side instrumentation for this
		// stream sets performer manually to a different user.
		const interactionData = {
			performer: {
				id: mw.user.getId(),
				name: mw.user.getName(),
				// eslint-disable-next-line camelcase
				edit_count: mw.config.get( 'wgUserEditCount' ),
				// eslint-disable-next-line camelcase
				edit_count_bucket: mw.config.get( 'wgUserEditCountBucket' ),
				groups: mw.config.get( 'wgUserGroups' ),
				// eslint-disable-next-line camelcase
				registration_dt: new Date( mw.config.get( 'wgUserRegistration' ) ).toISOString(),
				// eslint-disable-next-line camelcase
				pageview_id: mw.user.getPageviewToken()
			}
		};

		if ( data.context ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = data.context;
		}

		instrument.submitInteraction( action, interactionData );
	};
};
