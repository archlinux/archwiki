'use strict';

/**
 * Additional context for an instrumentation event.
 *
 * @typedef {Object} InteractionData
 * @property {string} [subType] - Subtype of the action
 * @property {string} [source] - Source of the action
 * @property {string} [context] - Context of the action
 */

/**
 * @callback LogEvent Log an event to the CheckUser UserInfoCard interaction event stream.
 *
 * @param {string} action - The action performed
 * @param {InteractionData} [data] - Additional data about the interaction
 */

/**
 * Lazy singleton instance of the underlying Metrics Platform instrument.
 */
let instrument;

/**
 * Composable to create an event logging function configured to log events to the CheckUser
 * UserInfoCard interaction event stream.
 *
 * @return {LogEvent}
 */
const useInstrument = () => {
	if ( !mw.eventLog ) {
		// EventLogging is not installed
		return () => {};
	}

	if ( !mw.config.get( 'wgCheckUserEnableUserInfoCardInstrumentation' ) ) {
		return () => {};
	}

	if ( !instrument ) {
		instrument = mw.eventLog.newInstrument(
			'mediawiki.product_metrics.user_info_card_interaction',
			'/analytics/product_metrics/web/base/1.4.2'
		);
	}

	return ( action, data = {} ) => {
		// Generate a session token for tracking the user's journey
		const sessionToken = mw.user.generateRandomSessionId();
		const interactionData = {
			// eslint-disable-next-line camelcase
			funnel_entry_token: sessionToken
		};

		if ( data.subType ) {
			// eslint-disable-next-line camelcase
			interactionData.action_subtype = data.subType;
		}

		if ( data.source ) {
			// eslint-disable-next-line camelcase
			interactionData.action_source = data.source;
		}

		if ( data.context ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = data.context;
		}

		instrument.submitInteraction( action, interactionData );
	};
};

module.exports = useInstrument;
