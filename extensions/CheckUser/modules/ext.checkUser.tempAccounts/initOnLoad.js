const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to a page. If there are any temporary user links on the page, if
 * any of those links can be associated with an IP address that the user used, add a button which
 * looks up the IP and displays it when clicked. Typically, this means that the user link appears
 * in a log line for an edit that the temporary user made, or a log action that they performed.
 *
 * This functionality is added in the following steps:
 * - Enable multi-reveal functionality, so that if one IP is revealed for a temporary user, they
 *   are all revealed.
 * - Add buttons next to temporary account user links, for revealing the IP.
 * - Automatically reveal and IPs that should be revealed without any user interaction. This can
 *   be because they have revealed a particular temporary user's IPs recently, or because
 *   auto-reveal mode is switched on.
 *
 * In summary, this adds buttons for revealing IPs to a page containing temporary user links, and
 * displays any IPs that should already be revealed.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	ipReveal.enableMultiReveal( $( documentRoot ) );

	const $ipRevealButtons = ipReveal.addIpRevealButtons( $( '#bodyContent', documentRoot ) );

	// Avoid unnecessary requests for auto-reveal if the acting user is blocked (T345639).
	if ( !mw.config.get( 'wgCheckUserIsPerformerBlocked' ) ) {
		ipReveal.automaticallyRevealUsers( $ipRevealButtons );
	}
};
