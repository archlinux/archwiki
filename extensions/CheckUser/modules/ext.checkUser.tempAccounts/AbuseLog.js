const ipReveal = require( './ipReveal.js' );

/**
 * Run code for use when the Special:AbuseLog page loads.
 *
 * This adds buttons to abuse filter log entries performed by temporary accounts,
 * for revealing the IP addresses used by the temporary account.
 *
 * @param {Object} [documentRoot] A Document or selector to use as the root of the
 *   search for elements
 */
function onLoad( documentRoot = null ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	ipReveal.enableMultiReveal( $( documentRoot ) );

	const $ipRevealButtons = ipReveal.addIpRevealButtons(
		$( '#bodyContent', documentRoot ).find( 'form ul li[data-afl-log-id]' )
	);

	// Avoid unnecessary requests for auto-reveal if the acting user is blocked (T345639).
	if ( !mw.config.get( 'wgCheckUserIsPerformerBlocked' ) ) {
		ipReveal.automaticallyRevealUsers( $ipRevealButtons );
	}
}

module.exports = {
	onLoad: onLoad
};
