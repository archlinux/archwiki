const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to a page that automatically updates.
 *
 * This is very similar to initOnLoad, except:
 * - Multi-reveal functionality is enabled once, on page load.
 * - New buttons and new revealed IPs are potentially added each time the page updates.
 *
 * @param {string|jQuery|*} documentRoot A DOM Element, Document, jQuery or selector
 *   to use as context
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		const $ipRevealButtons = ipReveal.addIpRevealButtons( $content );
		ipReveal.automaticallyRevealUsers( $ipRevealButtons );
	} );

	ipReveal.enableMultiReveal( $( documentRoot ) );
};
