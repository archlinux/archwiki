const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to contributions pages that show contributions made by a single
 * temporary user. See ipReveal#enableIpRevealForContributionsPage for details.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 * @param {string} pageTitle Declare what page this is being run on.
 *   This is for compatibility across Special:Contributions and Special:DeletedContributions,
 *   as they have different guaranteed existing elements.
 */
module.exports = function ( documentRoot, pageTitle ) {
	// Check if there is a temporary user link in any revision line in the list. If not,
	// the page has only one target, so we need to add the custom logic for contributions
	// pages with no temporary user links
	const $userLinks = $( '#bodyContent', documentRoot )
		.find( '.mw-contributions-list [data-mw-revid]' )
		.find( '.mw-tempuserlink' )
		// Do not include the edit summary, which might contain user links
		.not( '.comment .mw-tempuserlink' );

	if ( $userLinks.length === 0 ) {
		// The contributions page has only one target and therefore no user links
		ipReveal.enableIpRevealForContributionsPage( documentRoot, pageTitle );
	} else {
		// The contributions page has user links, due to having multiple targets. Treat
		// this like any other page.
		require( './initOnLoad.js' )();
	}
};
